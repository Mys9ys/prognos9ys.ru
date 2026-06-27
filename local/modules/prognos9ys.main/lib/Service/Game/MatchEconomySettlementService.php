<?php

namespace Prognos9ys\Main\Service\Game;

use Bitrix\Main\Loader;
use Bitrix\Main\Type\DateTime;
use Prognos9ys\Main\Model\Repository\GameEconomyRepository;

/**
 * Фиксация матчей, по которым внесён результат и прогнан пересчёт (как тики вкладов/займов).
 */
class MatchEconomySettlementService
{
    private GameEconomyRepository $repository;
    private GameEventScopeService $scopeService;

    /** @var array<int, bool> */
    private array $settledCache = [];

    public function __construct(
        ?GameEconomyRepository $repository = null,
        ?GameEventScopeService $scopeService = null
    ) {
        $this->repository = $repository ?? new GameEconomyRepository();
        $this->scopeService = $scopeService ?? new GameEventScopeService();
    }

    public function markFromCalc(int $matchId): bool
    {
        if ($matchId <= 0 || !$this->scopeService->isMatchEligible($matchId)) {
            return false;
        }

        if (!$this->isMatchResultEntered($matchId)) {
            return false;
        }

        if ($this->repository->hasMatchEconomySettlement($matchId)) {
            return false;
        }

        $eventId = $this->scopeService->getEventIdForMatch($matchId);
        $matchNumber = $this->scopeService->getMatchNumber($matchId);

        if ($eventId <= 0 || $matchNumber <= 0) {
            return false;
        }

        $this->repository->addMatchEconomySettlement([
            'UF_MATCH_ID' => $matchId,
            'UF_EVENT_ID' => $eventId,
            'UF_MATCH_NUMBER' => $matchNumber,
            'UF_SETTLED_AT' => new DateTime(),
        ]);

        return true;
    }

    /**
     * @param int[] $matchIds
     */
    public function preloadSettlement(array $matchIds): void
    {
        $matchIds = array_values(array_unique(array_filter(array_map('intval', $matchIds))));

        if (!$matchIds) {
            return;
        }

        $toLoad = [];
        foreach ($matchIds as $matchId) {
            if (!array_key_exists($matchId, $this->settledCache)) {
                $toLoad[$matchId] = $matchId;
            }
        }

        if (!$toLoad) {
            return;
        }

        $registry = $this->repository->getSettledMatchIds(array_values($toLoad));
        $needLegacy = [];

        foreach ($toLoad as $matchId) {
            if (isset($registry[$matchId])) {
                $this->settledCache[$matchId] = true;
                continue;
            }

            $needLegacy[$matchId] = $matchId;
        }

        if ($needLegacy) {
            foreach ($this->batchLegacySettled(array_values($needLegacy)) as $matchId => $settled) {
                $this->settledCache[$matchId] = $settled;
            }
        }
    }

    public function isMatchEconomicallySettled(int $matchId): bool
    {
        if ($matchId <= 0) {
            return false;
        }

        if (array_key_exists($matchId, $this->settledCache)) {
            return $this->settledCache[$matchId];
        }

        if ($this->repository->hasMatchEconomySettlement($matchId)) {
            $this->settledCache[$matchId] = true;

            return true;
        }

        $legacy = $this->isLegacyEconomySettled($matchId);
        $this->settledCache[$matchId] = $legacy;

        return $legacy;
    }

    /**
     * @return array{id:int,number:int}
     */
    public function getLastSettledMatchForEvent(int $eventId): array
    {
        if ($eventId <= 0) {
            return ['id' => 0, 'number' => 0];
        }

        $fromRegistry = $this->repository->getLastMatchEconomySettlementForEvent($eventId);
        if (($fromRegistry['number'] ?? 0) > 0) {
            return $fromRegistry;
        }

        return $this->findLegacyLastSettledMatchForEvent($eventId);
    }

    public function isMatchResultEntered(int $matchId): bool
    {
        if ($matchId <= 0 || !Loader::includeModule('iblock')) {
            return false;
        }

        $row = \CIBlockElement::GetList(
            [],
            ['IBLOCK_ID' => 2, 'ID' => $matchId],
            false,
            false,
            [
                'ID',
                'PROPERTY_result',
                'PROPERTY_goal_home',
                'PROPERTY_goal_guest',
                'PROPERTY_maps_home',
                'PROPERTY_maps_guest',
            ]
        )->GetNext();

        if (!$row) {
            return false;
        }

        $outcome = trim((string)($row['PROPERTY_RESULT_VALUE'] ?? ''));
        if (in_array($outcome, ['п1', 'н', 'п2'], true)) {
            return true;
        }

        $home = $row['PROPERTY_GOAL_HOME_VALUE'] ?? $row['PROPERTY_MAPS_HOME_VALUE'] ?? null;
        $guest = $row['PROPERTY_GOAL_GUEST_VALUE'] ?? $row['PROPERTY_MAPS_GUEST_VALUE'] ?? null;

        return $home !== null && $home !== '' && $guest !== null && $guest !== '';
    }

