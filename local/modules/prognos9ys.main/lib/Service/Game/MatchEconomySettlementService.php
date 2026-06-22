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

    public function isMatchEconomicallySettled(int $matchId): bool
    {
        if ($matchId <= 0) {
            return false;
        }

        if ($this->repository->hasMatchEconomySettlement($matchId)) {
            return true;
        }

        return $this->isLegacyEconomySettled($matchId);
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
