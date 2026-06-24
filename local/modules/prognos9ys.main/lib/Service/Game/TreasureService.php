<?php

namespace Prognos9ys\Main\Service\Game;

use Bitrix\Main\Type\DateTime;
use Prognos9ys\Main\Model\Repository\GameEconomyRepository;

class TreasureService
{
    public const CHEST_STATUS_CLOSED = 'closed';
    public const CHEST_STATUS_OPENED = 'opened';
    public const CHEST_STATUS_INVENTORY = 'inventory';
    public const CHEST_TYPE_MATCH = 'match';
    public const CHEST_TYPE_LEVEL = 'level';
    public const CHEST_TYPE_ACHIEVEMENT = 'achievement';
    public const CHEST_TYPE_WC26_ACHIEVEMENT = 'wc26_achievement';
    public const CHEST_TYPE_SHOP_WC26 = 'shop_wc26';
    public const CHEST_TYPE_PREMIUM_SCROLL = 'premium_scroll';
    public const CHEST_TYPE_PENNANT = 'pennant';

    /** @var array<string, int> */
    private const PENNANT_SYNTHETIC_MATCH_IDS = [
        'site' => -3000001,
        'chm2026' => -3000002,
    ];

    /** Пороги ачивки «ЧМ2026», за которые выдаются сундуки пула ЧМ-26. */
    private const CHM2026_CHEST_THRESHOLDS = [10, 50, 100];

    /** Старый баг: crc32()+abs давал коллизию на INT32_MIN, все ачивочные сундуки в одной строке. */
    public const LEGACY_COLLIDED_SYNTHETIC_MATCH_ID = -2147483648;

    /**
     * Стабильный synthetic match_id для награды ачивки (без переполнения crc32).
     */
    public static function achievementSyntheticMatchId(string $code, int $threshold): int
    {
        $unsigned = (int)sprintf('%u', crc32($code . ':' . $threshold));
        $bucket = $unsigned % 2000000000;

        return -($bucket + 1);
    }

    /**
     * @return int[]
     */
    public static function getChm2026AchievementSyntheticMatchIds(): array
    {
        $ids = [];
        foreach (self::CHM2026_CHEST_THRESHOLDS as $threshold) {
            $ids[] = self::achievementSyntheticMatchId('chm2026', $threshold);
        }

        return $ids;
    }

    public static function isChm2026AchievementSyntheticMatchId(int $matchId): bool
    {
        return in_array($matchId, self::getChm2026AchievementSyntheticMatchIds(), true);
    }

    private GameEconomyRepository $repository;
    private GameEventScopeService $scopeService;

    public function __construct(?GameEconomyRepository $repository = null)
    {
        $this->repository = $repository ?? new GameEconomyRepository();
        $this->scopeService = new GameEventScopeService();
    }

    /**
     * Upsert closed chest reward for user+match based on result score.
     */
    public function upsertFromScore(int $userId, int $matchId, int $eventId, int $matchNumber, float $allScore): void
    {
        if ($userId <= 0 || $matchId <= 0 || $eventId <= 0) {
            return;
        }

        if (!$this->scopeService->isMatchInScope($eventId, $matchNumber)) {
            return;
        }

        $target = 0;
        if ($allScore >= 40) {
            $target = 2;
        } elseif ($allScore >= 30) {
            $target = 1;
        }

        if ($target <= 0) {
            return;
        }

        $existing = $this->repository->getTreasureChest($userId, $matchId);
        $now = new DateTime();

        if ($existing) {
            $current = (int)($existing['UF_COUNT'] ?? 0);
            if ($current >= $target) {
                return;
            }

            $this->repository->updateTreasureChest((int)$existing['ID'], [
                'UF_COUNT' => $target,
                'UF_UPDATED_AT' => $now,
            ]);

            return;
        }

        $this->repository->addTreasureChest([
            'UF_USER_ID' => $userId,
            'UF_MATCH_ID' => $matchId,
            'UF_EVENT_ID' => $eventId,
            'UF_COUNT' => $target,
            'UF_STATUS' => self::CHEST_STATUS_CLOSED,
            'UF_TYPE' => 'match',
            'UF_CREATED_AT' => $now,
            'UF_UPDATED_AT' => $now,
        ]);
    }

