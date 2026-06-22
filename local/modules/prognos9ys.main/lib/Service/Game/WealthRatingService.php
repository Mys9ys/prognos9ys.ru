<?php

namespace Prognos9ys\Main\Service\Game;

use Bitrix\Main\UserTable;
use Prognos9ys\Main\Model\Repository\GameEconomyRepository;

class WealthRatingService
{
    private GameEconomyRepository $repository;
    private LevelService $levelService;
    private TreasuryShopService $shopService;
    private GameEventScopeService $scopeService;

    public function __construct(
        ?GameEconomyRepository $repository = null,
        ?LevelService $levelService = null,
        ?TreasuryShopService $shopService = null,
        ?GameEventScopeService $scopeService = null
    ) {
        $this->repository = $repository ?? new GameEconomyRepository();
        $this->levelService = $levelService ?? new LevelService($this->repository);
        $this->shopService = $shopService ?? new TreasuryShopService($this->repository);
        $this->scopeService = $scopeService ?? new GameEventScopeService();
    }

    public function getRating(int $limit = 30, string $wealthSort = 'rich', int $offset = 0): array
    {
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);
        $wealthSort = in_array($wealthSort, [
            'rich',
            'poor',
            'pending_xp',
            'treasure_rich',
        ], true) ? $wealthSort : 'rich';

        if ($wealthSort === 'pending_xp') {
            return $this->getPendingXpRating($limit, $offset);
        }

        if ($wealthSort === 'treasure_rich') {
            return $this->getTreasureRating($limit, $offset);
        }

