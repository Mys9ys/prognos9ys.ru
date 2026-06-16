<?php

namespace Prognos9ys\Main\Service\Game;

use Bitrix\Main\UserTable;
use Prognos9ys\Main\Model\Repository\GameEconomyRepository;

class RegistrationBonusService
{
    public static function onUserRegistered(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        try {
            (new WalletService())->grantStarterPack($userId);
        } catch (\Throwable $exception) {
            // Кошелёк мог быть создан ранее — не блокируем регистрацию.
        }
    }

    /**
     * Массово выдать стартовый пакет существующим пользователям.
     * Метод идемпотентный: если кошелёк уже есть, повторно не начисляет.
     *
     * @return array{processed:int,granted:int,failed:int}
     */
    public static function grantForExistingUsers(int $batchSize = 500): array
    {
        $repository = new GameEconomyRepository();
        $walletService = new WalletService();
        $stats = [
            'processed' => 0,
            'granted' => 0,
            'failed' => 0,
        ];

        $lastId = 0;
        $batchSize = max(50, $batchSize);

        while (true) {
            $rows = UserTable::getList([
                'select' => ['ID'],
                'filter' => [
                    '>ID' => $lastId,
                    '=ACTIVE' => 'Y',
                ],
                'order' => ['ID' => 'ASC'],
                'limit' => $batchSize,
            ])->fetchAll();

            if (!$rows) {
                break;
            }

            foreach ($rows as $row) {
                $userId = (int)($row['ID'] ?? 0);
                $lastId = $userId;

                if ($userId <= 0) {
                    continue;
                }

                $stats['processed']++;

                try {
                    $hasWallet = $repository->getWalletByUserId($userId) !== null;
                    $walletService->grantStarterPack($userId);
                    if (!$hasWallet) {
                        $stats['granted']++;
                    }
                } catch (\Throwable $exception) {
                    $stats['failed']++;
                }
            }
        }

        return $stats;
    }
}
