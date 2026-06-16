<?php

namespace Prognos9ys\Main\Model\Repository;

use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\Loader;
use Prognos9ys\Main\Service\Game\GameEconomyHlInstaller;

class GameEconomyRepository
{
    private ?string $walletDataClass = null;
    private ?string $walletTxDataClass = null;
    private ?string $levelTierDataClass = null;
    private ?string $userProgressDataClass = null;
    private ?string $pendingXpDataClass = null;
    private ?string $gameBankDataClass = null;
    private ?string $matchBetDataClass = null;
    private ?string $treasureChestDataClass = null;

    public function getWalletDataClass(): string
    {
        return $this->walletDataClass ??= $this->compileDataClass(GameEconomyHlInstaller::TABLE_WALLET);
    }

    public function getWalletTxDataClass(): string
    {
        return $this->walletTxDataClass ??= $this->compileDataClass(GameEconomyHlInstaller::TABLE_WALLET_TX);
    }

    public function getLevelTierDataClass(): string
    {
        return $this->levelTierDataClass ??= $this->compileDataClass(GameEconomyHlInstaller::TABLE_LEVEL_TIER);
    }

    public function getUserProgressDataClass(): string
    {
        return $this->userProgressDataClass ??= $this->compileDataClass(GameEconomyHlInstaller::TABLE_USER_PROGRESS);
    }

    public function getPendingXpDataClass(): string
    {
        return $this->pendingXpDataClass ??= $this->compileDataClass(GameEconomyHlInstaller::TABLE_PENDING_XP);
    }

    public function getGameBankDataClass(): string
    {
        return $this->gameBankDataClass ??= $this->compileDataClass(GameEconomyHlInstaller::TABLE_GAME_BANK);
    }

    public function getMatchBetDataClass(): string
    {
        return $this->matchBetDataClass ??= $this->compileDataClass(GameEconomyHlInstaller::TABLE_MATCH_BET);
    }

    public function getTreasureChestDataClass(): string
    {
        return $this->treasureChestDataClass ??= $this->compileDataClass(GameEconomyHlInstaller::TABLE_TREASURE_CHEST);
    }

    public function getWalletByUserId(int $userId): ?array
    {
        $dataClass = $this->getWalletDataClass();
        $row = $dataClass::getList([
            'filter' => ['=UF_USER_ID' => $userId],
            'limit' => 1,
        ])->fetch();

        return $row ?: null;
    }

    public function addWallet(array $fields): int
    {
        $dataClass = $this->getWalletDataClass();
        $result = $dataClass::add($fields);

        if (!$result->isSuccess()) {
            throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
        }

        return (int)$result->getId();
    }

    public function updateWallet(int $id, array $fields): void
    {
        $dataClass = $this->getWalletDataClass();
        $result = $dataClass::update($id, $fields);

        if (!$result->isSuccess()) {
            throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
        }
    }

    public function addWalletTx(array $fields): int
    {
        $dataClass = $this->getWalletTxDataClass();
        $result = $dataClass::add($fields);

        if (!$result->isSuccess()) {
            throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
        }

        return (int)$result->getId();
    }

    public function hasWalletTx(int $userId, string $reason, ?string $refType = null, ?int $refId = null): bool
    {
        $filter = [
            '=UF_USER_ID' => $userId,
            '=UF_REASON' => $reason,
        ];

        if ($refType !== null) {
            $filter['=UF_REF_TYPE'] = $refType;
        }

        if ($refId !== null) {
            $filter['=UF_REF_ID'] = $refId;
        }

        $dataClass = $this->getWalletTxDataClass();
        $row = $dataClass::getList([
            'filter' => $filter,
            'limit' => 1,
            'select' => ['ID'],
        ])->fetch();

        return (bool)$row;
    }

    public function getProgressByUserId(int $userId): ?array
    {
        $dataClass = $this->getUserProgressDataClass();
        $row = $dataClass::getList([
            'filter' => ['=UF_USER_ID' => $userId],
            'limit' => 1,
        ])->fetch();

        return $row ?: null;
    }

    public function addProgress(array $fields): int
    {
        $dataClass = $this->getUserProgressDataClass();
        $result = $dataClass::add($fields);

        if (!$result->isSuccess()) {
            throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
        }

        return (int)$result->getId();
    }

    public function updateProgress(int $id, array $fields): void
    {
        $dataClass = $this->getUserProgressDataClass();
        $result = $dataClass::update($id, $fields);

        if (!$result->isSuccess()) {
            throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
        }
    }

