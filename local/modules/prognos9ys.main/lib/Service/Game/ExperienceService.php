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
            'ACTIVE' => 'N',
            'PROPERTY_EVENTS' => $eligibleEventIds,
        ];

        if (GameEconomyConfig::isTestMatchNumberLimitEnabled()) {
            $filter['PROPERTY_number'] = GameEconomyConfig::TEST_ONLY_MATCH_NUMBER;
        }

        $count = 0;
        $response = \CIBlockElement::GetList(
            [],
            $filter,
            false,
            false,
            ['ID']
        );

        while ($row = $response->GetNext()) {
            $this->syncPendingForMatch((int)$row['ID']);
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

    public function claim(int $userId, int $matchId): array
    {
        if ($userId <= 0 || $matchId <= 0) {
            throw new ApiException('Некорректные параметры', 400);
        }

        if (!$this->eventScope->isMatchEligible($matchId)) {
            throw new ApiException('Опыт начисляется только за матчи ЧМ-2026 и последующих турниров', 422);
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

        return [
            'claimed_points' => $points,
            'match_id' => $matchId,
            'level_up' => $newProgress['level'] > $oldProgress['level'],
            'progress' => $newProgress,
        ];
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
        if (!Loader::includeModule('iblock')) {
            return false;
        }

        $row = \CIBlockElement::GetList(
            [],
            [
                'IBLOCK_ID' => self::MATCHES_IBLOCK_ID,
                'ID' => $matchId,
            ],
            false,
            false,
            ['ID', 'ACTIVE']
        )->GetNext();

        return $row && (string)$row['ACTIVE'] === 'N';
    }
}