    public function getTreasureSummary(int $userId): array
    {
        $this->migrateChm2026ChestsForUser($userId);
        $breakdown = $this->repository->getTreasureChestBreakdownForUser($userId);
        $premiumScrolls = $this->repository->getPremiumScrollBreakdownForUser($userId);
        $pennants = $this->repository->getPennantInventoryCountsForUser($userId);
        $eventId = $this->scopeService->getAnchorEventId();
        $wc26Openable = $eventId > 0
            ? $this->repository->countOpenableWc26ChestUnits($userId, $eventId)
            : 0;

        return [
            'closed_chests' => $breakdown['total'],
            'match_chests' => $breakdown['match'],
            'level_chests' => $breakdown['level'],
            'achievement_chests' => $breakdown['achievement'],
            'wc26_achievement_chests' => $breakdown['wc26_achievement'],
            'shop_chests' => $breakdown['shop'],
            'wc26_openable_chests' => $wc26Openable,
            'premium_scrolls' => $this->repository->getPremiumScrollCountForUser($userId),
            'premium_scrolls_1d' => $premiumScrolls[1] ?? 0,
            'premium_scrolls_3d' => $premiumScrolls[3] ?? 0,
            'premium_scrolls_5d' => $premiumScrolls[5] ?? 0,
            'pennant_site' => $pennants['site'] ?? 0,
            'pennant_chm2026' => $pennants['chm2026'] ?? 0,
        ];
    }

    /**
     * Закрытый сундучок за повышение уровня (идемпотентно, UF_MATCH_ID = -level).
     */
    public function grantLevelUpChest(int $userId, int $level): bool
    {
        if ($userId <= 0 || $level <= 0) {
            return false;
        }

        $syntheticMatchId = -$level;
        $existing = $this->repository->getTreasureChestByType(
            $userId,
            $syntheticMatchId,
            self::CHEST_TYPE_LEVEL
        );

        if ($existing) {
            return false;
        }

        $now = new DateTime();
        $legacy = $this->repository->getTreasureChest($userId, $syntheticMatchId);

        if ($legacy) {
            $legacyType = (string)($legacy['UF_TYPE'] ?? '');
            if ($legacyType === '' || $legacyType === self::CHEST_TYPE_MATCH) {
                $this->repository->updateTreasureChest((int)$legacy['ID'], [
                    'UF_TYPE' => self::CHEST_TYPE_LEVEL,
                    'UF_UPDATED_AT' => $now,
                ]);

                return true;
            }

            return false;
        }

        $eventId = (new GameEventScopeService())->getAnchorEventId();

        $this->repository->addTreasureChest([
            'UF_USER_ID' => $userId,
            'UF_MATCH_ID' => $syntheticMatchId,
            'UF_EVENT_ID' => $eventId > 0 ? $eventId : GameEconomyConfig::ANCHOR_EVENT_ID,
            'UF_COUNT' => 1,
            'UF_STATUS' => self::CHEST_STATUS_CLOSED,
            'UF_TYPE' => self::CHEST_TYPE_LEVEL,
            'UF_CREATED_AT' => $now,
            'UF_UPDATED_AT' => $now,
        ]);

        return true;
    }

    /**
     * Сундук ЧМ-26 за ачивку «ЧМ2026» (тот же пул лута, что матч/лавка).
     */
    public function grantWc26AchievementChests(int $userId, string $achievementCode, int $threshold, int $count): bool
    {
        if ($userId <= 0 || $achievementCode === '' || $threshold <= 0 || $count <= 0) {
            return false;
        }

        $syntheticMatchId = self::achievementSyntheticMatchId($achievementCode, $threshold);
        $existing = $this->repository->getTreasureChestByType(
            $userId,
            $syntheticMatchId,
            self::CHEST_TYPE_WC26_ACHIEVEMENT
        );
        if ($existing) {
            return false;
        }

        $legacy = $this->repository->getTreasureChestByType($userId, $syntheticMatchId, self::CHEST_TYPE_ACHIEVEMENT);
        if ($legacy && $achievementCode === 'chm2026') {
            $this->repository->updateTreasureChest((int)$legacy['ID'], [
                'UF_TYPE' => self::CHEST_TYPE_WC26_ACHIEVEMENT,
                'UF_COUNT' => max((int)($legacy['UF_COUNT'] ?? 0), $count),
                'UF_UPDATED_AT' => new DateTime(),
            ]);

            return true;
        }

        $now = new DateTime();
        $eventId = $this->scopeService->getAnchorEventId();

        $this->repository->addTreasureChest([
            'UF_USER_ID' => $userId,
            'UF_MATCH_ID' => $syntheticMatchId,
            'UF_EVENT_ID' => $eventId > 0 ? $eventId : GameEconomyConfig::ANCHOR_EVENT_ID,
            'UF_COUNT' => $count,
            'UF_STATUS' => self::CHEST_STATUS_CLOSED,
            'UF_TYPE' => self::CHEST_TYPE_WC26_ACHIEVEMENT,
            'UF_CREATED_AT' => $now,
            'UF_UPDATED_AT' => $now,
        ]);

        return true;
    }

