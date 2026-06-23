<?php

namespace Prognos9ys\Main\Service\Game;

use Bitrix\Main\Type\DateTime;
use Prognos9ys\Main\Model\Repository\GameEconomyRepository;

class TreasureService
{
    public const CHEST_STATUS_CLOSED = 'closed';
    public const CHEST_STATUS_INVENTORY = 'inventory';
    public const CHEST_TYPE_MATCH = 'match';
    public const CHEST_TYPE_LEVEL = 'level';
    public const CHEST_TYPE_ACHIEVEMENT = 'achievement';
    public const CHEST_TYPE_SHOP_WC26 = 'shop_wc26';
    public const CHEST_TYPE_PREMIUM_SCROLL = 'premium_scroll';
    public const CHEST_TYPE_PENNANT = 'pennant';

    /** @var array<string, int> */
    private const PENNANT_SYNTHETIC_MATCH_IDS = [
        'site' => -3000001,
        'chm2026' => -3000002,
    ];

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
        $breakdown = $this->repository->getTreasureChestBreakdownForUser($userId);
        $premiumScrolls = $this->repository->getPremiumScrollBreakdownForUser($userId);
        $pennants = $this->repository->getPennantInventoryCountsForUser($userId);

        return [
            'closed_chests' => $breakdown['total'],
            'match_chests' => $breakdown['match'],
            'level_chests' => $breakdown['level'],
            'achievement_chests' => $breakdown['achievement'],
            'shop_chests' => $breakdown['shop'],
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
     * Закрытый сундучок за ачивку (идемпотентно, ключ: user + syntheticMatchId + UF_TYPE=achievement).
     */
    public function grantAchievementChests(int $userId, string $achievementCode, int $threshold, int $count): bool
    {
        if ($userId <= 0 || $achievementCode === '' || $threshold <= 0 || $count <= 0) {
            return false;
        }

        $syntheticMatchId = -abs((int)crc32($achievementCode . ':' . $threshold));
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

