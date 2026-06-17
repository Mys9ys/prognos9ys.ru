<?php

namespace Prognos9ys\Main\Service\Game;

use Bitrix\Main\Type\DateTime;
use Prognos9ys\Main\Model\Repository\GameEconomyRepository;

class TreasureService
{
    public const CHEST_STATUS_CLOSED = 'closed';
    public const CHEST_TYPE_ACHIEVEMENT = 'achievement';

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
            'UF_CREATED_AT' => $now,
            'UF_UPDATED_AT' => $now,
        ]);
    }

    public function getTreasureSummary(int $userId): array
    {
        return [
            'closed_chests' => $this->repository->getTreasureChestTotalForUser($userId),
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
        $existing = $this->repository->getTreasureChest($userId, $syntheticMatchId);

        if ($existing) {
            return false;
        }

        $now = new DateTime();
        $eventId = (new GameEventScopeService())->getAnchorEventId();

        $this->repository->addTreasureChest([
            'UF_USER_ID' => $userId,
            'UF_MATCH_ID' => $syntheticMatchId,
            'UF_EVENT_ID' => $eventId > 0 ? $eventId : GameEconomyConfig::ANCHOR_EVENT_ID,
            'UF_COUNT' => 1,
            'UF_STATUS' => self::CHEST_STATUS_CLOSED,
            'UF_TYPE' => 'level',
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
}

