<?php

namespace Prognos9ys\Main\Service\Game;

use Bitrix\Main\Loader;
use Bitrix\Main\Type\DateTime;
use Prognos9ys\Main\Controller\ApiException;
use Prognos9ys\Main\Model\Repository\GameEconomyRepository;

class ExperienceService
{
    private const RESULT_IBLOCK_ID = 7;
    private const MATCHES_IBLOCK_ID = 2;

    private GameEconomyRepository $repository;
    private UserProgressService $progressService;
    private GameEventScopeService $eventScope;
    private ?MatchEconomySettlementService $settlementService = null;

    public function __construct(
        ?GameEconomyRepository $repository = null,
        ?UserProgressService $progressService = null,
        ?GameEventScopeService $eventScope = null
    ) {
        $this->repository = $repository ?? new GameEconomyRepository();
        $this->progressService = $progressService ?? new UserProgressService($this->repository);
        $this->eventScope = $eventScope ?? new GameEventScopeService();
    }

    public function syncPendingForMatch(int $matchId): void
    {
        if ($matchId <= 0 || !Loader::includeModule('iblock')) {
            return;
        }

        if (!$this->eventScope->isMatchEligible($matchId)) {
            return;
        }

        if (!$this->isMatchFinished($matchId)) {
            return;
        }

        $response = \CIBlockElement::GetList(
            [],
            [
                'IBLOCK_ID' => self::RESULT_IBLOCK_ID,
                'PROPERTY_match_id' => $matchId,
            ],
            false,
            false,
            ['ID', 'PROPERTY_user_id', 'PROPERTY_all']
        );

        while ($row = $response->GetNext()) {
            $userId = (int)$row['PROPERTY_USER_ID_VALUE'];
            $points = (float)$row['PROPERTY_ALL_VALUE'];

            if ($userId <= 0) {
                continue;
            }

            $this->upsertPending($userId, $matchId, $points);
        }
    }

    public function syncAllFinishedMatches(): int
    {
        if (!Loader::includeModule('iblock')) {
            return 0;
        }

        $eligibleEventIds = $this->eventScope->getEligibleEventIds();

        if (!$eligibleEventIds) {
            return 0;
        }

        $filter = [
            'IBLOCK_ID' => self::MATCHES_IBLOCK_ID,
            'PROPERTY_EVENTS' => $eligibleEventIds,
        ];

        if (GameEconomyConfig::isTestMatchNumberLimitEnabled()) {
            $filter['>=PROPERTY_number'] = GameEconomyConfig::getTestMatchNumberMin();
            $filter['<=PROPERTY_number'] = GameEconomyConfig::getTestMatchNumberMax();
        }

        $settlementService = new MatchEconomySettlementService(null, $this->eventScope);
        $count = 0;
        $response = \CIBlockElement::GetList(
            [],
            $filter,
            false,
            false,
            ['ID']
        );

        while ($row = $response->GetNext()) {
            $matchId = (int)($row['ID'] ?? 0);
            if ($matchId <= 0 || !$settlementService->isMatchEconomicallySettled($matchId)) {
                continue;
            }

            $this->syncPendingForMatch($matchId);
            $count++;
        }

        return $count;
    }

    /**
     * @param int[] $matchIds
     * @return array<int, array{points:float,status:string,can_claim:bool}>
     */
    public function getPendingMapForUser(int $userId, array $matchIds): array
    {
        if ($userId <= 0 || !$matchIds) {
            return [];
        }

        $rows = $this->repository->getPendingXpMap($userId, $matchIds);
        $map = [];

        foreach ($rows as $matchId => $row) {
            if (!$this->eventScope->isMatchEligible($matchId)) {
                continue;
            }

            $status = (string)$row['UF_STATUS'];
            $map[$matchId] = [
                'points' => round((float)$row['UF_POINTS'], 1),
                'status' => $status,
                'can_claim' => $status === GameEconomyConfig::XP_STATUS_PENDING,
            ];
        }

        return $map;
    }