    /**
     * @return array<int, array>
     */
    public function getLevelTiers(): array
    {
        $dataClass = $this->getLevelTierDataClass();
        $tiers = [];
        $response = $dataClass::getList([
            'order' => ['UF_LEVEL' => 'ASC'],
            'select' => ['*'],
        ]);

        while ($row = $response->fetch()) {
            $tiers[] = $row;
        }

        return $tiers;
    }

    public function getLevelTierByLevel(int $level): ?array
    {
        $dataClass = $this->getLevelTierDataClass();
        $row = $dataClass::getList([
            'filter' => ['=UF_LEVEL' => $level],
            'limit' => 1,
        ])->fetch();

        return $row ?: null;
    }

    public function addLevelTier(array $fields): int
    {
        $dataClass = $this->getLevelTierDataClass();
        $result = $dataClass::add($fields);

        if (!$result->isSuccess()) {
            throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
        }

        return (int)$result->getId();
    }

    public function getPendingXp(int $userId, int $matchId): ?array
    {
        $dataClass = $this->getPendingXpDataClass();
        $row = $dataClass::getList([
            'filter' => [
                '=UF_USER_ID' => $userId,
                '=UF_MATCH_ID' => $matchId,
            ],
            'limit' => 1,
        ])->fetch();

        return $row ?: null;
    }

    /**
     * @param int[] $matchIds
     * @return array<int, array>
     */
    public function getPendingXpMap(int $userId, array $matchIds): array
    {
        if (!$matchIds) {
            return [];
        }

        $dataClass = $this->getPendingXpDataClass();
        $map = [];
        $response = $dataClass::getList([
            'filter' => [
                '=UF_USER_ID' => $userId,
                '@UF_MATCH_ID' => array_values(array_unique($matchIds)),
            ],
            'select' => ['*'],
        ]);

        while ($row = $response->fetch()) {
            $map[(int)$row['UF_MATCH_ID']] = $row;
        }

        return $map;
    }

    public function addPendingXp(array $fields): int
    {
        $dataClass = $this->getPendingXpDataClass();
        $result = $dataClass::add($fields);

        if (!$result->isSuccess()) {
            throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
        }

        return (int)$result->getId();
    }

    public function updatePendingXp(int $id, array $fields): void
    {
        $dataClass = $this->getPendingXpDataClass();
        $result = $dataClass::update($id, $fields);

        if (!$result->isSuccess()) {
            throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
        }
    }

    public function getGameBankByCode(string $code): ?array
    {
        $dataClass = $this->getGameBankDataClass();
        $row = $dataClass::getList([
            'filter' => ['=UF_CODE' => $code],
            'limit' => 1,
        ])->fetch();

        return $row ?: null;
    }

    public function ensureGameBank(string $code): array
    {
        $row = $this->getGameBankByCode($code);

        if ($row) {
            return $row;
        }

        $id = $this->addGameBank([
            'UF_CODE' => $code,
            'UF_PROGNOBAKS' => 0,
        ]);

        return $this->getGameBankById($id);
    }

    public function addGameBank(array $fields): int
    {
        $dataClass = $this->getGameBankDataClass();
        $result = $dataClass::add($fields);

        if (!$result->isSuccess()) {
            throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
        }

        return (int)$result->getId();
    }

    public function updateGameBank(int $id, array $fields): void
    {
        $dataClass = $this->getGameBankDataClass();
        $result = $dataClass::update($id, $fields);

        if (!$result->isSuccess()) {
            throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
        }
    }

    public function getGameBankById(int $id): ?array
    {
        $dataClass = $this->getGameBankDataClass();
        $row = $dataClass::getById($id)->fetch();

        return $row ?: null;
    }

    public function getMatchBet(int $userId, int $matchId): ?array
    {
        $dataClass = $this->getMatchBetDataClass();
        $row = $dataClass::getList([
            'filter' => [
                '=UF_USER_ID' => $userId,
                '=UF_MATCH_ID' => $matchId,
            ],
            'order' => [
                'UF_PAYOUT' => 'DESC',
                'ID' => 'DESC',
            ],
            'limit' => 1,
        ])->fetch();

        return $row ?: null;
    }