    /**
     * Переклассифицировать сундуки ачивки «ЧМ2026» (UF_TYPE achievement → wc26_achievement).
     */
    public function migrateChm2026AchievementChestTypes(): int
    {
        $updated = $this->migrateAllCollidedAchievementChestRows();

        $dataClass = $this->repository->getTreasureChestDataClass();
        $userIds = [];
        $response = $dataClass::getList([
            'select' => ['ID', 'UF_USER_ID', 'UF_MATCH_ID', 'UF_TYPE', 'UF_STATUS'],
        ]);

        $now = new DateTime();
        while ($row = $response->fetch()) {
            if (!$this->shouldReclassifyRowAsChm2026Wc26($row)) {
                continue;
            }

            $this->repository->updateTreasureChest((int)$row['ID'], [
                'UF_TYPE' => self::CHEST_TYPE_WC26_ACHIEVEMENT,
                'UF_UPDATED_AT' => $now,
            ]);
            $updated++;

            $userId = (int)($row['UF_USER_ID'] ?? 0);
            if ($userId > 0) {
                $userIds[$userId] = true;
            }
        }

        foreach (array_keys($userIds) as $userId) {
            $updated += $this->reconcileChm2026ChestsFromClaims($userId);
        }

        return $updated;
    }

    public function migrateChm2026ChestsForUser(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        return $this->migrateCollidedAchievementChestRowsForUser($userId)
            + $this->repairLegacyMatchChestTypesForUser($userId)
            + $this->reclassifyChm2026ChestRowsForUser($userId)
            + $this->reconcileChm2026ChestsFromClaims($userId);
    }

    private function repairLegacyMatchChestTypesForUser(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        $dataClass = $this->repository->getTreasureChestDataClass();
        $updated = 0;
        $response = $dataClass::getList([
            'filter' => [
                '=UF_USER_ID' => $userId,
                '=UF_STATUS' => self::CHEST_STATUS_CLOSED,
                '>UF_MATCH_ID' => 0,
            ],
            'select' => ['ID', 'UF_TYPE', 'UF_MATCH_ID'],
        ]);

        $now = new DateTime();
        while ($row = $response->fetch()) {
            if ((string)($row['UF_TYPE'] ?? '') !== '') {
                continue;
            }

            $this->repository->updateTreasureChest((int)$row['ID'], [
                'UF_TYPE' => self::CHEST_TYPE_MATCH,
                'UF_UPDATED_AT' => $now,
            ]);
            $updated++;
        }

        return $updated;
    }

    public function migrateAllCollidedAchievementChestRows(): int
    {
        $dataClass = $this->repository->getTreasureChestDataClass();
        $userIds = [];
        $response = $dataClass::getList([
            'filter' => [
                '=UF_MATCH_ID' => self::LEGACY_COLLIDED_SYNTHETIC_MATCH_ID,
                '=UF_TYPE' => self::CHEST_TYPE_ACHIEVEMENT,
                '=UF_STATUS' => self::CHEST_STATUS_CLOSED,
            ],
            'select' => ['UF_USER_ID'],
        ]);

        while ($row = $response->fetch()) {
            $userId = (int)($row['UF_USER_ID'] ?? 0);
            if ($userId > 0) {
                $userIds[$userId] = true;
            }
        }

        $updated = 0;
        foreach (array_keys($userIds) as $userId) {
            $updated += $this->migrateCollidedAchievementChestRowsForUser($userId);
        }

        return $updated;
    }