    /**
     * @return array{count:int,points:float}
     */
    public function getPendingSummaryForUser(int $userId): array
    {
        if ($userId <= 0) {
            return ['count' => 0, 'points' => 0.0];
        }

        $summary = $this->summarizePendingRows($userId);

        if ($summary['count'] === 0) {
            $this->syncPendingForUser($userId);
            $summary = $this->summarizePendingRows($userId);
        }

        return $summary;
    }

    public function claimAll(int $userId, bool $skipSync = false): array
    {
        if ($userId <= 0) {
            throw new ApiException('Некорректные параметры', 400);
        }

        if (!$skipSync) {
            $this->syncPendingForUser($userId);
        }

        $pendingRows = $this->repository->getPendingXpListForUser($userId, GameEconomyConfig::XP_STATUS_PENDING);
        $matchIds = [];
        foreach ($pendingRows as $row) {
            $matchId = (int)($row['UF_MATCH_ID'] ?? 0);
            if ($matchId > 0) {
                $matchIds[$matchId] = $matchId;
            }
        }

        if ($matchIds) {
            $this->eventScope->preloadMatches(array_values($matchIds));
            $this->getSettlementService()->preloadSettlement(array_values($matchIds));
        }

        $oldProgress = $this->progressService->getSummary($userId);
        $oldLevel = (int)$oldProgress['level'];
        $totalPoints = 0.0;
        $claimedMatches = [];
        $settlementService = $this->getSettlementService();

        foreach ($pendingRows as $row) {
            $matchId = (int)($row['UF_MATCH_ID'] ?? 0);

            if ($matchId <= 0 || !$this->eventScope->isMatchEligible($matchId) || !$settlementService->isMatchEconomicallySettled($matchId)) {
                continue;
            }

            if ((string)$row['UF_STATUS'] !== GameEconomyConfig::XP_STATUS_PENDING) {
                continue;
            }

            $points = round((float)($row['UF_POINTS'] ?? 0), 1);

            if ($points <= 0) {
                continue;
            }

            $this->repository->updatePendingXp((int)$row['ID'], [
                'UF_STATUS' => GameEconomyConfig::XP_STATUS_CLAIMED,
                'UF_CLAIMED_AT' => new DateTime(),
            ]);

            $totalPoints += $points;
            $claimedMatches[] = [
                'match_id' => $matchId,
                'points' => $points,
            ];
        }

        if ($totalPoints <= 0) {
            throw new ApiException('Нет доступного опыта для получения', 404);
        }

        $newProgress = $this->progressService->addXp($userId, $totalPoints);
        $newLevel = (int)$newProgress['level'];
        $levelRewards = (new LevelUpRewardService($this->repository))
            ->grantForLevelRange($userId, $oldLevel, $newLevel);

        $levelsGained = [];

        if ($newLevel > $oldLevel) {
            for ($level = $oldLevel + 1; $level <= $newLevel; $level++) {
                $levelsGained[] = $level;
            }
        }

        return [
            'claimed_points' => round($totalPoints, 1),
            'claimed_count' => count($claimedMatches),
            'matches' => $claimedMatches,
            'old_level' => $oldLevel,
            'new_level' => $newLevel,
            'levels_gained' => $levelsGained,
            'level_up' => $newLevel > $oldLevel,
            'level_rewards' => $levelRewards,
            'progress' => $newProgress,
        ];
    }

