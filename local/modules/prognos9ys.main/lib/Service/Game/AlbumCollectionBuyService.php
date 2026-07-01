<?php

namespace Prognos9ys\Main\Service\Game;

use Prognos9ys\Main\Model\Repository\GameEconomyRepository;

/**
 * Докупка недостающих вымпелов/шарфов с биржи и вклейка до тира мега-ачивки.
 */
class AlbumCollectionBuyService
{
    private AlbumService $albumService;
    private ExchangeService $exchangeService;
    private GameEconomyRepository $economyRepository;
    private WalletService $walletService;
    private GameEventScopeService $scopeService;

    public function __construct(
        ?AlbumService $albumService = null,
        ?ExchangeService $exchangeService = null,
        ?GameEconomyRepository $economyRepository = null,
        ?WalletService $walletService = null,
        ?GameEventScopeService $scopeService = null
    ) {
        $this->economyRepository = $economyRepository ?? new GameEconomyRepository();
        $this->albumService = $albumService ?? new AlbumService();
        $this->exchangeService = $exchangeService ?? new ExchangeService($this->economyRepository);
        $this->walletService = $walletService ?? new WalletService($this->economyRepository);
        $this->scopeService = $scopeService ?? new GameEventScopeService();
    }

    /**
     * @return array{
     *   glued_from_inventory:int,
     *   bought:int,
     *   spent:float,
     *   glued_total:int,
     *   target_tier:int,
     *   lines:array<int, array{text:string,status:string}>
     * }
     */
    public function buyMissingToTier(int $userId, string $collection, int $targetTier): array
    {
        if ($userId <= 0) {
            throw new \InvalidArgumentException('Некорректный пользователь');
        }

        $collection = trim($collection);
        if (!in_array($collection, [AlbumConfig::COLLECTION_PENNANT_WC26, AlbumConfig::COLLECTION_SCARF_WC26], true)) {
            throw new \InvalidArgumentException('Неизвестная коллекция');
        }

        if (!in_array($targetTier, AlbumConfig::MEGA_THRESHOLDS, true)) {
            throw new \InvalidArgumentException('Цель должна быть 16, 32 или 48');
        }

        $albumId = $this->albumService->resolveAlbumIdForCollection($userId, $collection);
        if ($albumId <= 0) {
            throw new \RuntimeException('Сначала активируйте альбом для этой коллекции');
        }

        $glued = $this->countGluedInCollection($userId, $collection);
        if ($glued >= $targetTier) {
            return [
                'glued_from_inventory' => 0,
                'bought' => 0,
                'spent' => 0.0,
                'glued_total' => $glued,
                'target_tier' => $targetTier,
                'lines' => [
                    [
                        'text' => 'Уже ' . $glued . ' вклеено — цель ' . $targetTier . ' достигнута',
                        'status' => 'ok',
                    ],
                ],
            ];
        }

        $preferFatLots = $targetTier < AlbumConfig::SLOT_COUNT;
        $listingSort = $preferFatLots ? 'fat_lots' : 'price';

        $gluedFromInventory = 0;
        $bought = 0;
        $spent = 0.0;
        $lines = [];
        $guard = AlbumConfig::SLOT_COUNT;

        while ($glued < $targetTier && $guard-- > 0) {
            $inventoryItem = $this->findInventoryItemForMissingSlug($userId, $collection);
            if ($inventoryItem !== null) {
                $glueResult = $this->albumService->glue($userId, $albumId, $inventoryItem);
                $glued++;
                $gluedFromInventory++;
                foreach ($glueResult['lines'] ?? [] as $line) {
                    $lines[] = $line;
                }
                continue;
            }

            $candidates = $this->buildBuyCandidates($userId, $collection);
            if (!$candidates) {
                $lines[] = [
                    'text' => 'На бирже нет лотов для недостающих сборных',
                    'status' => 'skip',
                ];
                break;
            }

            $this->sortBuyCandidates($candidates, $preferFatLots);

            $wallet = round((float)($this->walletService->getWalletSummary($userId)['prognobaks'] ?? 0), 1);
            $picked = null;
            foreach ($candidates as $candidate) {
                if ($wallet >= (float)$candidate['price']) {
                    $picked = $candidate;
                    break;
                }
            }

            if ($picked === null) {
                $lines[] = [
                    'text' => 'Недостаточно 🪙 для следующей покупки (нужно от '
                        . round((float)$candidates[0]['price'], 1) . ')',
                    'status' => 'skip',
                ];
                break;
            }

            try {
                $buyResult = $this->exchangeService->buy(
                    $userId,
                    ExchangeConfig::KIND_LOOT,
                    (string)$picked['code'],
                    1,
                    (string)$picked['category'],
                    (int)$picked['event_id'],
                    '',
                    0,
                    $listingSort
                );
                $tradeCost = 0.0;
                foreach ((array)($buyResult['trades'] ?? []) as $trade) {
                    $tradeCost = round($tradeCost + (float)($trade['total'] ?? 0), 1);
                }
                $spent = round($spent + $tradeCost, 1);
                $bought++;

                $glueResult = $this->albumService->glue($userId, $albumId, (string)$picked['code']);
                $glued++;
                $lines[] = [
                    'text' => 'Куплено и вклеено: ' . (string)$picked['label']
                        . ' (−' . $tradeCost . ' 🪙)',
                    'status' => 'ok',
                ];
                foreach ($glueResult['lines'] ?? [] as $line) {
                    if (($line['status'] ?? '') === 'ok') {
                        continue;
                    }
                    $lines[] = $line;
                }
            } catch (\Throwable $e) {
                $lines[] = [
                    'text' => (string)$picked['label'] . ': ' . $e->getMessage(),
                    'status' => 'skip',
                ];
                break;
            }
        }

        if (!$lines) {
            $lines[] = [
                'text' => 'Нечего докупать',
                'status' => 'skip',
            ];
        }

        return [
            'glued_from_inventory' => $gluedFromInventory,
            'bought' => $bought,
            'spent' => $spent,
            'glued_total' => $this->countGluedInCollection($userId, $collection),
            'target_tier' => $targetTier,
            'lines' => $lines,
        ];
    }