    public function migrateCollidedAchievementChestRowsForUser(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        $dataClass = $this->repository->getTreasureChestDataClass();
        $collided = [];
        $response = $dataClass::getList([
            'filter' => [
                '=UF_USER_ID' => $userId,
                '=UF_MATCH_ID' => self::LEGACY_COLLIDED_SYNTHETIC_MATCH_ID,
                '=UF_TYPE' => self::CHEST_TYPE_ACHIEVEMENT,
                '=UF_STATUS' => self::CHEST_STATUS_CLOSED,
            ],
            'select' => ['ID', 'UF_COUNT'],
        ]);

        while ($row = $response->fetch()) {
            $collided[] = $row;
        }

        if (!$collided) {
            return 0;
        }

        $expected = $this->buildExpectedAchievementChestGrantsFromClaims($userId);
        if (!$expected) {
            return 0;
        }

        foreach ($collided as $row) {
            $dataClass::delete((int)$row['ID']);
        }

        $created = 0;
        $now = new DateTime();
        $eventId = $this->scopeService->getAnchorEventId();

        foreach ($expected as $item) {
            $matchId = self::achievementSyntheticMatchId($item['code'], $item['threshold']);
            $type = !empty($item['wc26'])
                ? self::CHEST_TYPE_WC26_ACHIEVEMENT
                : self::CHEST_TYPE_ACHIEVEMENT;

            $existing = $this->repository->getTreasureChestByType($userId, $matchId, $type);
            if ($existing) {
                continue;
            }

            $legacy = $this->repository->getTreasureChest($userId, $matchId);
            if ($legacy) {
                $this->repository->updateTreasureChest((int)$legacy['ID'], [
                    'UF_TYPE' => $type,
                    'UF_COUNT' => (int)$item['count'],
                    'UF_STATUS' => self::CHEST_STATUS_CLOSED,
                    'UF_UPDATED_AT' => $now,
                ]);
                $created++;
                continue;
            }

            $this->repository->addTreasureChest([
                'UF_USER_ID' => $userId,
                'UF_MATCH_ID' => $matchId,
                'UF_EVENT_ID' => $eventId > 0 ? $eventId : GameEconomyConfig::ANCHOR_EVENT_ID,
                'UF_COUNT' => (int)$item['count'],
                'UF_STATUS' => self::CHEST_STATUS_CLOSED,
                'UF_TYPE' => $type,
                'UF_CREATED_AT' => $now,
                'UF_UPDATED_AT' => $now,
            ]);
            $created++;
        }

        return count($collided) + $created;
    }

    /**
     * @return array<int, array{code:string,threshold:int,count:int,wc26:bool}>
     */
    private function buildExpectedAchievementChestGrantsFromClaims(int $userId): array
    {
        $claimMap = $this->repository->getAchievementClaimMapForUser($userId);
        $grants = [];

        foreach (AchievementConfig::getCatalog() as $code => $definition) {
            $claimed = (int)($claimMap[$code]['claimed_threshold'] ?? 0);
            if ($claimed <= 0) {
                continue;
            }

            foreach ((array)($definition['levels'] ?? []) as $level) {
                $threshold = (int)($level['threshold'] ?? 0);
                if ($threshold <= 0 || $threshold > $claimed) {
                    continue;
                }

                $count = (int)($level['reward']['chests'] ?? 0);
                if ($count <= 0) {
                    continue;
                }

                $grants[] = [
                    'code' => (string)$code,
                    'threshold' => $threshold,
                    'count' => $count,
                    'wc26' => AchievementConfig::grantsWc26Chest((string)$code),
                ];
            }
        }

        return $grants;
    }

    private function reclassifyChm2026ChestRowsForUser(int $userId): int
    {
        $dataClass = $this->repository->getTreasureChestDataClass();
        $updated = 0;
        $response = $dataClass::getList([
            'filter' => ['=UF_USER_ID' => $userId],
            'select' => ['ID', 'UF_MATCH_ID', 'UF_TYPE', 'UF_STATUS'],
        ]);

        $now = new DateTime();
        while ($row = $response->fetch()) {
            if (!$this->shouldReclassifyRowAsChm2026Wc26($row)) {
                continue;
            }

            $this->repository->updateTreasureChest((int)$row['ID'], [
                'UF_TYPE' => self::CHEST_TYPE_WC26_ACHIEVEMENT,
                'UF_UPDATED_AT' => $now,
            ]);
            $updated++;
        }

        return $updated;
    }