    public function claim(int $userId, int $matchId): array
    {
        if ($userId <= 0 || $matchId <= 0) {
            throw new ApiException('Некорректные параметры', 400);
        }

        if (!$this->eventScope->isMatchEligible($matchId)) {
            throw new ApiException('Опыт начисляется только за матчи ЧМ-2026', 422);
        }

        if (!$this->isMatchFinished($matchId)) {
            throw new ApiException('Опыт можно получить только после завершения матча', 422);
        }

        $pending = $this->repository->getPendingXp($userId, $matchId);

        if (!$pending) {
            $this->syncPendingForMatch($matchId);
            $pending = $this->repository->getPendingXp($userId, $matchId);
        }

        if (!$pending) {
            throw new ApiException('Нет доступного опыта за этот матч', 404);
        }

        if ((string)$pending['UF_STATUS'] === GameEconomyConfig::XP_STATUS_CLAIMED) {
            throw new ApiException('Опыт за этот матч уже получен', 409);
        }

        $points = round((float)$pending['UF_POINTS'], 1);
        $oldProgress = $this->progressService->getSummary($userId);

        $this->repository->updatePendingXp((int)$pending['ID'], [
            'UF_STATUS' => GameEconomyConfig::XP_STATUS_CLAIMED,
            'UF_CLAIMED_AT' => new DateTime(),
        ]);

        $newProgress = $this->progressService->addXp($userId, $points);
        $levelRewards = (new LevelUpRewardService($this->repository))
            ->grantForLevelRange($userId, (int)$oldProgress['level'], (int)$newProgress['level']);

        return [
            'claimed_points' => $points,
            'match_id' => $matchId,
            'level_up' => $newProgress['level'] > $oldProgress['level'],
            'level_rewards' => $levelRewards,
            'progress' => $newProgress,
        ];
    }

    /**
     * @return array{count:int,points:float}
     */
    private function summarizePendingRows(int $userId): array
    {
        $rows = $this->repository->getPendingXpListForUser($userId, GameEconomyConfig::XP_STATUS_PENDING);
        $count = 0;
        $points = 0.0;

        foreach ($rows as $row) {
            $matchId = (int)($row['UF_MATCH_ID'] ?? 0);

            if ($matchId <= 0 || !$this->eventScope->isMatchEligible($matchId) || !$this->isMatchFinished($matchId)) {
                continue;
            }

            $matchPoints = (float)($row['UF_POINTS'] ?? 0);

            if ($matchPoints <= 0) {
                continue;
            }

            $count++;
            $points += $matchPoints;
        }

        return [
            'count' => $count,
            'points' => round($points, 1),
        ];
    }

    private function syncPendingForUser(int $userId): void
    {
        if ($userId <= 0 || !Loader::includeModule('iblock')) {
            return;
        }

        $response = \CIBlockElement::GetList(
            [],
            [
                'IBLOCK_ID' => self::RESULT_IBLOCK_ID,
                'PROPERTY_user_id' => $userId,
            ],
            false,
            false,
            ['PROPERTY_match_id', 'PROPERTY_all']
        );

        while ($row = $response->GetNext()) {
            $matchId = (int)$row['PROPERTY_MATCH_ID_VALUE'];
            $points = (float)$row['PROPERTY_ALL_VALUE'];

            if ($matchId <= 0 || !$this->eventScope->isMatchEligible($matchId)) {
                continue;
            }

            $this->upsertPending($userId, $matchId, $points);
        }
    }

    private function upsertPending(int $userId, int $matchId, float $points): void
    {
        $existing = $this->repository->getPendingXp($userId, $matchId);

        if ($existing) {
            if ((string)$existing['UF_STATUS'] === GameEconomyConfig::XP_STATUS_CLAIMED) {
                return;
            }

            $this->repository->updatePendingXp((int)$existing['ID'], [
                'UF_POINTS' => round($points, 1),
            ]);

            return;
        }

        $this->repository->addPendingXp([
            'UF_USER_ID' => $userId,
            'UF_MATCH_ID' => $matchId,
            'UF_POINTS' => round($points, 1),
            'UF_STATUS' => GameEconomyConfig::XP_STATUS_PENDING,
            'UF_CREATED_AT' => new DateTime(),
        ]);
    }

    private function isMatchFinished(int $matchId): bool
    {
        return $this->getSettlementService()->isMatchEconomicallySettled($matchId);
    }

    private function getSettlementService(): MatchEconomySettlementService
    {
        return $this->settlementService ??= new MatchEconomySettlementService($this->repository, $this->eventScope);
    }
}
