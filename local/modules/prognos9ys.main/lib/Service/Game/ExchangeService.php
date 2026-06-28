<?php

namespace Prognos9ys\Main\Service\Game;

use Bitrix\Main\Type\DateTime;
use Prognos9ys\Main\Model\Repository\GameEconomyRepository;
use Prognos9ys\Main\Model\Repository\ProfessionRepository;

class ExchangeService
{
    private GameEconomyRepository $repository;
    private ExchangeInventoryService $inventoryService;
    private WalletService $walletService;
    private TreasuryService $treasuryService;

    public function __construct(?GameEconomyRepository $repository = null)
    {
        $this->repository = $repository ?? new GameEconomyRepository();
        $this->inventoryService = new ExchangeInventoryService($this->repository);
        $this->walletService = new WalletService($this->repository);
        $this->treasuryService = new TreasuryService($this->repository);
    }

    public function getState(int $userId): array
    {
        $wallet = $this->walletService->getWalletSummary($userId);
        $activeListings = $this->repository->countActiveExchangeListingsForUser($userId);

        return [
            'wallet_prognobaks' => (float)($wallet['prognobaks'] ?? 0),
            'active_listings' => $activeListings,
            'max_listings' => $this->resolveMaxListings($userId),
            'listing_days' => $this->resolveListingDays($userId),
            'commission_percent' => ExchangeConfig::COMMISSION_PERCENT,
            'sellable' => $this->buildSellableCatalog($userId),
        ];
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, pagination: array<string, int|bool>}
     */
    public function getCatalog(int $offset = 0, int $limit = 25, string $kind = ''): array
    {
        $page = $this->repository->getExchangeCatalogPage($offset, $limit, $kind);
        $items = [];

        foreach ($page['items'] as $row) {
            $items[] = $this->formatListing($row);
        }

        $limit = max(1, min($limit, 50));
        $offset = max(0, $offset);
        $total = (int)($page['total'] ?? 0);

        return [
            'items' => $items,
            'pagination' => [
                'offset' => $offset,
                'limit' => $limit,
                'total' => $total,
                'has_more' => ($offset + count($items)) < $total,
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getMyListings(int $userId): array
    {
        $rows = $this->repository->getExchangeListingsForSeller($userId, true);

        return array_map(fn(array $row) => $this->formatListing($row), $rows);
    }

    public function createListing(
        int $userId,
        string $kind,
        string $code,
        int $qty,
        float $pricePerUnit,
        string $category = '',
        int $eventId = 0,
        string $teamCode = ''
    ): array {
        $kind = trim($kind);
        $code = trim($code);
        $category = trim($category);
        $teamCode = trim($teamCode);

        if ($userId <= 0 || $kind === '' || $code === '' || $qty <= 0) {
            throw new \InvalidArgumentException('Некорректные параметры лота');
        }

        $pallet = ExchangeNominalConfig::getPalletLimit($kind, $code, $category);
        if ($qty > $pallet) {
            throw new \RuntimeException('Максимум ' . $pallet . ' шт. в одном лоте');
        }

        if ($this->repository->countActiveExchangeListingsForUser($userId) >= $this->resolveMaxListings($userId)) {
            throw new \RuntimeException('Достигнут лимит активных лотов');
        }

        $nominal = $this->inventoryService->resolveNominal($kind, $code, $category, $teamCode ?: null);
        $maxPrice = ExchangeNominalConfig::getMaxSellerPrice($nominal);
        $pricePerUnit = round($pricePerUnit, 1);

        if ($pricePerUnit < $nominal || $pricePerUnit > $maxPrice + 0.01) {
            throw new \RuntimeException(
                'Цена должна быть от ' . $nominal . ' до ' . $maxPrice . ' 🪙'
            );
        }

        $this->inventoryService->takeFromSeller($userId, $kind, $code, $category, $eventId, $qty);

        $now = new DateTime();
        $expiresAt = DateTime::createFromTimestamp(
            $now->getTimestamp() + $this->resolveListingDays($userId) * 86400
        );

        $listingId = $this->repository->addExchangeListing([
            'UF_SELLER_ID' => $userId,
            'UF_ITEM_KIND' => $kind,
            'UF_ITEM_CODE' => $code,
            'UF_ITEM_CATEGORY' => $category,
            'UF_EVENT_ID' => $eventId,
            'UF_TEAM_CODE' => $teamCode,
            'UF_QTY_TOTAL' => $qty,
            'UF_QTY_REMAINING' => $qty,
            'UF_PRICE_PER_UNIT' => $pricePerUnit,
            'UF_NOMINAL_SNAPSHOT' => $nominal,
            'UF_STATUS' => ExchangeConfig::STATUS_ACTIVE,
            'UF_ESCROW_REF_TYPE' => ExchangeConfig::ESCROW_REF_TYPE,
            'UF_ESCROW_REF_ID' => 0,
            'UF_EXPIRES_AT' => $expiresAt,
            'UF_CREATED_AT' => $now,
            'UF_UPDATED_AT' => $now,
        ]);

        $row = $this->repository->getExchangeListingById($listingId);

        return [
            'listing' => $row ? $this->formatListing($row) : null,
        ];
    }

    public function cancelListing(int $userId, int $listingId): array
    {
        $row = $this->requireOwnedActiveListing($userId, $listingId);
        $this->returnListingToSeller($row);
        $now = new DateTime();
        $this->repository->updateExchangeListing($listingId, [
            'UF_STATUS' => ExchangeConfig::STATUS_CANCELLED,
            'UF_QTY_REMAINING' => 0,
            'UF_UPDATED_AT' => $now,
        ]);

        return ['ok' => true];
    }

    public function moderatorRemoveListing(int $moderatorId, int $listingId, string $reason = ''): array
    {
        if ($moderatorId <= 0 || $listingId <= 0) {
            throw new \InvalidArgumentException('Некорректные параметры');
        }

        $row = $this->repository->getExchangeListingById($listingId);
        if (!$row || (string)($row['UF_STATUS'] ?? '') !== ExchangeConfig::STATUS_ACTIVE) {
            throw new \RuntimeException('Лот не найден');
        }

        $remaining = (int)($row['UF_QTY_REMAINING'] ?? 0);
        if ($remaining > 0) {
            $this->returnListingToSeller($row);
        }

        $now = new DateTime();
        $this->repository->updateExchangeListing($listingId, [
            'UF_STATUS' => ExchangeConfig::STATUS_MOD_REMOVED,
            'UF_QTY_REMAINING' => 0,
            'UF_UPDATED_AT' => $now,
        ]);

        return ['ok' => true, 'reason' => $reason];
    }

    public function buy(
        int $buyerId,
        string $kind,
        string $code,
        int $qty,
        string $category = '',
        int $eventId = 0,
        string $teamCode = ''
    ): array {
        if ($buyerId <= 0 || $qty <= 0) {
            throw new \InvalidArgumentException('Некорректная покупка');
        }

        $kind = trim($kind);
        $code = trim($code);
        $category = trim($category);
        $teamCode = trim($teamCode);

        $listings = $this->repository->findActiveExchangeListingsForSku(
            $kind,
            $code,
            $category,
            $eventId,
            $teamCode
        );

        $chunks = [];
        $remaining = $qty;

        foreach ($listings as $listing) {
            if ($remaining <= 0) {
                break;
            }

            $sellerId = (int)($listing['UF_SELLER_ID'] ?? 0);
            if ($sellerId === $buyerId) {
                continue;
            }

            $listingRemaining = (int)($listing['UF_QTY_REMAINING'] ?? 0);
            if ($listingRemaining <= 0) {
                continue;
            }

            $take = min($listingRemaining, $remaining);
            $price = round((float)($listing['UF_PRICE_PER_UNIT'] ?? 0), 1);
            $chunkTotal = round($price * $take, 1);
            $commission = round($chunkTotal * ExchangeConfig::COMMISSION_PERCENT / 100, 1);

            $chunks[] = [
                'listing' => $listing,
                'listing_id' => (int)($listing['ID'] ?? 0),
                'seller_id' => $sellerId,
                'take' => $take,
                'price' => $price,
                'total' => $chunkTotal,
                'commission' => $commission,
                'seller_net' => round($chunkTotal - $commission, 1),
            ];

            $remaining -= $take;
        }

        if ($remaining > 0) {
            $available = $qty - $remaining;
            throw new \RuntimeException('На бирже доступно только ' . $available . ' шт.');
        }

        $totalCost = 0.0;
        $totalCommission = 0.0;
        foreach ($chunks as $chunk) {
            $totalCost = round($totalCost + (float)$chunk['total'], 1);
            $totalCommission = round($totalCommission + (float)$chunk['commission'], 1);
        }

        $wallet = $this->walletService->getWalletSummary($buyerId);
        if (round((float)($wallet['prognobaks'] ?? 0), 1) < round($totalCost, 1)) {
            throw new \RuntimeException('Недостаточно прогнобаксов');
        }

        $batchRef = (int)(microtime(true) * 1000) % 2000000000;
        $this->walletService->debit(
            $buyerId,
            GameEconomyConfig::CURRENCY_PROGNOBAKS,
            $totalCost,
            'exchange_buy',
            'exchange_batch',
            $batchRef
        );

        $now = new DateTime();
        $trades = [];

        foreach ($chunks as $chunk) {
            $listing = $chunk['listing'];
            $listingId = (int)$chunk['listing_id'];
            $sellerId = (int)$chunk['seller_id'];
            $take = (int)$chunk['take'];

            $this->walletService->credit(
                $sellerId,
                GameEconomyConfig::CURRENCY_PROGNOBAKS,
                (float)$chunk['seller_net'],
                'exchange_sell',
                'exchange_listing',
                $listingId
            );

            $itemKind = (string)($listing['UF_ITEM_KIND'] ?? '');
            $itemCode = (string)($listing['UF_ITEM_CODE'] ?? '');
            $itemCategory = (string)($listing['UF_ITEM_CATEGORY'] ?? '');
            $itemEventId = (int)($listing['UF_EVENT_ID'] ?? 0);
            $itemTeam = (string)($listing['UF_TEAM_CODE'] ?? '');

            $this->inventoryService->giveToBuyer(
                $buyerId,
                $itemKind,
                $itemCode,
                $itemCategory,
                $itemEventId,
                $itemTeam,
                $take
            );

            $listingRemaining = (int)($listing['UF_QTY_REMAINING'] ?? 0);
            $newRemaining = $listingRemaining - $take;
            $this->repository->updateExchangeListing($listingId, [
                'UF_QTY_REMAINING' => $newRemaining,
                'UF_STATUS' => $newRemaining <= 0 ? ExchangeConfig::STATUS_FILLED : ExchangeConfig::STATUS_ACTIVE,
                'UF_UPDATED_AT' => $now,
            ]);

            $tradeId = $this->repository->addExchangeTrade([
                'UF_LISTING_ID' => $listingId,
                'UF_SELLER_ID' => $sellerId,
                'UF_BUYER_ID' => $buyerId,
                'UF_ITEM_KIND' => $itemKind,
                'UF_ITEM_CODE' => $itemCode,
                'UF_ITEM_CATEGORY' => $itemCategory,
                'UF_EVENT_ID' => $itemEventId,
                'UF_TEAM_CODE' => $itemTeam,
                'UF_QTY' => $take,
                'UF_PRICE_PER_UNIT' => (float)$chunk['price'],
                'UF_TOTAL_PRICE' => (float)$chunk['total'],
                'UF_COMMISSION' => (float)$chunk['commission'],
                'UF_SELLER_NET' => (float)$chunk['seller_net'],
                'UF_CREATED_AT' => $now,
            ]);

            $trades[] = [
                'trade_id' => $tradeId,
                'listing_id' => $listingId,
                'qty' => $take,
                'total' => (float)$chunk['total'],
            ];
        }

        if ($totalCommission > 0) {
            $this->treasuryService->credit(
                GameEconomyConfig::CURRENCY_PROGNOBAKS,
                $totalCommission,
                'exchange_commission',
                $batchRef
            );
        }

        return [
            'bought_qty' => $qty,
            'total_spent' => $totalCost,
            'commission' => $totalCommission,
            'trades' => $trades,
        ];
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, pagination: array<string, int|bool>}
     */
    public function getTradeHistory(int $userId, int $offset = 0, int $limit = 25): array
    {
        $page = $this->repository->getExchangeTradesPageForUser($userId, $offset, $limit);
        $items = [];

        foreach ($page['items'] as $row) {
            $createdAt = $row['UF_CREATED_AT'] ?? null;
            $createdLabel = $createdAt instanceof DateTime ? $createdAt->format('d.m.Y H:i') : '';

            $items[] = [
                'id' => (int)($row['ID'] ?? 0),
                'listing_id' => (int)($row['UF_LISTING_ID'] ?? 0),
                'role' => (int)($row['UF_BUYER_ID'] ?? 0) === $userId ? 'buy' : 'sell',
                'label' => $this->inventoryService->buildItemLabel(
                    (string)($row['UF_ITEM_KIND'] ?? ''),
                    (string)($row['UF_ITEM_CODE'] ?? ''),
                    (string)($row['UF_ITEM_CATEGORY'] ?? ''),
                    (string)($row['UF_TEAM_CODE'] ?? '') ?: null
                ),
                'qty' => (int)($row['UF_QTY'] ?? 0),
                'price_per_unit' => (float)($row['UF_PRICE_PER_UNIT'] ?? 0),
                'total' => (float)($row['UF_TOTAL_PRICE'] ?? 0),
                'commission' => (float)($row['UF_COMMISSION'] ?? 0),
                'seller_net' => (float)($row['UF_SELLER_NET'] ?? 0),
                'created_at' => $createdLabel,
            ];
        }

        $limit = max(1, min($limit, 50));
        $offset = max(0, $offset);
        $total = (int)($page['total'] ?? 0);

        return [
            'items' => $items,
            'pagination' => [
                'offset' => $offset,
                'limit' => $limit,
                'total' => $total,
                'has_more' => ($offset + count($items)) < $total,
            ],
        ];
    }

    public function expireListings(): int
    {
        $rows = $this->repository->getExpiredActiveExchangeListings();
        $count = 0;
        $now = new DateTime();

        foreach ($rows as $row) {
            $listingId = (int)($row['ID'] ?? 0);
            if ($listingId <= 0) {
                continue;
            }

            $this->returnListingToSeller($row);
            $this->repository->updateExchangeListing($listingId, [
                'UF_STATUS' => ExchangeConfig::STATUS_EXPIRED,
                'UF_QTY_REMAINING' => 0,
                'UF_UPDATED_AT' => $now,
            ]);
            $count++;
        }

        return $count;
    }

    private function returnListingToSeller(array $row): void
    {
        $qty = (int)($row['UF_QTY_REMAINING'] ?? 0);
        if ($qty <= 0) {
            return;
        }

        $sellerId = (int)($row['UF_SELLER_ID'] ?? 0);
        $this->inventoryService->giveToBuyer(
            $sellerId,
            (string)($row['UF_ITEM_KIND'] ?? ''),
            (string)($row['UF_ITEM_CODE'] ?? ''),
            (string)($row['UF_ITEM_CATEGORY'] ?? ''),
            (int)($row['UF_EVENT_ID'] ?? 0),
            (string)($row['UF_TEAM_CODE'] ?? ''),
            $qty
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function requireOwnedActiveListing(int $userId, int $listingId): array
    {
        $row = $this->repository->getExchangeListingById($listingId);
        if (!$row) {
            throw new \RuntimeException('Лот не найден');
        }

        if ((int)($row['UF_SELLER_ID'] ?? 0) !== $userId) {
            throw new \RuntimeException('Нет доступа к лоту');
        }

        if ((string)($row['UF_STATUS'] ?? '') !== ExchangeConfig::STATUS_ACTIVE) {
            throw new \RuntimeException('Лот уже закрыт');
        }

        return $row;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildSellableCatalog(int $userId): array
    {
        $items = [];
        $treasure = (new TreasureService($this->repository))->getTreasureSummary($userId);

        foreach ([
            TreasureService::CHEST_TYPE_LEVEL,
            TreasureService::CHEST_TYPE_ACHIEVEMENT,
            TreasureService::CHEST_TYPE_MATCH,
            TreasureService::CHEST_TYPE_WC26_ACHIEVEMENT,
            TreasureService::CHEST_TYPE_SHOP_WC26,
        ] as $chestType) {
            $count = $this->inventoryService->getAvailableQty($userId, ExchangeConfig::KIND_CHEST, $chestType);
            if ($count <= 0) {
                continue;
            }

            $nominal = ExchangeNominalConfig::getChestNominal($chestType);
            $items[] = $this->sellableRow(
                ExchangeConfig::KIND_CHEST,
                $chestType,
                '',
                0,
                '',
                $count,
                $nominal
            );
        }

        foreach ([1, 3, 5] as $days) {
            $count = $this->inventoryService->getAvailableQty(
                $userId,
                ExchangeConfig::KIND_PREMIUM_SCROLL,
                (string)$days
            );
            if ($count <= 0) {
                continue;
            }

            $nominal = ExchangeNominalConfig::getPremiumScrollNominal($days);
            $items[] = $this->sellableRow(
                ExchangeConfig::KIND_PREMIUM_SCROLL,
                (string)$days,
                '',
                0,
                '',
                $count,
                $nominal
            );
        }

        foreach (['site', 'chm2026'] as $pennantCode) {
            $count = $this->inventoryService->getAvailableQty(
                $userId,
                ExchangeConfig::KIND_PENNANT,
                $pennantCode
            );
            if ($count <= 0) {
                continue;
            }

            $nominal = ExchangeNominalConfig::getPennantNominal($pennantCode);
            $items[] = $this->sellableRow(
                ExchangeConfig::KIND_PENNANT,
                $pennantCode,
                '',
                0,
                '',
                $count,
                $nominal
            );
        }

        $anchorEventId = (new GameEventScopeService())->getAnchorEventId();
        foreach (array_merge(
            $this->repository->getLootItemStacksForUser($userId, ChestLootConfig::LOOT_EVENT_GLOBAL),
            $anchorEventId > 0 ? $this->repository->getLootItemStacksForUser($userId, $anchorEventId) : []
        ) as $loot) {
            $count = (int)($loot['count'] ?? 0);
            if ($count <= 0) {
                continue;
            }

            $code = (string)($loot['code'] ?? '');
            $category = (string)($loot['category'] ?? '');
            if ($category === ChestLootConfig::CATEGORY_PACK && !($loot['sealed'] ?? false)) {
                continue;
            }

            $eventId = (int)($loot['event_id'] ?? 0);
            $nominal = ExchangeNominalConfig::getLootNominal($code, $category);
            $items[] = $this->sellableRow(
                ExchangeConfig::KIND_LOOT,
                $code,
                $category,
                $eventId,
                '',
                $count,
                $nominal
            );
        }

        $professionRepository = new ProfessionRepository();
        foreach ($professionRepository->getMaterialsByUserId($userId) as $materialRow) {
            $count = (int)($materialRow['UF_QTY'] ?? 0);
            if ($count <= 0) {
                continue;
            }

            $code = (string)($materialRow['UF_MATERIAL_CODE'] ?? '');
            if ($code === '') {
                continue;
            }

            $isPremium = ($materialRow['UF_IS_PREMIUM'] ?? '') === 'Y';
            $category = $isPremium
                ? ExchangeConfig::MATERIAL_CATEGORY_PREMIUM
                : ExchangeConfig::MATERIAL_CATEGORY_NORMAL;
            $nominal = ExchangeNominalConfig::getMaterialNominal($code);
            $items[] = $this->sellableRow(
                ExchangeConfig::KIND_MATERIAL,
                $code,
                $category,
                0,
                '',
                $count,
                $nominal
            );
        }

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    private function sellableRow(
        string $kind,
        string $code,
        string $category,
        int $eventId,
        string $teamCode,
        int $count,
        float $nominal
    ): array {
        $maxPrice = ExchangeNominalConfig::getMaxSellerPrice($nominal);
        $pallet = ExchangeNominalConfig::getPalletLimit($kind, $code, $category);

        return [
            'kind' => $kind,
            'code' => $code,
            'category' => $category,
            'event_id' => $eventId,
            'team_code' => $teamCode,
            'label' => $this->inventoryService->buildItemLabel($kind, $code, $category, $teamCode ?: null),
            'available' => $count,
            'pallet_limit' => $pallet,
            'nominal' => $nominal,
            'max_price' => $maxPrice,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function formatListing(array $row): array
    {
        $expiresAt = $row['UF_EXPIRES_AT'] ?? null;
        $expiresLabel = $expiresAt instanceof DateTime ? $expiresAt->format('d.m.Y H:i') : '';
        $kind = (string)($row['UF_ITEM_KIND'] ?? '');
        $code = (string)($row['UF_ITEM_CODE'] ?? '');
        $category = (string)($row['UF_ITEM_CATEGORY'] ?? '');
        $teamCode = (string)($row['UF_TEAM_CODE'] ?? '');

        return [
            'id' => (int)($row['ID'] ?? 0),
            'seller_id' => (int)($row['UF_SELLER_ID'] ?? 0),
            'kind' => $kind,
            'code' => $code,
            'category' => $category,
            'event_id' => (int)($row['UF_EVENT_ID'] ?? 0),
            'team_code' => $teamCode,
            'label' => $this->inventoryService->buildItemLabel($kind, $code, $category, $teamCode ?: null),
            'qty_total' => (int)($row['UF_QTY_TOTAL'] ?? 0),
            'qty_remaining' => (int)($row['UF_QTY_REMAINING'] ?? 0),
            'price_per_unit' => (float)($row['UF_PRICE_PER_UNIT'] ?? 0),
            'nominal' => (float)($row['UF_NOMINAL_SNAPSHOT'] ?? 0),
            'status' => (string)($row['UF_STATUS'] ?? ''),
            'expires_at' => $expiresLabel,
        ];
    }

    private function resolveMaxListings(int $userId): int
    {
        return $this->hasActivePremium($userId)
            ? ExchangeConfig::MAX_LISTINGS_PREMIUM
            : ExchangeConfig::MAX_LISTINGS_DEFAULT;
    }

    private function resolveListingDays(int $userId): int
    {
        return $this->hasActivePremium($userId)
            ? ExchangeConfig::LISTING_DAYS_PREMIUM
            : ExchangeConfig::LISTING_DAYS_DEFAULT;
    }

    private function hasActivePremium(int $userId): bool
    {
        return false;
    }
}