        return $this->getWealthRating($limit, $offset, $wealthSort === 'poor');
    }

    private function getWealthRating(int $limit, int $offset, bool $poorestFirst): array
    {
        $wallets = $this->repository->getAllWallets();
        $levelMap = $this->buildLevelMap();
        $pendingMap = $this->buildPendingMap();
        $prepared = [];

        foreach ($wallets as $wallet) {
            $total = $this->calcTotalWealth($wallet['prognobaks'], $wallet['rublius']);
            if (!$poorestFirst && $total <= 0) {
                continue;
            }

            $prepared[] = [
                'user_id' => $wallet['user_id'],
                'prognobaks' => $wallet['prognobaks'],
                'rublius' => $wallet['rublius'],
                'total' => $total,
            ];
        }

        usort($prepared, static function (array $a, array $b) use ($poorestFirst): int {
            if ($a['total'] === $b['total']) {
                return $poorestFirst
                    ? ($a['user_id'] <=> $b['user_id'])
                    : ($b['user_id'] <=> $a['user_id']);
            }

            return $poorestFirst
                ? ($a['total'] <=> $b['total'])
                : ($b['total'] <=> $a['total']);
        });

        $total = count($prepared);
        $users = $this->loadUsers(array_column($prepared, 'user_id'));

        $ratings = [];
        $place = 0;
        $prevTotal = null;

        foreach ($prepared as $index => $row) {
            if ($index < $offset) {
                if ($prevTotal === null || $row['total'] !== $prevTotal) {
                    $place = $index + 1;
                }
                $prevTotal = $row['total'];
                continue;
            }

            if ($index >= $offset + $limit) {
                break;
            }

            if ($prevTotal === null || $row['total'] !== $prevTotal) {
                $place = $index + 1;
            }

            $userId = $row['user_id'];
            $extras = $this->buildRowExtras($userId, $pendingMap);
            $ratings[] = array_merge([
                'place' => $place,
                'user' => $this->resolveUser($users, $userId),
                'prognobaks' => $row['prognobaks'],
                'rublius' => $row['rublius'],
                'level' => $levelMap[$userId] ?? 0,
                'total' => $row['total'],
                'score' => $row['total'],
            ], $extras);

            $prevTotal = $row['total'];
        }

        return [
            'status' => 'ok',
            'wealth_sort' => $poorestFirst ? 'poor' : 'rich',
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'ratings' => $ratings,
        ];
    }

    private function getPendingXpRating(int $limit, int $offset): array
    {
        $scope = new GameEventScopeService();
        $aggregates = $this->repository->getPendingXpAggregatesByUser(
            static fn(int $matchId): bool => $scope->isMatchEligible($matchId)
        );
        $walletMap = [];
        $levelMap = $this->buildLevelMap();

        foreach ($this->repository->getAllWallets() as $wallet) {
            $walletMap[$wallet['user_id']] = $wallet;
        }

        $prepared = [];
        foreach ($aggregates as $userId => $agg) {
            $wallet = $walletMap[$userId] ?? ['prognobaks' => 0.0, 'rublius' => 0.0];
            $prepared[] = [
                'user_id' => $userId,
                'prognobaks' => $wallet['prognobaks'],
                'rublius' => $wallet['rublius'],
                'total' => $this->calcTotalWealth($wallet['prognobaks'], $wallet['rublius']),
                'pending_count' => $agg['count'],
                'pending_points' => $agg['points'],
            ];
        }

        usort($prepared, static function (array $a, array $b): int {
            if ($a['pending_points'] === $b['pending_points']) {
                return $b['pending_count'] <=> $a['pending_count'];
            }

            return $b['pending_points'] <=> $a['pending_points'];
        });

        $total = count($prepared);
        $users = $this->loadUsers(array_column($prepared, 'user_id'));

        $ratings = [];
        $place = 0;
        $prevPoints = null;
        $pendingMap = $this->buildPendingMap();

        foreach ($prepared as $index => $row) {
            if ($index < $offset) {
                if ($prevPoints === null || $row['pending_points'] !== $prevPoints) {
                    $place = $index + 1;
                }
                $prevPoints = $row['pending_points'];
                continue;
            }

            if ($index >= $offset + $limit) {
                break;
            }

            if ($prevPoints === null || $row['pending_points'] !== $prevPoints) {
                $place = $index + 1;
            }

            $userId = $row['user_id'];
            $extras = $this->buildRowExtras($userId, $pendingMap);
            $ratings[] = array_merge([
                'place' => $place,
                'user' => $this->resolveUser($users, $userId),
                'prognobaks' => $row['prognobaks'],
                'rublius' => $row['rublius'],
                'level' => $levelMap[$userId] ?? 0,
                'total' => $row['total'],
                'score' => $row['pending_points'],
                'pending_count' => $row['pending_count'],
                'pending_points' => $row['pending_points'],
            ], [
                'shop_offers' => $extras['shop_offers'],
            ]);

            $prevPoints = $row['pending_points'];
        }

        return [
            'status' => 'ok',
            'wealth_sort' => 'pending_xp',
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'ratings' => $ratings,
        ];
    }

    private function getTreasureRating(int $limit, int $offset): array
    {
        $wallets = $this->repository->getAllWallets();
        if (!$wallets) {
            return [
                'status' => 'ok',
                'wealth_sort' => 'treasure_rich',
                'total' => 0,
                'limit' => $limit,
                'offset' => $offset,
                'ratings' => [],
            ];
        }

        $treasureMap = $this->repository->getClosedTreasureChestTotalsMapForAllUsers();
        $levelMap = $this->buildLevelMap();

        $prepared = [];
        foreach ($wallets as $wallet) {
            $userId = (int)($wallet['user_id'] ?? 0);
            if ($userId <= 0) {
                continue;
            }

            $treasureTotal = (int)($treasureMap[$userId] ?? 0);
            if ($treasureTotal <= 0) {
                continue;
            }

            $totalWealth = $this->calcTotalWealth($wallet['prognobaks'], $wallet['rublius']);
            $prepared[] = [
                'user_id' => $userId,
                'prognobaks' => $wallet['prognobaks'],
                'rublius' => $wallet['rublius'],
                'total' => $totalWealth,
                'treasure_total' => $treasureTotal,
            ];
        }

        usort($prepared, static function (array $a, array $b): int {
            if ($a['treasure_total'] === $b['treasure_total']) {
                // стабильная сортировка
                return $a['user_id'] <=> $b['user_id'];
            }

            return $b['treasure_total'] <=> $a['treasure_total'];
        });

        $total = count($prepared);
        $users = $this->loadUsers(array_column($prepared, 'user_id'));

        $ratings = [];
        $place = 0;
        $prevTreasure = null;
        foreach ($prepared as $index => $row) {
            if ($index < $offset) {
                if ($prevTreasure === null || $row['treasure_total'] !== $prevTreasure) {
                    $place = $index + 1;
                }
                $prevTreasure = $row['treasure_total'];
                continue;
            }

            if ($index >= $offset + $limit) {
                break;
            }

            if ($prevTreasure === null || $row['treasure_total'] !== $prevTreasure) {
                $place = $index + 1;
            }

            $userId = (int)$row['user_id'];
            $ratings[] = [
                'place' => $place,
                'user' => $this->resolveUser($users, $userId),
                'prognobaks' => $row['prognobaks'],
                'rublius' => $row['rublius'],
                'level' => $levelMap[$userId] ?? 0,
                'total' => $row['total'],
                'treasure_total' => $row['treasure_total'],
                'score' => $row['treasure_total'],
                'pending_count' => 0,
                'pending_points' => 0.0,
            ];

            $prevTreasure = $row['treasure_total'];
        }

        return [
            'status' => 'ok',
            'wealth_sort' => 'treasure_rich',
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'ratings' => $ratings,
        ];
    }

    private function calcTotalWealth(float $prognobaks, float $rublius): float
    {
        return round($prognobaks, 1);
    }

    /**
     * @return array<int, array{count:int,points:float}>
     */
    private function buildPendingMap(): array
    {
        return $this->repository->getPendingXpAggregatesByUser(
            fn(int $matchId): bool => $this->scopeService->isMatchEligible($matchId)
        );
    }

    /**
     * @param array<int, array{count:int,points:float}> $pendingMap
     * @return array{pending_count:int,pending_points:float,shop_offers:array<string,mixed>}
     */
    private function buildRowExtras(int $userId, array $pendingMap): array
    {
        $pending = $pendingMap[$userId] ?? ['count' => 0, 'points' => 0.0];

        return [
            'pending_count' => (int)($pending['count'] ?? 0),
            'pending_points' => round((float)($pending['points'] ?? 0), 1),
            'shop_offers' => $this->shopService->getCompactRowOffers($userId),
        ];
    }

    /**
     * @return array<int, int>
     */
    private function buildLevelMap(): array
    {
        $map = [];
        foreach ($this->repository->getAllUserXpMap() as $userId => $xp) {
            $map[$userId] = $this->levelService->getLevelFromXp($xp);
        }

        return $map;
    }

    /**
     * @param array<int, array{id:int,name:string,img:?string}> $users
     * @return array{id:int,name:string,img:?string}
     */
    private function resolveUser(array $users, int $userId): array
    {
        return $users[$userId] ?? [
            'id' => $userId,
            'name' => 'Игрок #' . $userId,
            'img' => null,
        ];
    }

    /**
     * @param int[] $userIds
     * @return array<int, array{id:int,name:string,img:?string}>
     */
    private function loadUsers(array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if (!$userIds) {
            return [];
        }

        $users = [];
        $response = UserTable::getList([
            'filter' => ['@ID' => $userIds],
            'select' => ['ID', 'NAME', 'PERSONAL_PHOTO'],
        ]);

        while ($row = $response->fetch()) {
            $id = (int)$row['ID'];
            $users[$id] = [
                'id' => $id,
                'name' => $row['NAME'] ?: ('Игрок #' . $id),
                'img' => $row['PERSONAL_PHOTO'] ? \CFile::GetPath($row['PERSONAL_PHOTO']) : null,
            ];
        }

        return $users;
    }
}
