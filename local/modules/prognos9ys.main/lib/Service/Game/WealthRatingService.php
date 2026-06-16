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

    public function getRating(int $limit = 30): array
    {
        $limit = max(1, min(100, $limit));
        $wallets = $this->repository->getAllWallets();

        $prepared = [];
        foreach ($wallets as $wallet) {
            $total = $this->calcTotalWealth($wallet['prognobaks'], $wallet['rublius']);
            if ($total <= 0) {
                continue;
            }

            $prepared[] = [
                'user_id' => $wallet['user_id'],
                'prognobaks' => $wallet['prognobaks'],
                'rublius' => $wallet['rublius'],
                'total' => $total,
            ];
        }

        usort($prepared, static function (array $a, array $b): int {
            if ($a['total'] === $b['total']) {
                return $b['user_id'] <=> $a['user_id'];
            }

            return $b['total'] <=> $a['total'];
        });

        $prepared = array_slice($prepared, 0, $limit);
        $users = $this->loadUsers(array_column($prepared, 'user_id'));

        $ratings = [];
        $place = 0;
        $prevTotal = null;

        foreach ($prepared as $index => $row) {
            if ($prevTotal === null || $row['total'] < $prevTotal) {
                $place = $index + 1;
            }

            $userId = $row['user_id'];
            $ratings[] = [
                'place' => $place,
                'user' => $users[$userId] ?? [
                    'id' => $userId,
                    'name' => 'Игрок #' . $userId,
                    'img' => null,
                ],
                'prognobaks' => $row['prognobaks'],
                'rublius' => $row['rublius'],
                'total' => $row['total'],
                'score' => $row['total'],
            ];

            $prevTotal = $row['total'];
        }

        return [
            'status' => 'ok',
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
