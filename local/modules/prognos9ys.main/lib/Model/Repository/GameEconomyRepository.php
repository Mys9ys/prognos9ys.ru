<?php

namespace Prognos9ys\Main\Model\Repository;

use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\Loader;
use Prognos9ys\Main\Service\Game\GameEconomyConfig;
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
    private ?string $achievementClaimDataClass = null;
    private ?string $treasuryShopWaveDataClass = null;
    private ?string $matchEconomySettleDataClass = null;

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

    public function getAchievementClaimDataClass(): string
    {
        return $this->achievementClaimDataClass ??= $this->compileDataClass(GameEconomyHlInstaller::TABLE_ACHIEVEMENT_CLAIM);
    }

    public function getTreasuryShopWaveDataClass(): string
    {
        return $this->treasuryShopWaveDataClass ??= $this->compileDataClass(GameEconomyHlInstaller::TABLE_TREASURY_SHOP_WAVE);
    }

    public function getMatchEconomySettleDataClass(): string
    {
        return $this->matchEconomySettleDataClass ??= $this->compileDataClass(GameEconomyHlInstaller::TABLE_MATCH_ECONOMY_SETTLE);
    }

    public function hasMatchEconomySettlement(int $matchId): bool
    {
        if ($matchId <= 0) {
            return false;
        }

        $dataClass = $this->getMatchEconomySettleDataClass();

        return (bool)$dataClass::getList([
            'filter' => ['=UF_MATCH_ID' => $matchId],
            'limit' => 1,
            'select' => ['ID'],
        ])->fetch();
    }

    public function addMatchEconomySettlement(array $fields): int
    {
        $dataClass = $this->getMatchEconomySettleDataClass();
        $result = $dataClass::add($fields);

        if (!$result->isSuccess()) {
            throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
        }

        return (int)$result->getId();
    }

    /**
     * @return array{id:int,number:int}
     */
    public function getLastMatchEconomySettlementForEvent(int $eventId): array
    {
        if ($eventId <= 0) {
            return ['id' => 0, 'number' => 0];
        }

        $dataClass = $this->getMatchEconomySettleDataClass();
        $row = $dataClass::getList([
            'filter' => ['=UF_EVENT_ID' => $eventId],
            'order' => ['UF_MATCH_NUMBER' => 'DESC', 'ID' => 'DESC'],
            'limit' => 1,
            'select' => ['UF_MATCH_ID', 'UF_MATCH_NUMBER'],
        ])->fetch();

        if (!$row) {
            return ['id' => 0, 'number' => 0];
        }

        return [
            'id' => (int)($row['UF_MATCH_ID'] ?? 0),
            'number' => (int)($row['UF_MATCH_NUMBER'] ?? 0),
        ];
    }

    public function getWalletByUserId(int $userId): ?array
    {
        $rows = $this->getWalletRowsByUserId($userId);

        if (!$rows) {
            return null;
        }

        if (count($rows) > 1) {
            return $this->mergeWalletDuplicatesForUser($userId, $rows);
        }

        return $rows[0];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    public function mergeWalletDuplicatesForUser(int $userId, array $rows): array
    {
        if ($userId <= 0 || !$rows) {
            throw new \InvalidArgumentException('Некорректный кошелёк для объединения');
        }

        if (count($rows) === 1) {
            return $rows[0];
        }

        $primary = $rows[0];
        $primaryId = (int)($primary['ID'] ?? 0);
        $prognobaks = 0.0;
        $rublius = 0.0;

        foreach ($rows as $row) {
            $prognobaks += round((float)($row['UF_PROGNOBAKS'] ?? 0), 1);
            $rublius += round((float)($row['UF_RUBLIUS'] ?? 0), 1);
        }

        $this->updateWallet($primaryId, [
            'UF_PROGNOBAKS' => round($prognobaks, 1),
            'UF_RUBLIUS' => round($rublius, 1),
        ]);

        for ($i = 1, $count = count($rows); $i < $count; $i++) {
            $duplicateId = (int)($rows[$i]['ID'] ?? 0);
            if ($duplicateId > 0 && $duplicateId !== $primaryId) {
                $this->deleteWallet($duplicateId);
            }
        }

        $merged = $this->getWalletRowsByUserId($userId);

        if (!$merged) {
            throw new \RuntimeException('Не удалось объединить кошельки пользователя #' . $userId);
        }

        return $merged[0];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getWalletRowsByUserId(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $dataClass = $this->getWalletDataClass();
        $rows = [];
        $response = $dataClass::getList([
            'filter' => ['=UF_USER_ID' => $userId],
            'order' => ['ID' => 'ASC'],
        ]);

        while ($row = $response->fetch()) {
            $rows[] = $row;
        }

        return $rows;
    }

    public function deleteWallet(int $id): void
    {
        if ($id <= 0) {
            return;
        }

        $dataClass = $this->getWalletDataClass();
        $result = $dataClass::delete($id);

        if (!$result->isSuccess()) {
            throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
        }
    }

    /**
     * @return array<int, array{user_id:int,prognobaks:float,rublius:float}>
     */
    public function getAllWallets(): array
    {
        $dataClass = $this->getWalletDataClass();
        $grouped = [];
        $response = $dataClass::getList([
            'select' => ['UF_USER_ID', 'UF_PROGNOBAKS', 'UF_RUBLIUS'],
            'order' => ['UF_USER_ID' => 'ASC', 'ID' => 'ASC'],
        ]);

        while ($row = $response->fetch()) {
            $userId = (int)($row['UF_USER_ID'] ?? 0);
            if ($userId <= 0) {
                continue;
            }

            if (!isset($grouped[$userId])) {
                $grouped[$userId] = [
                    'user_id' => $userId,
                    'prognobaks' => 0.0,
                    'rublius' => 0.0,
                ];
            }

            $grouped[$userId]['prognobaks'] += round((float)($row['UF_PROGNOBAKS'] ?? 0), 1);
            $grouped[$userId]['rublius'] += round((float)($row['UF_RUBLIUS'] ?? 0), 1);
        }

        return array_values(array_map(static function (array $wallet): array {
            return [
                'user_id' => (int)$wallet['user_id'],
                'prognobaks' => round((float)$wallet['prognobaks'], 1),
                'rublius' => round((float)$wallet['rublius'], 1),
            ];
        }, $grouped));
    }

    /**
     * @return array{prognobaks:float,rublius:float}
     */
    public function sumWalletBalances(): array
    {
        $dataClass = $this->getWalletDataClass();
        $prognobaks = 0.0;
        $rublius = 0.0;
        $response = $dataClass::getList([
            'select' => ['UF_PROGNOBAKS', 'UF_RUBLIUS'],
        ]);

        while ($row = $response->fetch()) {
            $prognobaks += round((float)($row['UF_PROGNOBAKS'] ?? 0), 1);
            $rublius += round((float)($row['UF_RUBLIUS'] ?? 0), 1);
        }

        return [
            'prognobaks' => round($prognobaks, 1),
            'rublius' => round($rublius, 1),
        ];
    }

    /**
     * @return array{prognobaks:float,rublius:float}
     */
    public function sumActiveUserBankBalances(): array
    {
        $dataClass = $this->getUserBankDataClass();
        $prognobaks = 0.0;
        $response = $dataClass::getList([
            'filter' => ['=UF_ACTIVE' => GameEconomyConfig::USER_BANK_STATUS_ACTIVE],
            'select' => ['UF_RESERVED', 'UF_LIQUID'],
        ]);

        while ($row = $response->fetch()) {
            $prognobaks += round((float)($row['UF_RESERVED'] ?? 0) + (float)($row['UF_LIQUID'] ?? 0), 1);
        }

        return [
            'prognobaks' => round($prognobaks, 1),
            'rublius' => 0.0,
        ];
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

    public function updateWalletTxRefForLastReason(
        int $userId,
        string $reason,
        string $refType,
        int $refId
    ): void {
        if ($userId <= 0 || $refId <= 0) {
            return;
        }

        $dataClass = $this->getWalletTxDataClass();
        $row = $dataClass::getList([
            'filter' => [
                '=UF_USER_ID' => $userId,
                '=UF_REASON' => $reason,
            ],
            'order' => ['ID' => 'DESC'],
            'limit' => 1,
        ])->fetch();

        if (!$row) {
            return;
        }

        $dataClass::update((int)$row['ID'], [
            'UF_REF_TYPE' => $refType,
            'UF_REF_ID' => $refId,
        ]);
    }

    public function hasWalletTx(
        int $userId,
        string $reason,
        ?string $refType = null,
        ?int $refId = null,
        ?string $currency = null
    ): bool {
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

        if ($currency !== null) {
            $filter['=UF_CURRENCY'] = $currency;
        }

        $dataClass = $this->getWalletTxDataClass();
        $row = $dataClass::getList([
            'filter' => $filter,
            'limit' => 1,
            'select' => ['ID'],
        ])->fetch();

        return (bool)$row;
    }

    /**
     * @return array<int, array>
     */
    public function getBankWalletTxByUserId(int $userId, int $limit = 100): array
    {
        if ($userId <= 0) {
            return [];
        }

        $dataClass = $this->getWalletTxDataClass();
        $rows = [];
        $response = $dataClass::getList([
            'filter' => [
                '=UF_USER_ID' => $userId,
                '@UF_REASON' => [
                    'bank_reserve_lock',
                    'bank_reserve_unlock',
                    'bank_deposit',
                    'bank_deposit_cancel',
                    'bank_deposit_return',
                    'bank_deposit_return_half',
                    'bank_deposit_interest',
                    'bank_loan',
                    'bank_loan_cancel',
                    'bank_loan_repay',
                    'bank_loan_interest',
                ],
            ],
            'order' => ['UF_CREATED_AT' => 'DESC', 'ID' => 'DESC'],
            'limit' => max(1, min(200, $limit)),
            'select' => ['*'],
        ]);

        while ($row = $response->fetch()) {
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param int[] $refIds
     * @param string[]|null $reasons
     * @return array<int, array>
     */
    public function getWalletTxByRefs(string $refType, array $refIds, ?array $reasons = null): array
    {
        $refIds = array_values(array_filter(array_map('intval', $refIds)));
        if (!$refIds) {
            return [];
        }

        $filter = [
            '=UF_REF_TYPE' => $refType,
            '@UF_REF_ID' => $refIds,
        ];

        if ($reasons) {
            $filter['@UF_REASON'] = $reasons;
        }

        $dataClass = $this->getWalletTxDataClass();
        $rows = [];
        $response = $dataClass::getList([
            'filter' => $filter,
            'order' => ['UF_CREATED_AT' => 'ASC', 'ID' => 'ASC'],
            'select' => ['*'],
        ]);

        while ($row = $response->fetch()) {
            $rows[] = $row;
        }

        return $rows;
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

    public function resetAllUserProgressXp(): int
    {
        $dataClass = $this->getUserProgressDataClass();
        $updated = 0;
        $response = $dataClass::getList(['select' => ['ID', 'UF_XP']]);

        while ($row = $response->fetch()) {
            if (round((float)($row['UF_XP'] ?? 0), 1) === 0.0) {
                continue;
            }

            $dataClass::update((int)$row['ID'], ['UF_XP' => 0]);
            $updated++;
        }

        return $updated;
    }

    public function reopenClaimedPendingXp(): int
    {
        $dataClass = $this->getPendingXpDataClass();
        $updated = 0;
        $response = $dataClass::getList([
            'filter' => ['=UF_STATUS' => GameEconomyConfig::XP_STATUS_CLAIMED],
            'select' => ['ID'],
        ]);

        while ($row = $response->fetch()) {
            $dataClass::update((int)$row['ID'], [
                'UF_STATUS' => GameEconomyConfig::XP_STATUS_PENDING,
                'UF_CLAIMED_AT' => null,
            ]);
            $updated++;
        }

        return $updated;
    }

    public function resetAllWalletBalances(float $prognobaks, float $rublius): int
    {
        $dataClass = $this->getWalletDataClass();
        $updated = 0;
        $response = $dataClass::getList(['select' => ['ID']]);

        while ($row = $response->fetch()) {
            $dataClass::update((int)$row['ID'], [
                'UF_PROGNOBAKS' => $prognobaks,
                'UF_RUBLIUS' => $rublius,
            ]);
            $updated++;
        }

        return $updated;
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

    /**
     * @return array<int, array>
     */
    public function getPendingXpListForUser(int $userId, ?string $status = null): array
    {
        if ($userId <= 0) {
            return [];
        }

        $filter = ['=UF_USER_ID' => $userId];

        if ($status !== null) {
            $filter['=UF_STATUS'] = $status;
        }

        $dataClass = $this->getPendingXpDataClass();
        $rows = [];
        $response = $dataClass::getList([
            'filter' => $filter,
            'select' => ['*'],
            'order' => ['UF_MATCH_ID' => 'ASC'],
        ]);

        while ($row = $response->fetch()) {
            $rows[] = $row;
        }

        return $rows;
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

    /**
     * @param callable(int):bool|null $matchFilter
     * @return array<int, array{count:int,points:float}>
     */
    public function getPendingXpAggregatesByUser(?callable $matchFilter = null): array
    {
        $dataClass = $this->getPendingXpDataClass();
        $map = [];
        $response = $dataClass::getList([
            'filter' => [
                '=UF_STATUS' => GameEconomyConfig::XP_STATUS_PENDING,
            ],
            'select' => ['UF_USER_ID', 'UF_POINTS', 'UF_MATCH_ID'],
        ]);

        while ($row = $response->fetch()) {
            $matchId = (int)($row['UF_MATCH_ID'] ?? 0);

            if ($matchFilter !== null && ($matchId <= 0 || !$matchFilter($matchId))) {
                continue;
            }

            $userId = (int)($row['UF_USER_ID'] ?? 0);
            if ($userId <= 0) {
                continue;
            }

            if (!isset($map[$userId])) {
                $map[$userId] = ['count' => 0, 'points' => 0.0];
            }

            $map[$userId]['count']++;
            $map[$userId]['points'] = round(
                $map[$userId]['points'] + (float)($row['UF_POINTS'] ?? 0),
                1
            );
        }

        return $map;
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
            'UF_RUBLIUS' => 0,
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

    public function getTreasureChestByType(int $userId, int $matchId, string $type): ?array
    {
        if ($userId <= 0 || $matchId === 0 || $type === '') {
            return null;
        }

        $dataClass = $this->getTreasureChestDataClass();
        $row = $dataClass::getList([
            'filter' => [
                '=UF_USER_ID' => $userId,
                '=UF_MATCH_ID' => $matchId,
                '=UF_TYPE' => $type,
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

    /**
     * @return array<string, array{claimed_threshold:int,id:int}>
     */
    public function getAchievementClaimMapForUser(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $dataClass = $this->getAchievementClaimDataClass();
        $map = [];
        $response = $dataClass::getList([
            'filter' => ['=UF_USER_ID' => $userId],
            'select' => ['ID', 'UF_CODE', 'UF_CLAIMED_THRESHOLD'],
        ]);

        while ($row = $response->fetch()) {
            $code = (string)($row['UF_CODE'] ?? '');
            if ($code === '') {
                continue;
            }
            $map[$code] = [
                'id' => (int)($row['ID'] ?? 0),
                'claimed_threshold' => (int)($row['UF_CLAIMED_THRESHOLD'] ?? 0),
            ];
        }

        return $map;
    }

    public function upsertAchievementClaim(int $userId, string $code, int $claimedThreshold, array $fields = []): void
    {
        if ($userId <= 0 || $code === '' || $claimedThreshold < 0) {
            throw new \InvalidArgumentException('Invalid achievement claim upsert params');
        }

        $dataClass = $this->getAchievementClaimDataClass();
        $existing = $dataClass::getList([
            'filter' => [
                '=UF_USER_ID' => $userId,
                '=UF_CODE' => $code,
            ],
            'select' => ['ID'],
            'limit' => 1,
        ])->fetch();

        $payload = array_merge($fields, [
            'UF_USER_ID' => $userId,
            'UF_CODE' => $code,
            'UF_CLAIMED_THRESHOLD' => $claimedThreshold,
        ]);

        if ($existing) {
            $result = $dataClass::update((int)$existing['ID'], $payload);
            if (!$result->isSuccess()) {
                throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
            }
            return;
        }

        $result = $dataClass::add($payload);
        if (!$result->isSuccess()) {
            throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
        }
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
     * @return array{total:int,match:int,level:int,achievement:int,shop:int}
     */
    public function getTreasureChestBreakdownForUser(int $userId): array
    {
        if ($userId <= 0) {
            return [
                'total' => 0,
                'match' => 0,
                'level' => 0,
                'achievement' => 0,
                'shop' => 0,
            ];
        }

        $dataClass = $this->getTreasureChestDataClass();
        $breakdown = [
            'total' => 0,
            'match' => 0,
            'level' => 0,
            'achievement' => 0,
            'shop' => 0,
        ];

        $response = $dataClass::getList([
            'filter' => [
                '=UF_USER_ID' => $userId,
                '=UF_STATUS' => 'closed',
            ],
            'select' => ['UF_COUNT', 'UF_TYPE', 'UF_MATCH_ID'],
        ]);

        while ($row = $response->fetch()) {
            $count = (int)($row['UF_COUNT'] ?? 0);
            if ($count <= 0) {
                continue;
            }

            $breakdown['total'] += $count;
            $type = (string)($row['UF_TYPE'] ?? '');
            $matchId = (int)($row['UF_MATCH_ID'] ?? 0);

            if ($type === 'shop_wc26') {
                $breakdown['shop'] += $count;
                continue;
            }

            if ($type === 'achievement') {
                $breakdown['achievement'] += $count;
                continue;
            }

            if ($type === 'level' || ($type === '' && $matchId < 0 && $matchId >= -500)) {
                $breakdown['level'] += $count;
                continue;
            }

            $breakdown['match'] += $count;
        }

        return $breakdown;
    }

    public function getPremiumScrollCountForUser(int $userId): int
    {
        $breakdown = $this->getPremiumScrollBreakdownForUser($userId);

        return (int)($breakdown[1] ?? 0) + (int)($breakdown[3] ?? 0) + (int)($breakdown[5] ?? 0);
    }

    /**
     * @return array{1:int,3:int,5:int}
     */
    public function getPremiumScrollBreakdownForUser(int $userId): array
    {
        $breakdown = [1 => 0, 3 => 0, 5 => 0];

        if ($userId <= 0) {
            return $breakdown;
        }

        $dataClass = $this->getTreasureChestDataClass();
        $response = $dataClass::getList([
            'filter' => [
                '=UF_USER_ID' => $userId,
                '=UF_TYPE' => 'premium_scroll',
                '=UF_STATUS' => 'inventory',
            ],
            'select' => ['UF_COUNT', 'UF_MATCH_ID'],
        ]);

        while ($row = $response->fetch()) {
            $count = (int)($row['UF_COUNT'] ?? 0);
            if ($count <= 0) {
                continue;
            }

            $days = $this->resolvePremiumScrollDays((int)($row['UF_MATCH_ID'] ?? 0));
            $breakdown[$days] = (int)($breakdown[$days] ?? 0) + $count;
        }

        return $breakdown;
    }

    /**
     * @return array{site:int,chm2026:int}
     */
    public function getPennantInventoryCountsForUser(int $userId): array
    {
        $counts = [
            'site' => 0,
            'chm2026' => 0,
        ];

        if ($userId <= 0) {
            return $counts;
        }

        $dataClass = $this->getTreasureChestDataClass();
        $response = $dataClass::getList([
            'filter' => [
                '=UF_USER_ID' => $userId,
                '=UF_TYPE' => 'pennant',
                '=UF_STATUS' => 'inventory',
            ],
            'select' => ['UF_COUNT', 'UF_MATCH_ID'],
        ]);

        while ($row = $response->fetch()) {
            $code = $this->resolvePennantCodeFromSyntheticMatchId((int)($row['UF_MATCH_ID'] ?? 0));
            if ($code === null) {
                continue;
            }

            $counts[$code] = (int)($counts[$code] ?? 0) + (int)($row['UF_COUNT'] ?? 0);
        }

        return $counts;
    }

    private function resolvePennantCodeFromSyntheticMatchId(int $matchId): ?string
    {
        $map = [
            -3000001 => 'site',
            -3000002 => 'chm2026',
        ];

        return $map[$matchId] ?? null;
    }

    private function resolvePremiumScrollDays(int $matchId): int
    {
        if ($matchId >= -2000000) {
            return 1;
        }

        $offset = -$matchId - 2000000;

        if ($offset < 100) {
            return 1;
        }

        $days = $offset % 100;

        return in_array($days, [1, 3, 5], true) ? $days : 1;
    }

    /**
     * @return array<int, int> userId => total closed chests
     */
    public function getClosedTreasureChestTotalsMapForAllUsers(): array
    {
        $dataClass = $this->getTreasureChestDataClass();

        $map = [];
        $response = $dataClass::getList([
            'filter' => [
                '=UF_STATUS' => 'closed',
            ],
            'select' => ['UF_USER_ID', 'UF_COUNT'],
        ]);

        while ($row = $response->fetch()) {
            $userId = (int)($row['UF_USER_ID'] ?? 0);
            if ($userId <= 0) {
                continue;
            }

            $map[$userId] = (int)($map[$userId] ?? 0) + (int)($row['UF_COUNT'] ?? 0);
        }

        return $map;
    }

    /**
     * @return array<int, array>
     */
    public function getMatchBetsByMatch(int $matchId): array
    {
        $dataClass = $this->getMatchBetDataClass();
        $rows = [];
        $response = $dataClass::getList([
            'filter' => [
                '=UF_MATCH_ID' => $matchId,
            ],
            'select' => ['*'],
        ]);

        while ($row = $response->fetch()) {
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @return array<int, array>
     */
    public function getPendingMatchBetsByMatch(int $matchId): array
    {
        if ($matchId <= 0) {
            return [];
        }

        $dataClass = $this->getMatchBetDataClass();
        $rows = [];
        $response = $dataClass::getList([
            'filter' => [
                '=UF_MATCH_ID' => $matchId,
                '=UF_STATUS' => GameEconomyConfig::BET_STATUS_PENDING,
            ],
            'select' => ['*'],
        ]);

        while ($row = $response->fetch()) {
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param int[] $matchIds
     * @return array<int, array>
     */
    public function getBetsByMatchIds(array $matchIds, ?string $status = null): array
    {
        $matchIds = array_values(array_unique(array_filter(array_map('intval', $matchIds))));
        if (!$matchIds) {
            return [];
        }

        $filter = ['@UF_MATCH_ID' => $matchIds];
        if ($status !== null && $status !== '') {
            $filter['=UF_STATUS'] = $status;
        }

        $dataClass = $this->getMatchBetDataClass();
        $rows = [];
        $response = $dataClass::getList([
            'filter' => $filter,
            'select' => ['UF_MATCH_ID', 'UF_OUTCOME'],
        ]);

        while ($row = $response->fetch()) {
            $rows[] = $row;
        }

        return $rows;
    }

    public function deleteMatchBetsByMatch(int $matchId): int
    {
        $deleted = 0;
        foreach ($this->getMatchBetsByMatch($matchId) as $row) {
            $dataClass = $this->getMatchBetDataClass();
            $dataClass::delete((int)$row['ID']);
            $deleted++;
        }

        return $deleted;
    }

    public function deleteAllMatchBets(): int
    {
        $dataClass = $this->getMatchBetDataClass();
        $deleted = 0;
        $response = $dataClass::getList(['select' => ['ID']]);
        while ($row = $response->fetch()) {
            $dataClass::delete((int)$row['ID']);
            $deleted++;
        }

        return $deleted;
    }

    public function deleteAllWalletTx(): int
    {
        $dataClass = $this->getWalletTxDataClass();
        $deleted = 0;
        $response = $dataClass::getList(['select' => ['ID']]);
        while ($row = $response->fetch()) {
            $dataClass::delete((int)$row['ID']);
            $deleted++;
        }

        return $deleted;
    }

    public function deleteAllPendingXp(): int
    {
        $dataClass = $this->getPendingXpDataClass();
        $deleted = 0;
        $response = $dataClass::getList(['select' => ['ID']]);
        while ($row = $response->fetch()) {
            $dataClass::delete((int)$row['ID']);
            $deleted++;
        }

        return $deleted;
    }

    public function deleteAllTreasureChests(): int
    {
        $dataClass = $this->getTreasureChestDataClass();
        $deleted = 0;
        $response = $dataClass::getList(['select' => ['ID']]);
        while ($row = $response->fetch()) {
            $dataClass::delete((int)$row['ID']);
            $deleted++;
        }

        return $deleted;
    }

    public function resetGameBank(string $code): void
    {
        $row = $this->getGameBankByCode($code);
        if (!$row) {
            return;
        }

        $fields = ['UF_PROGNOBAKS' => 0];
        if (array_key_exists('UF_RUBLIUS', $row)) {
            $fields['UF_RUBLIUS'] = 0;
        }

        $this->updateGameBank((int)$row['ID'], $fields);
    }

    public function getTreasuryShopWave(int $userId, int $milestone): ?array
    {
        if ($userId <= 0 || $milestone <= 0) {
            return null;
        }

        $dataClass = $this->getTreasuryShopWaveDataClass();
        $row = $dataClass::getList([
            'filter' => [
                '=UF_USER_ID' => $userId,
                '=UF_MILESTONE' => $milestone,
            ],
            'order' => ['ID' => 'ASC'],
            'limit' => 1,
        ])->fetch();

        return $row ?: null;
    }

    public function getTreasuryShopWaveById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $dataClass = $this->getTreasuryShopWaveDataClass();
        $row = $dataClass::getById($id)->fetch();

        return $row ?: null;
    }

    public function addTreasuryShopWave(array $fields): int
    {
        $dataClass = $this->getTreasuryShopWaveDataClass();
        $result = $dataClass::add($fields);

        if (!$result->isSuccess()) {
            throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
        }

        return (int)$result->getId();
    }

    public function updateTreasuryShopWave(int $id, array $fields): void
    {
        $dataClass = $this->getTreasuryShopWaveDataClass();
        $result = $dataClass::update($id, $fields);

        if (!$result->isSuccess()) {
            throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
        }
    }

    /**
     * @return array<int, float> userId => xp
     */
    public function getAllUserXpMap(): array
    {
        $dataClass = $this->getUserProgressDataClass();
        $map = [];
        $response = $dataClass::getList(['select' => ['UF_USER_ID', 'UF_XP']]);

        while ($row = $response->fetch()) {
            $userId = (int)($row['UF_USER_ID'] ?? 0);
            if ($userId <= 0) {
                continue;
            }

            $map[$userId] = round((float)($row['UF_XP'] ?? 0), 1);
        }

        return $map;
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

    public function getUserBankDataClass(): string
    {
        return $this->compileDataClass(GameEconomyHlInstaller::TABLE_USER_BANK);
    }

    public function getBankDepositDataClass(): string
    {
        return $this->compileDataClass(GameEconomyHlInstaller::TABLE_BANK_DEPOSIT);
    }

    public function getBankLoanDataClass(): string
    {
        return $this->compileDataClass(GameEconomyHlInstaller::TABLE_BANK_LOAN);
    }

    public function getUserBankById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $dataClass = $this->getUserBankDataClass();
        $row = $dataClass::getList([
            'filter' => ['=ID' => $id],
            'limit' => 1,
        ])->fetch();

        return $row ?: null;
    }

    public function getUserBankByOwnerId(int $ownerId, bool $activeOnly = true): ?array
    {
        if ($ownerId <= 0) {
            return null;
        }

        $filter = ['=UF_OWNER_ID' => $ownerId];
        if ($activeOnly) {
            $filter['=UF_ACTIVE'] = GameEconomyConfig::USER_BANK_STATUS_ACTIVE;
        }

        $dataClass = $this->getUserBankDataClass();
        $row = $dataClass::getList([
            'filter' => $filter,
            'order' => ['ID' => 'DESC'],
            'limit' => 1,
        ])->fetch();

        return $row ?: null;
    }

    public function getActiveUserBanks(int $limit = 50): array
    {
        $dataClass = $this->getUserBankDataClass();
        $rows = [];
        $response = $dataClass::getList([
            'filter' => ['=UF_ACTIVE' => GameEconomyConfig::USER_BANK_STATUS_ACTIVE],
            'order' => ['UF_LIQUID' => 'DESC', 'ID' => 'DESC'],
            'limit' => $limit,
        ]);

        while ($row = $response->fetch()) {
            $rows[] = $row;
        }

        return $rows;
    }

    public function addUserBank(array $fields): int
    {
        $dataClass = $this->getUserBankDataClass();
        $result = $dataClass::add($fields);

        if (!$result->isSuccess()) {
            throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
        }

        return (int)$result->getId();
    }

    public function updateUserBank(int $id, array $fields): void
    {
        $dataClass = $this->getUserBankDataClass();
        $result = $dataClass::update($id, $fields);

        if (!$result->isSuccess()) {
            throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
        }
    }

    public function adjustUserBankLiquid(int $bankId, float $delta): void
    {
        $bank = $this->getUserBankById($bankId);
        if (!$bank) {
            throw new \RuntimeException('Банк не найден');
        }

        $liquid = round((float)($bank['UF_LIQUID'] ?? 0) + $delta, 1);
        if ($liquid < 0) {
            throw new \RuntimeException('Недостаточно ликвидности банка');
        }

        $this->updateUserBank($bankId, ['UF_LIQUID' => $liquid]);
    }

    public function getUserBankLoanableAmount(array $bank): float
    {
        return round((float)($bank['UF_RESERVED'] ?? 0) + (float)($bank['UF_LIQUID'] ?? 0), 1);
    }

    /**
     * Списание под займ: сначала резерв владельца, затем ликвидность вкладов.
     */
    public function allocateUserBankFundsForLoan(int $bankId, float $amount): void
    {
        $bank = $this->getUserBankById($bankId);
        if (!$bank) {
            throw new \RuntimeException('Банк не найден');
        }

        $amount = round($amount, 1);
        $reserved = round((float)($bank['UF_RESERVED'] ?? 0), 1);
        $liquid = round((float)($bank['UF_LIQUID'] ?? 0), 1);

        if ($this->getUserBankLoanableAmount($bank) < $amount) {
            throw new \RuntimeException('В банке недостаточно средств для займа');
        }

        $fromReserve = round(min($amount, $reserved), 1);
        $fromLiquid = round($amount - $fromReserve, 1);

        $this->updateUserBank($bankId, [
            'UF_RESERVED' => round($reserved - $fromReserve, 1),
            'UF_LIQUID' => round($liquid - $fromLiquid, 1),
        ]);
    }

    /**
     * Возврат по займу: сначала восстанавливаем резерв владельца до лимита, остаток — в ликвидность.
     */
    public function creditUserBankLoanRepayment(int $bankId, float $amount): void
    {
        $bank = $this->getUserBankById($bankId);
        if (!$bank) {
            throw new \RuntimeException('Банк не найден');
        }

        $amount = round($amount, 1);
        if ($amount <= 0) {
            return;
        }

        $reserved = round((float)($bank['UF_RESERVED'] ?? 0), 1);
        $liquid = round((float)($bank['UF_LIQUID'] ?? 0), 1);
        $reserveCap = GameEconomyConfig::BANK_RESERVED_CAPITAL_PROGNOBAKS;
        $toReserve = round(min($amount, max(0.0, $reserveCap - $reserved)), 1);
        $toLiquid = round($amount - $toReserve, 1);

        $this->updateUserBank($bankId, [
            'UF_RESERVED' => round($reserved + $toReserve, 1),
            'UF_LIQUID' => round($liquid + $toLiquid, 1),
        ]);
    }

    public function getBankDepositById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $dataClass = $this->getBankDepositDataClass();
        $row = $dataClass::getList([
            'filter' => ['=ID' => $id],
            'limit' => 1,
        ])->fetch();

        return $row ?: null;
    }

    public function getActiveDepositsByEvent(int $eventId): array
    {
        return $this->getDepositsByFilter([
            '=UF_EVENT_ID' => $eventId,
            '@UF_STATUS' => [
                GameEconomyConfig::CONTRACT_STATUS_ACTIVE,
                GameEconomyConfig::CONTRACT_STATUS_EXTENDED,
            ],
        ]);
    }

    public function getActiveDepositsByBankId(int $bankId): array
    {
        return $this->getDepositsByFilter([
            '=UF_BANK_ID' => $bankId,
            '@UF_STATUS' => [
                GameEconomyConfig::CONTRACT_STATUS_ACTIVE,
                GameEconomyConfig::CONTRACT_STATUS_EXTENDED,
            ],
        ]);
    }

    public function getDepositsByBankId(int $bankId): array
    {
        return $this->getDepositsByFilter(['=UF_BANK_ID' => $bankId], ['ID' => 'DESC']);
    }

    public function getAllActiveDeposits(): array
    {
        return $this->getDepositsByFilter([
            '@UF_STATUS' => [
                GameEconomyConfig::CONTRACT_STATUS_ACTIVE,
                GameEconomyConfig::CONTRACT_STATUS_EXTENDED,
            ],
        ]);
    }

    public function getDepositsByUserId(int $userId): array
    {
        return $this->getDepositsByFilter(['=UF_USER_ID' => $userId], ['ID' => 'DESC']);
    }

    public function countActiveContractsByBankId(int $bankId): int
    {
        return count($this->getActiveDepositsByBankId($bankId))
            + count($this->getActiveLoansByBankId($bankId));
    }

    private function getDepositsByFilter(array $filter, array $order = ['ID' => 'ASC']): array
    {
        $dataClass = $this->getBankDepositDataClass();
        $rows = [];
        $response = $dataClass::getList([
            'filter' => $filter,
            'order' => $order,
        ]);

        while ($row = $response->fetch()) {
            $rows[] = $row;
        }

        return $rows;
    }

    public function addBankDeposit(array $fields): int
    {
        $dataClass = $this->getBankDepositDataClass();
        $result = $dataClass::add($fields);

        if (!$result->isSuccess()) {
            throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
        }

        return (int)$result->getId();
    }

    public function updateBankDeposit(int $id, array $fields): void
    {
        $dataClass = $this->getBankDepositDataClass();
        $result = $dataClass::update($id, $fields);

        if (!$result->isSuccess()) {
            throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
        }
    }

    public function getBankLoanById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $dataClass = $this->getBankLoanDataClass();
        $row = $dataClass::getList([
            'filter' => ['=ID' => $id],
            'limit' => 1,
        ])->fetch();

        return $row ?: null;
    }

    public function getActiveLoansByEvent(int $eventId): array
    {
        return $this->getLoansByFilter([
            '=UF_EVENT_ID' => $eventId,
            '@UF_STATUS' => [
                GameEconomyConfig::CONTRACT_STATUS_ACTIVE,
                GameEconomyConfig::CONTRACT_STATUS_EXTENDED,
            ],
        ]);
    }

    public function getActiveLoansByBankId(int $bankId): array
    {
        return $this->getLoansByFilter([
            '=UF_BANK_ID' => $bankId,
            '@UF_STATUS' => [
                GameEconomyConfig::CONTRACT_STATUS_ACTIVE,
                GameEconomyConfig::CONTRACT_STATUS_EXTENDED,
            ],
        ]);
    }

    public function getLoansByBankId(int $bankId): array
    {
        return $this->getLoansByFilter(['=UF_BANK_ID' => $bankId], ['ID' => 'DESC']);
    }

    public function getLoansByUserId(int $userId): array
    {
        return $this->getLoansByFilter(['=UF_USER_ID' => $userId], ['ID' => 'DESC']);
    }

    private function getLoansByFilter(array $filter, array $order = ['ID' => 'ASC']): array
    {
        $dataClass = $this->getBankLoanDataClass();
        $rows = [];
        $response = $dataClass::getList([
            'filter' => $filter,
            'order' => $order,
        ]);

        while ($row = $response->fetch()) {
            $rows[] = $row;
        }

        return $rows;
    }

    public function addBankLoan(array $fields): int
    {
        $dataClass = $this->getBankLoanDataClass();
        $result = $dataClass::add($fields);

        if (!$result->isSuccess()) {
            throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
        }

        return (int)$result->getId();
    }

    public function updateBankLoan(int $id, array $fields): void
    {
        $dataClass = $this->getBankLoanDataClass();
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