    /**
     * @param int[] $matchIds
     * @return array<int, bool>
     */
    private function batchLegacySettled(array $matchIds): array
    {
        $matchIds = array_values(array_unique(array_filter(array_map('intval', $matchIds))));
        $out = array_fill_keys($matchIds, false);

        if (!$matchIds || !Loader::includeModule('iblock')) {
            return $out;
        }

        $resultEntered = [];
        $response = \CIBlockElement::GetList(
            [],
            ['IBLOCK_ID' => 2, 'ID' => $matchIds],
            false,
            false,
            [
                'ID',
                'PROPERTY_result',
                'PROPERTY_goal_home',
                'PROPERTY_goal_guest',
                'PROPERTY_maps_home',
                'PROPERTY_maps_guest',
            ]
        );

        while ($row = $response->GetNext()) {
            $matchId = (int)($row['ID'] ?? 0);
            if ($matchId <= 0) {
                continue;
            }

            $outcome = trim((string)($row['PROPERTY_RESULT_VALUE'] ?? ''));
            if (in_array($outcome, ['п1', 'н', 'п2'], true)) {
                $resultEntered[$matchId] = true;
                continue;
            }

            $home = $row['PROPERTY_GOAL_HOME_VALUE'] ?? $row['PROPERTY_MAPS_HOME_VALUE'] ?? null;
            $guest = $row['PROPERTY_GOAL_GUEST_VALUE'] ?? $row['PROPERTY_MAPS_GUEST_VALUE'] ?? null;
            $resultEntered[$matchId] = $home !== null && $home !== '' && $guest !== null && $guest !== '';
        }

        $withResults = array_keys(array_filter($resultEntered));
        if (!$withResults) {
            return $out;
        }

        $resultIbId = (int)(\CIBlock::GetList([], ['CODE' => 'result'], false)->Fetch()['ID'] ?: 7);
        $calcResponse = \CIBlockElement::GetList(
            [],
            [
                'IBLOCK_ID' => $resultIbId,
                'PROPERTY_match_id' => $withResults,
            ],
            false,
            false,
            ['ID', 'PROPERTY_match_id']
        );

        $hasCalc = [];
        while ($row = $calcResponse->GetNext()) {
            $matchId = (int)($row['PROPERTY_MATCH_ID_VALUE'] ?? 0);
            if ($matchId > 0) {
                $hasCalc[$matchId] = true;
            }
        }

        foreach ($matchIds as $matchId) {
            $out[$matchId] = ($resultEntered[$matchId] ?? false) && ($hasCalc[$matchId] ?? false);
        }

        return $out;
    }

    private function isLegacyEconomySettled(int $matchId): bool
    {
        if (!$this->isMatchResultEntered($matchId)) {
            return false;
        }

        if (!Loader::includeModule('iblock')) {
            return false;
        }

        $resultIbId = (int)(\CIBlock::GetList([], ['CODE' => 'result'], false)->Fetch()['ID'] ?: 7);
        $row = \CIBlockElement::GetList(
            [],
            [
                'IBLOCK_ID' => $resultIbId,
                'PROPERTY_match_id' => $matchId,
            ],
            false,
            ['nTopCount' => 1],
            ['ID']
        )->Fetch();

        return (bool)$row;
    }

    /**
     * @return array{id:int,number:int}
     */
    private function findLegacyLastSettledMatchForEvent(int $eventId): array
    {
        if (!Loader::includeModule('iblock')) {
            return ['id' => 0, 'number' => 0];
        }

        $filter = [
            'IBLOCK_ID' => 2,
            'PROPERTY_events' => $eventId,
        ];

        if (GameEconomyConfig::isTestMatchNumberLimitEnabled()) {
            $filter['>=PROPERTY_number'] = GameEconomyConfig::getTestMatchNumberMin();
            $filter['<=PROPERTY_number'] = GameEconomyConfig::getTestMatchNumberMax();
        }

        $response = \CIBlockElement::GetList(
            ['PROPERTY_number' => 'DESC', 'ID' => 'DESC'],
            $filter,
            false,
            false,
            ['ID', 'PROPERTY_events', 'PROPERTY_number']
        );

        while ($row = $response->GetNext()) {
            $matchId = (int)($row['ID'] ?? 0);
            if ($matchId <= 0 || !$this->isLegacyEconomySettled($matchId)) {
                continue;
            }

            $matchNumber = (int)($row['PROPERTY_NUMBER_VALUE'] ?? 0);
            if ($matchNumber <= 0 || !$this->scopeService->isMatchInScope($eventId, $matchNumber)) {
                continue;
            }

            return ['id' => $matchId, 'number' => $matchNumber];
        }

        return ['id' => 0, 'number' => 0];
    }
}
