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
    private BankConsignmentService $consignmentService;

    public function __construct(?GameEconomyRepository $repository = null)
    {
        $this->repository = $repository ?? new GameEconomyRepository();
        $this->inventoryService = new ExchangeInventoryService($this->repository);
        $this->walletService = new WalletService($this->repository);
        $this->treasuryService = new TreasuryService($this->repository);
        $this->consignmentService = new BankConsignmentService($this->repository);
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
            'consignment_payout_percent' => BankConsignmentConfig::INSTANT_PAYOUT_PERCENT,
            'catalog_tabs' => ExchangeCatalogConfig::getTabs(),
            'sellable' => $this->buildSellableCatalog($userId),
        ];
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, pagination: array<string, int|bool>}
     */
    public function getCatalog(int $offset = 0, int $limit = 25, string $catalogTab = ''): array
    {
        $listings = $this->repository->getActiveExchangeListings(2000, $catalogTab);
        $groups = [];

        foreach ($listings as $row) {
            $formatted = $this->formatListing($row);
            if (!ExchangeCatalogConfig::matchesTab(
                $catalogTab,
                (string)($formatted['kind'] ?? ''),
                (string)($formatted['category'] ?? ''),
                (string)($formatted['code'] ?? '')
            )) {
                continue;
            }
            $price = round((float)($formatted['price_per_unit'] ?? 0), 1);
            $catalogCode = (string)($formatted['catalog_code'] ?? $formatted['code'] ?? '');
            $kind = (string)($formatted['kind'] ?? '');
            $category = (string)($formatted['category'] ?? '');
            $groupEventId = $this->resolveCatalogGroupEventId(
                $kind,
                $category,
                (int)($formatted['event_id'] ?? 0)
            );
            $groupKey = implode('|', [
                $kind,
                $catalogCode,
                $category,
                $groupEventId,
                (string)($formatted['team_code'] ?? ''),
                (string)$price,
            ]);

            if (!isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'group_key' => $groupKey,
                    'kind' => $kind,
                    'code' => $catalogCode,
                    'category' => $category,
                    'event_id' => $groupEventId,
                    'team_code' => (string)($formatted['team_code'] ?? ''),
                    'label' => (string)($formatted['label'] ?? ''),
                    'catalog_tab' => ExchangeCatalogConfig::resolveTab(
                        (string)($formatted['kind'] ?? ''),
                        (string)($formatted['category'] ?? ''),
                        (string)($formatted['code'] ?? '')
                    ),
                    'price_per_unit' => $price,
                    'qty_total' => 0,
                    'listings_count' => 0,
                    'has_consignment' => false,
                    'offers' => [],
                ];
            }

            $qty = (int)($formatted['qty_remaining'] ?? 0);
            $groups[$groupKey]['qty_total'] += $qty;
            $groups[$groupKey]['listings_count']++;
            if (!empty($formatted['is_consignment'])) {
                $groups[$groupKey]['has_consignment'] = true;
            }

            $groups[$groupKey]['offers'][] = [
                'listing_id' => (int)($formatted['id'] ?? 0),
                'seller_id' => (int)($formatted['seller_id'] ?? 0),
                'seller_name' => (string)($formatted['seller_name'] ?? ''),
                'seller_bank_id' => (int)($formatted['seller_bank_id'] ?? 0),
                'seller_bank_name' => (string)($formatted['seller_bank_name'] ?? ''),
                'is_consignment' => !empty($formatted['is_consignment']),
                'qty_remaining' => $qty,
                'expires_at' => (string)($formatted['expires_at'] ?? ''),
            ];
        }

        $items = array_values($groups);
        usort($items, static function (array $a, array $b): int {
            $priceCmp = ($a['price_per_unit'] ?? 0) <=> ($b['price_per_unit'] ?? 0);
            if ($priceCmp !== 0) {
                return $priceCmp;
            }

            return strcmp((string)($a['label'] ?? ''), (string)($b['label'] ?? ''));
        });

        $limit = max(1, min($limit, 50));
        $offset = max(0, $offset);
        $total = count($items);
        $pageItems = array_slice($items, $offset, $limit);

        return [
            'items' => $pageItems,
            'pagination' => [
                'offset' => $offset,
                'limit' => $limit,
                'total' => $total,
                'has_more' => ($offset + count($pageItems)) < $total,
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
        $code = $this->normalizeListingCode($kind, $code);

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
        if ($this->consignmentService->isConsignmentListing($row)) {
            throw new \RuntimeException('Комиссионный лот нельзя снять');
        }

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
        if ($remaining > 0 && !$this->consignmentService->isConsignmentListing($row)) {
            $this->returnListingToSeller($row);
        }

        $now = new DateTime();
        if ($this->consignmentService->isConsignmentListing($row)) {
            $consignmentId = (int)($row['UF_CONSIGNMENT_ID'] ?? 0);
            if ($consignmentId > 0) {
                $this->repository->updateBankConsignment($consignmentId, [
                    'UF_STATUS' => BankConsignmentConfig::STATUS_CANCELLED,
                    'UF_UPDATED_AT' => $now,
                ]);
            }
        }

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
        string $teamCode = '',
        float $pricePerUnit = 0
    ): array {
        if ($buyerId <= 0 || $qty <= 0) {
            throw new \InvalidArgumentException('Некорректная покупка');
        }

        $kind = trim($kind);
        $code = trim($code);
        $category = trim($category);
        $teamCode = trim($teamCode);
        $code = $this->normalizeListingCode($kind, $code);

        $lookupEventId = $this->resolveBuyLookupEventId($kind, $category, $eventId);
        $listings = $this->repository->findActiveExchangeListingsForSku(
            $kind,
            $code,
            $category,
            $lookupEventId,
            $teamCode
        );

        if ($pricePerUnit > 0) {
            $targetPrice = round($pricePerUnit, 1);
            $listings = array_values(array_filter(
                $listings,
                static fn(array $listing): bool => round((float)($listing['UF_PRICE_PER_UNIT'] ?? 0), 1) === $targetPrice
            ));
        }

        $chunks = [];
        $remaining = $qty;

        foreach ($listings as $listing) {
            if ($remaining <= 0) {
                break;
            }

            $sellerId = (int)($listing['UF_SELLER_ID'] ?? 0);
            $sellerBankId = (int)($listing['UF_SELLER_BANK_ID'] ?? 0);
            $isBankListing = $sellerBankId > 0;
            if (!$isBankListing && $sellerId === $buyerId) {
                continue;
            }

            $listingRemaining = (int)($listing['UF_QTY_REMAINING'] ?? 0);
            if ($listingRemaining <= 0) {
                continue;
            }

            $take = min($listingRemaining, $remaining);
            $price = round((float)($listing['UF_PRICE_PER_UNIT'] ?? 0), 1);
            $chunkTotal = round($price * $take, 1);
            $commission = $isBankListing
                ? 0.0
                : round($chunkTotal * ExchangeConfig::COMMISSION_PERCENT / 100, 1);

            $chunks[] = [
                'listing' => $listing,
                'listing_id' => (int)($listing['ID'] ?? 0),
                'seller_id' => $sellerId,
                'seller_bank_id' => $sellerBankId,
                'is_bank_listing' => $isBankListing,
                'consignment_id' => (int)($listing['UF_CONSIGNMENT_ID'] ?? 0),
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
            $sellerBankId = (int)($chunk['seller_bank_id'] ?? 0);
            $isBankListing = !empty($chunk['is_bank_listing']);
            $take = (int)$chunk['take'];

            if ($isBankListing && $sellerBankId > 0) {
                $this->repository->adjustUserBankLiquid($sellerBankId, (float)$chunk['seller_net']);
            } else {
                $this->walletService->credit(
                    $sellerId,
                    GameEconomyConfig::CURRENCY_PROGNOBAKS,
                    (float)$chunk['seller_net'],
                    'exchange_sell',
                    'exchange_listing',
                    $listingId
                );
            }

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
            $newStatus = $newRemaining <= 0 ? ExchangeConfig::STATUS_FILLED : ExchangeConfig::STATUS_ACTIVE;
            $this->repository->updateExchangeListing($listingId, [
                'UF_QTY_REMAINING' => $newRemaining,
                'UF_STATUS' => $newStatus,
                'UF_UPDATED_AT' => $now,
            ]);

            if ($newRemaining <= 0) {
                $consignmentId = (int)($chunk['consignment_id'] ?? 0);
                if ($consignmentId > 0) {
                    $this->consignmentService->markConsignmentSold($consignmentId);
                }
            }

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
     * @return array{chunks: array<int, array<string, mixed>>, total_paid: float}
     */
    public function consignToBank(
        int $userId,
        string $kind,
        string $code,
        int $qty,
        string $category = '',
        int $eventId = 0,
        string $teamCode = ''
    ): array {
        return $this->consignmentService->consign(
            $userId,
            $kind,
            $code,
            $qty,
            $category,
            $eventId,
            $teamCode
        );
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

            if ($this->consignmentService->isConsignmentListing($row)) {
                if ($this->consignmentService->relistExpiredConsignmentListing($row)) {
                    $count++;
                }
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

        $wc26Count = $this->inventoryService->getAvailableQty(
            $userId,
            ExchangeConfig::KIND_CHEST,
            ExchangeConfig::CHEST_CODE_WC26
        );
        if ($wc26Count > 0) {
            $nominal = ExchangeNominalConfig::getChestNominal(ExchangeConfig::CHEST_CODE_WC26);
            $items[] = $this->sellableRow(
                ExchangeConfig::KIND_CHEST,
                ExchangeConfig::CHEST_CODE_WC26,
                '',
                0,
                '',
                $wc26Count,
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
        foreach (ChestLootConfig::mergeInventoryLootStacks(array_merge(
            $this->repository->getLootItemStacksForUser($userId, ChestLootConfig::LOOT_EVENT_GLOBAL),
            $anchorEventId > 0 ? $this->repository->getLootItemStacksForUser($userId, $anchorEventId) : []
        )) as $loot) {
            $count = (int)($loot['count'] ?? 0);
            if ($count <= 0) {
                continue;
            }

            $code = (string)($loot['code'] ?? '');
            $category = (string)($loot['category'] ?? '');
            if ($category === ChestLootConfig::CATEGORY_ALBUM) {
                continue;
            }
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

        $albumCount = $this->inventoryService->getAvailableQty(
            $userId,
            ExchangeConfig::KIND_LOOT,
            AlbumConfig::ITEM_CODE,
            ChestLootConfig::CATEGORY_ALBUM
        );
        if ($albumCount > 0) {
            $nominal = ExchangeNominalConfig::getLootNominal(
                AlbumConfig::ITEM_CODE,
                ChestLootConfig::CATEGORY_ALBUM
            );
            $items[] = $this->sellableRow(
                ExchangeConfig::KIND_LOOT,
                AlbumConfig::ITEM_CODE,
                ChestLootConfig::CATEGORY_ALBUM,
                0,
                '',
                $albumCount,
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
        $consignPrice = $this->consignmentService->resolveConsignmentPrice(
            $kind,
            $code,
            $category,
            $eventId,
            $teamCode
        );

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
            'consign_price' => $consignPrice,
            'consign_instant_per_unit' => round(
                $consignPrice * BankConsignmentConfig::INSTANT_PAYOUT_PERCENT / 100,
                1
            ),
            'catalog_tab' => ExchangeCatalogConfig::resolveTab($kind, $category, $code),
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
        $sellerBankId = (int)($row['UF_SELLER_BANK_ID'] ?? 0);
        $sellerBankName = $this->resolveSellerBankName($sellerBankId);
        $catalogCode = $kind === ExchangeConfig::KIND_CHEST
            ? ExchangeConfig::normalizeChestExchangeCode($code)
            : $code;

        return [
            'id' => (int)($row['ID'] ?? 0),
            'seller_id' => (int)($row['UF_SELLER_ID'] ?? 0),
            'seller_name' => $this->resolveSellerDisplayName($row),
            'seller_bank_id' => $sellerBankId,
            'seller_bank_name' => $sellerBankName,
            'consignor_id' => (int)($row['UF_ORIGINAL_USER_ID'] ?? 0),
            'is_consignment' => $sellerBankId > 0,
            'kind' => $kind,
            'code' => $code,
            'catalog_code' => $catalogCode,
            'category' => $category,
            'event_id' => (int)($row['UF_EVENT_ID'] ?? 0),
            'team_code' => $teamCode,
            'label' => $this->inventoryService->buildItemLabel($kind, $catalogCode, $category, $teamCode ?: null),
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

    private function resolveSellerBankName(int $bankId): string
    {
        if ($bankId <= 0) {
            return '';
        }

        $bank = $this->repository->getUserBankById($bankId);
        if (!$bank) {
            return '';
        }

        return (new UserBankService($this->repository))->formatBankPublic($bank)['owner_name'] ?? '';
    }

    private function normalizeListingCode(string $kind, string $code): string
    {
        if ($kind === ExchangeConfig::KIND_CHEST) {
            return ExchangeConfig::normalizeChestExchangeCode($code);
        }

        return $code;
    }

    private function resolveCatalogGroupEventId(string $kind, string $category, int $eventId): int
    {
        if ($kind === ExchangeConfig::KIND_LOOT && ChestLootConfig::isEventAgnosticLootCategory($category)) {
            return ChestLootConfig::LOOT_EVENT_GLOBAL;
        }

        return $eventId;
    }

    private function resolveBuyLookupEventId(string $kind, string $category, int $eventId): int
    {
        if ($kind === ExchangeConfig::KIND_LOOT && ChestLootConfig::isEventAgnosticLootCategory($category)) {
            return -1;
        }

        return $eventId;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function resolveSellerDisplayName(array $row): string
    {
        $sellerBankId = (int)($row['UF_SELLER_BANK_ID'] ?? 0);
        if ($sellerBankId > 0) {
            $bankName = $this->resolveSellerBankName($sellerBankId);

            return $bankName !== '' ? ('банк ' . $bankName) : 'банк';
        }

        $sellerId = (int)($row['UF_SELLER_ID'] ?? 0);
        if ($sellerId <= 0) {
            return '';
        }

        $userRow = \Bitrix\Main\UserTable::getList([
            'filter' => ['=ID' => $sellerId],
            'select' => ['ID', 'LOGIN', 'NAME', 'LAST_NAME'],
            'limit' => 1,
        ])->fetch();

        if (!$userRow) {
            return 'user#' . $sellerId;
        }

        $name = trim(($userRow['NAME'] ?? '') . ' ' . ($userRow['LAST_NAME'] ?? ''));
        if ($name !== '') {
            return $name;
        }

        return (string)($userRow['LOGIN'] ?? ('user#' . $sellerId));
    }

    /**
     * План продажи лишних дублей вымпелов/шарфов ЧМ-26.
     *
     * @return array{items:array<int,array<string,mixed>>,total_qty:int}
     */
    public function getDuplicateSouvenirSellPlan(int $userId): array
    {
        if ($userId <= 0) {
            return ['items' => [], 'total_qty' => 0];
        }

        $gluedTeams = (new AlbumService())->getGluedTeamsByCollection($userId);
        $anchorEventId = (new GameEventScopeService())->getAnchorEventId();
        $stacks = ChestLootConfig::mergeInventoryLootStacks(array_merge(
            $this->repository->getLootItemStacksForUser($userId, ChestLootConfig::LOOT_EVENT_GLOBAL),
            $anchorEventId > 0 ? $this->repository->getLootItemStacksForUser($userId, $anchorEventId) : []
        ));

        $items = [];
        $totalQty = 0;

        foreach ($stacks as $stack) {
            $code = (string)($stack['code'] ?? '');
            $category = (string)($stack['category'] ?? '');
            $count = (int)($stack['count'] ?? 0);
            if ($count <= 0 || !AlbumConfig::isSupportedCollectible($code)) {
                continue;
            }

            $collection = (string)(AlbumConfig::collectionForItemCode($code) ?? '');
            $slug = (string)(Wc26CollectibleConfig::extractTeamSlugFromCollectibleCode($code) ?? '');
            if ($collection === '' || $slug === '') {
                continue;
            }

            $glued = in_array($slug, $gluedTeams[$collection] ?? [], true);
            $keep = $glued ? 0 : 1;
            $excess = $count - $keep;
            if ($excess <= 0) {
                continue;
            }

            $eventId = (int)($stack['event_id'] ?? $anchorEventId);
            $nominal = ExchangeNominalConfig::getLootNominal($code, $category);
            $items[] = [
                'kind' => ExchangeConfig::KIND_LOOT,
                'code' => $code,
                'category' => $category,
                'event_id' => $eventId,
                'team_code' => '',
                'label' => (string)($stack['label'] ?? $code),
                'qty' => $excess,
                'nominal' => $nominal,
                'max_price' => ExchangeNominalConfig::getMaxSellerPrice($nominal),
                'consign_price' => $this->consignmentService->resolveConsignmentPrice(
                    ExchangeConfig::KIND_LOOT,
                    $code,
                    $category,
                    $eventId,
                    ''
                ),
            ];
            $totalQty += $excess;
        }

        return ['items' => $items, 'total_qty' => $totalQty];
    }

    /**
     * @return array{mode:string,sold_qty:int,lines:array<int,array{text:string,status:string}>}
     */
    public function bulkSellDuplicateSouvenirs(int $userId, string $mode, float $pricePerUnit = 0): array
    {
        $mode = trim($mode);
        if (!in_array($mode, ['listing', 'consign'], true)) {
            throw new \InvalidArgumentException('Режим: listing или consign');
        }

        $plan = $this->getDuplicateSouvenirSellPlan($userId);
        $lines = [];
        $soldQty = 0;

        foreach ($plan['items'] as $item) {
            $qty = (int)($item['qty'] ?? 0);
            if ($qty <= 0) {
                continue;
            }

            $price = $pricePerUnit > 0
                ? round($pricePerUnit, 1)
                : round((float)($mode === 'consign' ? ($item['consign_price'] ?? $item['nominal']) : ($item['nominal'] ?? 0)), 1);

            try {
                if ($mode === 'consign') {
                    $this->consignToBank(
                        $userId,
                        (string)$item['kind'],
                        (string)$item['code'],
                        $qty,
                        (string)($item['category'] ?? ''),
                        (int)($item['event_id'] ?? 0),
                        (string)($item['team_code'] ?? '')
                    );
                } else {
                    $this->createListing(
                        $userId,
                        (string)$item['kind'],
                        (string)$item['code'],
                        $qty,
                        $price,
                        (string)($item['category'] ?? ''),
                        (int)($item['event_id'] ?? 0),
                        (string)($item['team_code'] ?? '')
                    );
                }

                $soldQty += $qty;
                $lines[] = [
                    'text' => ($item['label'] ?? $item['code']) . ' ×' . $qty,
                    'status' => 'ok',
                ];
            } catch (\Throwable $exception) {
                $lines[] = [
                    'text' => ($item['label'] ?? $item['code']) . ': ' . $exception->getMessage(),
                    'status' => 'fail',
                ];
            }
        }

        if ($soldQty <= 0 && !$lines) {
            $lines[] = ['text' => 'Нет лишних дублей для продажи', 'status' => 'fail'];
        }

        return [
            'mode' => $mode,
            'sold_qty' => $soldQty,
            'lines' => $lines,
        ];
    }
}