    private function countGluedInCollection(int $userId, string $collection): int
    {
        $meta = $this->albumService->getProfileMeta($userId);
        $slugs = (array)($meta['glued_teams'][$collection] ?? []);

        return count($slugs);
    }

    /**
     * @return string[]
     */
    private function getMissingSlugs(int $userId, string $collection): array
    {
        $meta = $this->albumService->getProfileMeta($userId);
        $glued = array_fill_keys((array)($meta['glued_teams'][$collection] ?? []), true);
        $missing = [];

        foreach (array_keys(Wc26CollectibleConfig::teamSlugs()) as $slug) {
            if (!isset($glued[$slug])) {
                $missing[] = $slug;
            }
        }

        return $missing;
    }

    private function findInventoryItemForMissingSlug(int $userId, string $collection): ?string
    {
        $category = $this->categoryForCollection($collection);
        foreach ($this->getMissingSlugs($userId, $collection) as $slug) {
            $code = $this->itemCodeForSlug($collection, $slug);
            $eventId = $this->economyRepository->findLootStackEventId($userId, $code, $category);
            if ($eventId === null) {
                continue;
            }
            if ($this->economyRepository->getLootItemCount($userId, $eventId, $code, $category) <= 0) {
                continue;
            }

            return $code;
        }

        return null;
    }

    /**
     * @return array<int, array{
     *   slug:string,
     *   code:string,
     *   category:string,
     *   event_id:int,
     *   price:float,
     *   max_lot_qty:int,
     *   label:string
     * }>
     */
    private function buildBuyCandidates(int $userId, string $collection): array
    {
        $category = $this->categoryForCollection($collection);
        $eventId = $this->scopeService->getAnchorEventId();
        $candidates = [];

        foreach ($this->getMissingSlugs($userId, $collection) as $slug) {
            $code = $this->itemCodeForSlug($collection, $slug);
            $listings = $this->economyRepository->findActiveExchangeListingsForSku(
                ExchangeConfig::KIND_LOOT,
                $code,
                $category,
                $eventId,
                ''
            );

            $bestPrice = null;
            $maxLotQty = 0;
            foreach ($listings as $listing) {
                $sellerId = (int)($listing['UF_SELLER_ID'] ?? 0);
                $sellerBankId = (int)($listing['UF_SELLER_BANK_ID'] ?? 0);
                if ($sellerBankId <= 0 && $sellerId === $userId) {
                    continue;
                }

                $qty = (int)($listing['UF_QTY_REMAINING'] ?? 0);
                if ($qty <= 0) {
                    continue;
                }

                $price = round((float)($listing['UF_PRICE_PER_UNIT'] ?? 0), 1);
                if ($bestPrice === null || $price < $bestPrice) {
                    $bestPrice = $price;
                }
                $maxLotQty = max($maxLotQty, $qty);
            }

            if ($bestPrice === null) {
                continue;
            }

            $candidates[] = [
                'slug' => $slug,
                'code' => $code,
                'category' => $category,
                'event_id' => $eventId,
                'price' => $bestPrice,
                'max_lot_qty' => $maxLotQty,
                'label' => $collection === AlbumConfig::COLLECTION_PENNANT_WC26
                    ? Wc26CollectibleConfig::getPennantLabel($code)
                    : Wc26CollectibleConfig::getScarfLabel($code),
            ];
        }

        return $candidates;
    }

    /**
     * @param array<int, array{price:float,max_lot_qty:int}> $candidates
     */
    private function sortBuyCandidates(array &$candidates, bool $preferFatLots): void
    {
        usort($candidates, static function (array $a, array $b) use ($preferFatLots): int {
            if ($preferFatLots) {
                $qtyCmp = ((int)$b['max_lot_qty']) <=> ((int)$a['max_lot_qty']);
                if ($qtyCmp !== 0) {
                    return $qtyCmp;
                }
            }

            $priceCmp = ((float)$a['price']) <=> ((float)$b['price']);
            if ($priceCmp !== 0) {
                return $priceCmp;
            }

            if ($preferFatLots) {
                return 0;
            }

            return ((int)$b['max_lot_qty']) <=> ((int)$a['max_lot_qty']);
        });
    }

    private function categoryForCollection(string $collection): string
    {
        if ($collection === AlbumConfig::COLLECTION_PENNANT_WC26) {
            return ChestLootConfig::CATEGORY_PENNANT;
        }

        return ChestLootConfig::CATEGORY_SCARF;
    }

    private function itemCodeForSlug(string $collection, string $slug): string
    {
        if ($collection === AlbumConfig::COLLECTION_PENNANT_WC26) {
            return Wc26CollectibleConfig::pennantCode($slug);
        }

        return Wc26CollectibleConfig::scarfCode($slug);
    }
}