    private function reconcileChm2026ChestsFromClaims(int $userId): int
    {
        $claimMap = $this->repository->getAchievementClaimMapForUser($userId);
        $claimedThreshold = (int)($claimMap['chm2026']['claimed_threshold'] ?? 0);
        if ($claimedThreshold <= 0) {
            return 0;
        }

        $levels = AchievementConfig::getCatalog()['chm2026']['levels'] ?? [];
        $updated = 0;
        $now = new DateTime();

        foreach ($levels as $level) {
            $threshold = (int)($level['threshold'] ?? 0);
            if ($threshold <= 0 || $threshold > $claimedThreshold) {
                continue;
            }
            if ((int)($level['reward']['chests'] ?? 0) <= 0) {
                continue;
            }

            $matchId = self::achievementSyntheticMatchId('chm2026', $threshold);
            $row = $this->repository->getTreasureChest($userId, $matchId);
            if (!$row) {
                continue;
            }
            if ((string)($row['UF_TYPE'] ?? '') === self::CHEST_TYPE_WC26_ACHIEVEMENT) {
                continue;
            }

            $this->repository->updateTreasureChest((int)$row['ID'], [
                'UF_TYPE' => self::CHEST_TYPE_WC26_ACHIEVEMENT,
                'UF_UPDATED_AT' => $now,
            ]);
            $updated++;
        }

        return $updated;
    }

    private function shouldReclassifyRowAsChm2026Wc26(array $row): bool
    {
        $type = (string)($row['UF_TYPE'] ?? '');
        if ($type === self::CHEST_TYPE_WC26_ACHIEVEMENT) {
            return false;
        }
        if (in_array($type, [self::CHEST_TYPE_PENNANT, self::CHEST_TYPE_PREMIUM_SCROLL], true)) {
            return false;
        }

        $status = (string)($row['UF_STATUS'] ?? '');
        if ($status !== self::CHEST_STATUS_CLOSED) {
            return false;
        }

        return self::isChm2026AchievementSyntheticMatchId((int)($row['UF_MATCH_ID'] ?? 0));
    }

    public static function describeSyntheticMatchId(int $matchId): ?string
    {
        if ($matchId === 0) {
            return null;
        }

        if ($matchId === self::LEGACY_COLLIDED_SYNTHETIC_MATCH_ID) {
            return 'legacy_collision';
        }

        foreach (AchievementConfig::getCatalog() as $code => $definition) {
            foreach ((array)($definition['levels'] ?? []) as $level) {
                $threshold = (int)($level['threshold'] ?? 0);
                if ($threshold <= 0) {
                    continue;
                }

                $syntheticId = self::achievementSyntheticMatchId($code, $threshold);
                if ($syntheticId === $matchId) {
                    return $code . ':' . $threshold;
                }
            }
        }

        if ($matchId > 0) {
            return 'match:' . $matchId;
        }
        if ($matchId < 0 && $matchId >= -500) {
            return 'level:' . abs($matchId);
        }

        return 'synthetic:' . $matchId;
    }

    /**
     * Закрытый сундучок за ачивку (идемпотентно, ключ: user + syntheticMatchId + UF_TYPE=achievement).
     */
    public function grantAchievementChests(int $userId, string $achievementCode, int $threshold, int $count): bool
    {
        if ($userId <= 0 || $achievementCode === '' || $threshold <= 0 || $count <= 0) {
            return false;
        }

        if (AchievementConfig::grantsWc26Chest($achievementCode)) {
            return $this->grantWc26AchievementChests($userId, $achievementCode, $threshold, $count);
        }

        $syntheticMatchId = self::achievementSyntheticMatchId($achievementCode, $threshold);
        $existing = $this->repository->getTreasureChestByType($userId, $syntheticMatchId, self::CHEST_TYPE_ACHIEVEMENT);
        if ($existing) {
            return false;
        }

        $now = new DateTime();
        $eventId = (new GameEventScopeService())->getAnchorEventId();

        $this->repository->addTreasureChest([
            'UF_USER_ID' => $userId,
            'UF_MATCH_ID' => $syntheticMatchId,
            'UF_EVENT_ID' => $eventId > 0 ? $eventId : GameEconomyConfig::ANCHOR_EVENT_ID,
            'UF_COUNT' => $count,
            'UF_STATUS' => self::CHEST_STATUS_CLOSED,
            'UF_TYPE' => self::CHEST_TYPE_ACHIEVEMENT,
            'UF_CREATED_AT' => $now,
            'UF_UPDATED_AT' => $now,
        ]);

        return true;
    }

