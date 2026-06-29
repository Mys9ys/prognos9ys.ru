<?php

namespace Prognos9ys\Main\Service\Game;

use Bitrix\Main\Type\DateTime;
use Prognos9ys\Main\Model\Repository\GameEconomyRepository;

class BankConsignmentService
{
    private GameEconomyRepository $repository;
    private ExchangeInventoryService $inventoryService;
    private WalletService $walletService;

    public function __construct(?GameEconomyRepository $repository = null)
    {
        $this->repository = $repository ?? new GameEconomyRepository();
        $this->inventoryService = new ExchangeInventoryService($this->repository);
        $this->walletService = new WalletService($this->repository);
    }

    /**
     * @return array<string, mixed>
     */
    public function getConsignmentSettingsForBank(array $bankRow): array
    {
        return [
            'enabled' => BankConsignmentConfig::isConsignmentEnabled($bankRow),
            'categories' => BankConsignmentConfig::parseCategoryFlags(
                (string)($bankRow['UF_CONSIGNMENT_CATEGORIES'] ?? '')
            ),
            'category_options' => $this->buildCategoryOptions(),
            'instant_payout_percent' => BankConsignmentConfig::INSTANT_PAYOUT_PERCENT,
        ];
    }

    /**
     * @param array<string, bool> $categories
     * @return array<string, mixed>
     */
    public function updateConsignmentSettings(int $ownerId, bool $enabled, array $categories = []): array
    {
        $bank = $this->repository->getUserBankByOwnerId($ownerId);
        if (!$bank) {
            throw new \RuntimeException('Активный банк не найден');
        }

        $bankId = (int)$bank['ID'];
        $flags = BankConsignmentConfig::defaultCategoryFlags();
        foreach ($flags as $kind => $defaultValue) {
            if (array_key_exists($kind, $categories)) {
                $flags[$kind] = !empty($categories[$kind]);
            }
        }

        $this->repository->updateUserBank($bankId, [
            'UF_CONSIGNMENT_ENABLED' => $enabled ? 'Y' : 'N',
            'UF_CONSIGNMENT_CATEGORIES' => BankConsignmentConfig::encodeCategoryFlags($flags),
        ]);

        $updated = $this->repository->getUserBankById($bankId);

        return $this->getConsignmentSettingsForBank($updated ?: $bank);
    }

    /**
     * @return array{price_per_unit: float, instant_per_unit: float, chunks: int, total_instant: float}
     */
    public function quoteConsignment(
        int $userId,
        string $kind,
        string $code,
        int $qty,
        string $category = '',
        int $eventId = 0,
        string $teamCode = ''
    ): array {
        $this->validateConsignParams($userId, $kind, $code, $qty, $category, $eventId, $teamCode);

        $pallet = ExchangeNominalConfig::getPalletLimit($kind, $code, $category);
        $chunks = $this->splitQtyIntoChunks($qty, $pallet);
        $pricePerUnit = $this->resolveConsignmentPrice($kind, $code, $category, $eventId, $teamCode);
        $instantPerUnit = round($pricePerUnit * BankConsignmentConfig::INSTANT_PAYOUT_PERCENT / 100, 1);
        $totalInstant = 0.0;

        foreach ($chunks as $chunkQty) {
            $chunkInstant = round($instantPerUnit * $chunkQty, 1);
            $totalInstant = round($totalInstant + $chunkInstant, 1);
            if ($this->repository->getEligibleConsignmentBanks($kind, $chunkInstant, $category, $code) === []) {
                throw new \RuntimeException('Нет банка');
            }
        }

        return [
            'price_per_unit' => $pricePerUnit,
            'instant_per_unit' => $instantPerUnit,
            'chunks' => count($chunks),
            'total_instant' => $totalInstant,
        ];
    }

    /**
     * @return array{chunks: array<int, array<string, mixed>>, total_paid: float}
     */
    public function consign(
        int $userId,
        string $kind,
        string $code,
        int $qty,
        string $category = '',
        int $eventId = 0,
        string $teamCode = ''
    ): array {
        $kind = trim($kind);
        $code = trim($code);
        $category = trim($category);
        $teamCode = trim($teamCode);
        if ($kind === ExchangeConfig::KIND_CHEST) {
            $code = ExchangeConfig::normalizeChestExchangeCode($code);
        }

        $this->validateConsignParams($userId, $kind, $code, $qty, $category, $eventId, $teamCode);

        $pallet = ExchangeNominalConfig::getPalletLimit($kind, $code, $category);
        $chunkQtys = $this->splitQtyIntoChunks($qty, $pallet);
        $results = [];
        $totalPaid = 0.0;

        foreach ($chunkQtys as $chunkQty) {
            $results[] = $this->consignChunk(
                $userId,
                $kind,
                $code,
                $chunkQty,
                $category,
                $eventId,
                $teamCode
            );
            $totalPaid = round($totalPaid + (float)($results[count($results) - 1]['instant_paid'] ?? 0), 1);
        }

        return [
            'chunks' => $results,
            'total_paid' => $totalPaid,
        ];
    }