    /**
     * @param int[] $matchIds
     * @return array<int, array{status:string,payout:float}>
     */
    public function getMatchBetMapForUser(int $userId, array $matchIds): array
    {
        if ($userId <= 0 || !$matchIds) {
            return [];
        }

        $dataClass = $this->getMatchBetDataClass();
        $map = [];
        $response = $dataClass::getList([
            'filter' => [
                '=UF_USER_ID' => $userId,
                '@UF_MATCH_ID' => array_values(array_unique($matchIds)),
            ],
            'order' => [
                'UF_PAYOUT' => 'DESC',
                'ID' => 'DESC',
            ],
            'select' => ['*'],
        ]);

        while ($row = $response->fetch()) {
            $matchId = (int)($row['UF_MATCH_ID'] ?? 0);
            if ($matchId <= 0 || isset($map[$matchId])) {
                continue;
            }

            $map[$matchId] = [
                'status' => (string)($row['UF_STATUS'] ?? ''),
                'payout' => round((float)($row['UF_PAYOUT'] ?? 0), 1),
            ];
        }

        return $map;
    }

    public function getTreasureChest(int $userId, int $matchId): ?array
    {
        $dataClass = $this->getTreasureChestDataClass();
        $row = $dataClass::getList([
            'filter' => [
                '=UF_USER_ID' => $userId,
                '=UF_MATCH_ID' => $matchId,
            ],
            'limit' => 1,
        ])->fetch();

        return $row ?: null;
    }

    public function addTreasureChest(array $fields): int
    {
        $dataClass = $this->getTreasureChestDataClass();
        $result = $dataClass::add($fields);

        if (!$result->isSuccess()) {
            throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
        }

        return (int)$result->getId();
    }

    public function updateTreasureChest(int $id, array $fields): void
    {
        $dataClass = $this->getTreasureChestDataClass();
        $result = $dataClass::update($id, $fields);

        if (!$result->isSuccess()) {
            throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
        }
    }

    /**
     * @param int[] $matchIds
     * @return array<int, int> matchId => count
     */
    public function getTreasureChestCountMapForUser(int $userId, array $matchIds): array
    {
        if ($userId <= 0 || !$matchIds) {
            return [];
        }

        $dataClass = $this->getTreasureChestDataClass();
        $map = [];
        $response = $dataClass::getList([
            'filter' => [
                '=UF_USER_ID' => $userId,
                '@UF_MATCH_ID' => array_values(array_unique($matchIds)),
            ],
            'select' => ['UF_MATCH_ID', 'UF_COUNT'],
        ]);

        while ($row = $response->fetch()) {
            $matchId = (int)($row['UF_MATCH_ID'] ?? 0);
            if ($matchId <= 0) {
                continue;
            }
            $map[$matchId] = (int)($row['UF_COUNT'] ?? 0);
        }

        return $map;
    }

    /**
     * @return int total chests for user
     */
    public function getTreasureChestTotalForUser(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        $dataClass = $this->getTreasureChestDataClass();
        $total = 0;
        $response = $dataClass::getList([
            'filter' => [
                '=UF_USER_ID' => $userId,
                '=UF_STATUS' => 'closed',
            ],
            'select' => ['UF_COUNT'],
        ]);

        while ($row = $response->fetch()) {
            $total += (int)($row['UF_COUNT'] ?? 0);
        }

        return $total;
    }

    /**
     * @return array<int, array>
     */
    public function getPendingMatchBetsByMatch(int $matchId): array
    {
        $dataClass = $this->getMatchBetDataClass();
        $rows = [];
        $response = $dataClass::getList([
            'filter' => [
                '=UF_MATCH_ID' => $matchId,
                '=UF_STATUS' => \Prognos9ys\Main\Service\Game\GameEconomyConfig::BET_STATUS_PENDING,
            ],
            'select' => ['*'],
        ]);

        while ($row = $response->fetch()) {
            $rows[] = $row;
        }

        return $rows;
    }

    public function addMatchBet(array $fields): int
    {
        $dataClass = $this->getMatchBetDataClass();
        $result = $dataClass::add($fields);

        if (!$result->isSuccess()) {
            throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
        }

        return (int)$result->getId();
    }

    public function updateMatchBet(int $id, array $fields): void
    {
        $dataClass = $this->getMatchBetDataClass();
        $result = $dataClass::update($id, $fields);

        if (!$result->isSuccess()) {
            throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
        }
    }

    private function compileDataClass(string $tableName): string
    {
        if (!Loader::includeModule('highloadblock')) {
            throw new \RuntimeException('Модуль highloadblock не установлен');
        }

        $hlblock = HighloadBlockTable::getList([
            'filter' => ['=TABLE_NAME' => $tableName],
        ])->fetch();

        if (!$hlblock) {
            throw new \RuntimeException(
                'HL-блок не найден: ' . $tableName . '. Запустите install_game_economy_hl.php'
            );
        }

        $entity = HighloadBlockTable::compileEntity($hlblock);

        return $entity->getDataClass();
    }
}