    /**
     * Закрытый сундук ЧМ-26 из лавки казны (идемпотентно по волне + валюте).
     */
    public function grantShopChest(int $userId, int $milestone, string $currency): bool
    {
        if ($userId <= 0 || $milestone <= 0) {
            return false;
        }

        $currencyCode = $currency === GameEconomyConfig::CURRENCY_RUBLIUS ? 'r' : 'p';
        $syntheticMatchId = -1000000 - ($milestone * 10) - ($currencyCode === 'r' ? 1 : 0);
        $existing = $this->repository->getTreasureChestByType($userId, $syntheticMatchId, self::CHEST_TYPE_SHOP_WC26);

        if ($existing) {
            return false;
        }

        $now = new DateTime();
        $eventId = $this->scopeService->getAnchorEventId();

        $this->repository->addTreasureChest([
            'UF_USER_ID' => $userId,
            'UF_MATCH_ID' => $syntheticMatchId,
            'UF_EVENT_ID' => $eventId > 0 ? $eventId : GameEconomyConfig::ANCHOR_EVENT_ID,
            'UF_COUNT' => 1,
            'UF_STATUS' => self::CHEST_STATUS_CLOSED,
            'UF_TYPE' => self::CHEST_TYPE_SHOP_WC26,
            'UF_CREATED_AT' => $now,
            'UF_UPDATED_AT' => $now,
        ]);

        return true;
    }

    /**
     * Неактивированный свиток премиума из лавки казны (идемпотентно по волне + длительности).
     */
    public function grantPremiumScroll(int $userId, int $milestone, int $days = 1): bool
    {
        if ($userId <= 0 || $milestone <= 0) {
            return false;
        }

        if (!in_array($days, [1, 3, 5], true)) {
            $days = 1;
        }

        $syntheticMatchId = -2000000 - ($milestone * 100) - $days;
        $existing = $this->repository->getTreasureChestByType(
            $userId,
            $syntheticMatchId,
            self::CHEST_TYPE_PREMIUM_SCROLL
        );

        if ($existing) {
            return false;
        }

        $now = new DateTime();
        $eventId = $this->scopeService->getAnchorEventId();

        $this->repository->addTreasureChest([
            'UF_USER_ID' => $userId,
            'UF_MATCH_ID' => $syntheticMatchId,
            'UF_EVENT_ID' => $eventId > 0 ? $eventId : GameEconomyConfig::ANCHOR_EVENT_ID,
            'UF_COUNT' => 1,
            'UF_STATUS' => self::CHEST_STATUS_INVENTORY,
            'UF_TYPE' => self::CHEST_TYPE_PREMIUM_SCROLL,
            'UF_CREATED_AT' => $now,
            'UF_UPDATED_AT' => $now,
        ]);

        return true;
    }

    /**
     * Вымпел в инвентарь (идемпотентно по коду, напр. site / chm2026).
     */
    public function grantPennant(int $userId, string $pennantCode): bool
    {
        $pennantCode = trim($pennantCode);
        if ($userId <= 0 || $pennantCode === '') {
            return false;
        }

        $syntheticMatchId = $this->resolvePennantSyntheticMatchId($pennantCode);
        $existing = $this->repository->getTreasureChestByType(
            $userId,
            $syntheticMatchId,
            self::CHEST_TYPE_PENNANT
        );

        if ($existing) {
            return false;
        }

        $now = new DateTime();
        $eventId = $this->scopeService->getAnchorEventId();

        $this->repository->addTreasureChest([
            'UF_USER_ID' => $userId,
            'UF_MATCH_ID' => $syntheticMatchId,
            'UF_EVENT_ID' => $eventId > 0 ? $eventId : GameEconomyConfig::ANCHOR_EVENT_ID,
            'UF_COUNT' => 1,
            'UF_STATUS' => self::CHEST_STATUS_INVENTORY,
            'UF_TYPE' => self::CHEST_TYPE_PENNANT,
            'UF_CREATED_AT' => $now,
            'UF_UPDATED_AT' => $now,
        ]);

        return true;
    }

    private function resolvePennantSyntheticMatchId(string $pennantCode): int
    {
        if (isset(self::PENNANT_SYNTHETIC_MATCH_IDS[$pennantCode])) {
            return self::PENNANT_SYNTHETIC_MATCH_IDS[$pennantCode];
        }

        return -3000000 - abs((int)crc32($pennantCode));
    }
}

