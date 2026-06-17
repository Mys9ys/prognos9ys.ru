<?php

namespace Prognos9ys\Main\Service\Game;

use Bitrix\Main\UserTable;
use Prognos9ys\Main\Model\Repository\GameEconomyRepository;

class WealthRatingService
{
    private GameEconomyRepository $repository;

    public function __construct(?GameEconomyRepository $repository = null)
    {
        $this->repository = $repository ?? new GameEconomyRepository();
    }

    public function getRating(int $limit = 30, string $wealthSort = 'rich'): array
    {
        $limit = max(1, min(100, $limit));
        $wealthSort = in_array($wealthSort, [
            'rich',
            'poor',
            'pending_xp',
            'treasure_rich',
            'treasure_poor',
        ], true) ? $wealthSort : 'rich';

        if ($wealthSort === 'pending_xp') {
            return $this->getPendingXpRating($limit);
        }

        if ($wealthSort === 'treasure_rich' || $wealthSort === 'treasure_poor') {
            return $this->getTreasureRating($limit, $wealthSort === 'treasure_poor');
        }

        return $this->getWealthRating($limit, $wealthSort === 'poor');
    }

    private function getWealthRating(int $limit, bool $poorestFirst): array
    {
        $wallets = $this->repository->getAllWallets();
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

        $prepared = array_slice($prepared, 0, $limit);
        $users = $this->loadUsers(array_column($prepared, 'user_id'));

        $ratings = [];
        $place = 0;
        $prevTotal = null;

        foreach ($prepared as $index => $row) {
            if ($prevTotal === null || $row['total'] !== $prevTotal) {
                $place = $index + 1;
            }

            $userId = $row['user_id'];
            $ratings[] = [
                'place' => $place,
                'user' => $this->resolveUser($users, $userId),
                'prognobaks' => $row['prognobaks'],
                'rublius' => $row['rublius'],
                'total' => $row['total'],
                'score' => $row['total'],
                'pending_count' => 0,
                'pending_points' => 0.0,
            ];

            $prevTotal = $row['total'];
        }

        return [
            'status' => 'ok',
            'wealth_sort' => $poorestFirst ? 'poor' : 'rich',
            'ratings' => $ratings,
        ];
    }

    private function getPendingXpRating(int $limit): array
    {
        $aggregates = $this->repository->getPendingXpAggregatesByUser();
        $walletMap = [];

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

        $prepared = array_slice($prepared, 0, $limit);
        $users = $this->loadUsers(array_column($prepared, 'user_id'));

        $ratings = [];
        $place = 0;
        $prevPoints = null;

        foreach ($prepared as $index => $row) {
            if ($prevPoints === null || $row['pending_points'] !== $prevPoints) {
                $place = $index + 1;
            }

            $userId = $row['user_id'];
            $ratings[] = [
                'place' => $place,
                'user' => $this->resolveUser($users, $userId),
                'prognobaks' => $row['prognobaks'],
                'rublius' => $row['rublius'],
                'total' => $row['total'],
                'score' => $row['pending_points'],
                'pending_count' => $row['pending_count'],
                'pending_points' => $row['pending_points'],
            ];

            $prevPoints = $row['pending_points'];
        }

        return [
            'status' => 'ok',
            'wealth_sort' => 'pending_xp',
            'ratings' => $ratings,
        ];
    }

    private function getTreasureRating(int $limit, bool $poorestFirst): array
    {
        $wallets = $this->repository->getAllWallets();
        if (!$wallets) {
            return [
                'status' => 'ok',
                'wealth_sort' => $poorestFirst ? 'treasure_poor' : 'treasure_rich',
                'ratings' => [],
            ];
        }

        $treasureMap = $this->repository->getClosedTreasureChestTotalsMapForAllUsers();

        $prepared = [];
        foreach ($wallets as $wallet) {
            $userId = (int)($wallet['user_id'] ?? 0);
            if ($userId <= 0) {
                continue;
            }

            $treasureTotal = (int)($treasureMap[$userId] ?? 0);
            if (!$poorestFirst && $treasureTotal <= 0) {
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

        usort($prepared, static function (array $a, array $b) use ($poorestFirst): int {
            if ($a['treasure_total'] === $b['treasure_total']) {
                // стабильная сортировка
                return $a['user_id'] <=> $b['user_id'];
            }

            return $poorestFirst
                ? ($a['treasure_total'] <=> $b['treasure_total'])
                : ($b['treasure_total'] <=> $a['treasure_total']);
        });

        $prepared = array_slice($prepared, 0, $limit);
        $users = $this->loadUsers(array_column($prepared, 'user_id'));

        $ratings = [];
        $place = 0;
        $prevTreasure = null;
        foreach ($prepared as $index => $row) {
            if ($prevTreasure === null || $row['treasure_total'] !== $prevTreasure) {
                $place = $index + 1;
            }

            $userId = (int)$row['user_id'];
            $ratings[] = [
                'place' => $place,
                'user' => $this->resolveUser($users, $userId),
                'prognobaks' => $row['prognobaks'],
                'rublius' => $row['rublius'],
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
            'wealth_sort' => $poorestFirst ? 'treasure_poor' : 'treasure_rich',
            'ratings' => $ratings,
        ];
    }

    private function calcTotalWealth(float $prognobaks, float $rublius): float
    {
        return round(
            $prognobaks + $rublius * GameEconomyConfig::RUBLIUS_TO_PROGNOBAKS,
            1
        );
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