    public function resolveConsignmentPrice(
        string $kind,
        string $code,
        string $category = '',
        int $eventId = 0,
        string $teamCode = ''
    ): float {
        $minPrice = $this->repository->getMinActiveExchangePriceForSku(
            $kind,
            $code,
            $category,
            $eventId,
            $teamCode
        );
        if ($minPrice !== null) {
            return $minPrice;
        }

        $nominal = $this->inventoryService->resolveNominal($kind, $code, $category, $teamCode ?: null);
        $maxPrice = ExchangeNominalConfig::getMaxSellerPrice($nominal);

        $lastPrice = $this->repository->getLastExchangeTradePriceForSku(
            $kind,
            $code,
            $category,
            $eventId,
            $teamCode
        );
        if ($lastPrice !== null) {
            return round(min($lastPrice * ExchangeConfig::SELLER_PRICE_CAP_MULTIPLIER, $maxPrice), 1);
        }

        return round($nominal, 1);
    }

    public function relistExpiredConsignmentListing(array $listingRow): bool
    {
        $consignmentId = (int)($listingRow['UF_CONSIGNMENT_ID'] ?? 0);
        $listingId = (int)($listingRow['ID'] ?? 0);
        if ($consignmentId <= 0 || $listingId <= 0) {
            return false;
        }

        $consignment = $this->repository->getBankConsignmentById($consignmentId);
        if (!$consignment) {
            return false;
        }

        $kind = (string)($listingRow['UF_ITEM_KIND'] ?? '');
        $code = (string)($listingRow['UF_ITEM_CODE'] ?? '');
        $category = (string)($listingRow['UF_ITEM_CATEGORY'] ?? '');
        $eventId = (int)($listingRow['UF_EVENT_ID'] ?? 0);
        $teamCode = (string)($listingRow['UF_TEAM_CODE'] ?? '');
        $qty = (int)($listingRow['UF_QTY_REMAINING'] ?? 0);
        if ($qty <= 0) {
            return false;
        }

        $price = $this->resolveConsignmentPrice($kind, $code, $category, $eventId, $teamCode);
        $nominal = $this->inventoryService->resolveNominal($kind, $code, $category, $teamCode ?: null);
        $now = new DateTime();
        $listingDays = ExchangeConfig::LISTING_DAYS_DEFAULT;
        $expiresAt = DateTime::createFromTimestamp($now->getTimestamp() + $listingDays * 86400);

        $this->repository->updateExchangeListing($listingId, [
            'UF_PRICE_PER_UNIT' => $price,
            'UF_NOMINAL_SNAPSHOT' => $nominal,
            'UF_STATUS' => ExchangeConfig::STATUS_ACTIVE,
            'UF_EXPIRES_AT' => $expiresAt,
            'UF_UPDATED_AT' => $now,
        ]);

        $this->repository->updateBankConsignment($consignmentId, [
            'UF_PRICE_PER_UNIT' => $price,
            'UF_RELIST_COUNT' => (int)($consignment['UF_RELIST_COUNT'] ?? 0) + 1,
            'UF_STATUS' => BankConsignmentConfig::STATUS_ACTIVE,
            'UF_UPDATED_AT' => $now,
        ]);

        return true;
    }

    public function markConsignmentSold(int $consignmentId): void
    {
        if ($consignmentId <= 0) {
            return;
        }

        $this->repository->updateBankConsignment($consignmentId, [
            'UF_STATUS' => BankConsignmentConfig::STATUS_SOLD,
            'UF_UPDATED_AT' => new DateTime(),
        ]);
    }

    public function isConsignmentListing(array $listingRow): bool
    {
        return (int)($listingRow['UF_SELLER_BANK_ID'] ?? 0) > 0
            || (int)($listingRow['UF_CONSIGNMENT_ID'] ?? 0) > 0;
    }

    /**
     * @return array<string, mixed>
     */
    private function consignChunk(
        int $userId,
        string $kind,
        string $code,
        int $chunkQty,
        string $category,
        int $eventId,
        string $teamCode
    ): array {
        $pricePerUnit = $this->resolveConsignmentPrice($kind, $code, $category, $eventId, $teamCode);
        $instantPaid = round($pricePerUnit * $chunkQty * BankConsignmentConfig::INSTANT_PAYOUT_PERCENT / 100, 1);

        $eligibleBanks = $this->repository->getEligibleConsignmentBanks($kind, $instantPaid, $category, $code);
        if ($eligibleBanks === []) {
            throw new \RuntimeException('Нет банка');
        }

        $bank = $eligibleBanks[array_rand($eligibleBanks)];
        $bankId = (int)($bank['ID'] ?? 0);
        $bankOwnerId = (int)($bank['UF_OWNER_ID'] ?? 0);
        if ($bankId <= 0 || $bankOwnerId <= 0) {
            throw new \RuntimeException('Нет банка');
        }

        $this->inventoryService->takeFromSeller($userId, $kind, $code, $category, $eventId, $chunkQty);

        try {
            $this->repository->adjustUserBankLiquid($bankId, -$instantPaid);
        } catch (\Throwable $exception) {
            $this->inventoryService->giveToBuyer($userId, $kind, $code, $category, $eventId, $teamCode, $chunkQty);
            throw $exception;
        }

        $now = new DateTime();
        $listingDays = ExchangeConfig::LISTING_DAYS_DEFAULT;
        $expiresAt = DateTime::createFromTimestamp($now->getTimestamp() + $listingDays * 86400);
        $nominal = $this->inventoryService->resolveNominal($kind, $code, $category, $teamCode ?: null);

        $listingId = $this->repository->addExchangeListing([
            'UF_SELLER_ID' => $bankOwnerId,
            'UF_SELLER_BANK_ID' => $bankId,
            'UF_ORIGINAL_USER_ID' => $userId,
            'UF_CONSIGNMENT_ID' => 0,
            'UF_ITEM_KIND' => $kind,
            'UF_ITEM_CODE' => $code,
            'UF_ITEM_CATEGORY' => $category,
            'UF_EVENT_ID' => $eventId,
            'UF_TEAM_CODE' => $teamCode,
            'UF_QTY_TOTAL' => $chunkQty,
            'UF_QTY_REMAINING' => $chunkQty,
            'UF_PRICE_PER_UNIT' => $pricePerUnit,
            'UF_NOMINAL_SNAPSHOT' => $nominal,
            'UF_STATUS' => ExchangeConfig::STATUS_ACTIVE,
            'UF_ESCROW_REF_TYPE' => ExchangeConfig::ESCROW_REF_TYPE,
            'UF_ESCROW_REF_ID' => 0,
            'UF_EXPIRES_AT' => $expiresAt,
            'UF_CREATED_AT' => $now,
            'UF_UPDATED_AT' => $now,
        ]);

        $consignmentId = $this->repository->addBankConsignment([
            'UF_USER_ID' => $userId,
            'UF_BANK_ID' => $bankId,
            'UF_LISTING_ID' => $listingId,
            'UF_ITEM_KIND' => $kind,
            'UF_ITEM_CODE' => $code,
            'UF_ITEM_CATEGORY' => $category,
            'UF_EVENT_ID' => $eventId,
            'UF_TEAM_CODE' => $teamCode,
            'UF_QTY' => $chunkQty,
            'UF_PRICE_PER_UNIT' => $pricePerUnit,
            'UF_INSTANT_PAID' => $instantPaid,
            'UF_STATUS' => BankConsignmentConfig::STATUS_ACTIVE,
            'UF_RELIST_COUNT' => 0,
            'UF_CREATED_AT' => $now,
            'UF_UPDATED_AT' => $now,
        ]);

        $this->repository->updateExchangeListing($listingId, [
            'UF_CONSIGNMENT_ID' => $consignmentId,
        ]);

        $this->walletService->credit(
            $userId,
            GameEconomyConfig::CURRENCY_PROGNOBAKS,
            $instantPaid,
            'bank_consignment_payout',
            'bank_consignment',
            $consignmentId
        );

        return [
            'bank_id' => $bankId,
            'bank_owner_id' => $bankOwnerId,
            'listing_id' => $listingId,
            'consignment_id' => $consignmentId,
            'qty' => $chunkQty,
            'price_per_unit' => $pricePerUnit,
            'instant_paid' => $instantPaid,
        ];
    }

    private function validateConsignParams(
        int $userId,
        string $kind,
        string $code,
        int $qty,
        string $category,
        int $eventId,
        string $teamCode
    ): void {
        if ($userId <= 0 || $kind === '' || $code === '' || $qty <= 0) {
            throw new \InvalidArgumentException('Некорректные параметры');
        }

        $available = $this->inventoryService->getAvailableQty($userId, $kind, $code, $category, $eventId);
        if ($available < $qty) {
            throw new \RuntimeException('Недостаточно товара');
        }
    }

    /**
     * @return array<int, int>
     */
    private function splitQtyIntoChunks(int $qty, int $pallet): array
    {
        $pallet = max(1, $pallet);
        $chunks = [];
        $remaining = $qty;

        while ($remaining > 0) {
            $take = min($remaining, $pallet);
            $chunks[] = $take;
            $remaining -= $take;
        }

        return $chunks;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function buildCategoryOptions(): array
    {
        $labels = [
            ExchangeCatalogConfig::TAB_CHEST => 'Сундуки',
            ExchangeCatalogConfig::TAB_PREMIUM_SCROLL => 'Премиум-свитки',
            ExchangeCatalogConfig::TAB_LOOT => 'ККИ (паки)',
            ExchangeCatalogConfig::TAB_SOUVENIR => 'Сувениры',
            ExchangeCatalogConfig::TAB_XP_BANK => 'Банки XP',
            ExchangeCatalogConfig::TAB_CERT => 'Лицензии и сертификаты',
            ExchangeCatalogConfig::TAB_MATERIAL => 'Материалы',
        ];

        $options = [];
        foreach (ExchangeCatalogConfig::consignmentTabIds() as $tabId) {
            $options[] = [
                'id' => $tabId,
                'label' => $labels[$tabId] ?? $tabId,
            ];
        }

        return $options;
    }
}
