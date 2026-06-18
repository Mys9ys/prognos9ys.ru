<?php

namespace Prognos9ys\Main\Service\Football;

use Bitrix\Main\Loader;

/**
 * Последние исходы команды в рамках одного события (турнира).
 */
class TeamFormService
{
    private const MATCHES_IBLOCK_ID = 2;
    private const DEFAULT_LIMIT = 5;

    /**
     * @return array{home: string[], guest: string[]}
     */
    public function getTeamForms(
        int $eventId,
        int $homeTeamId,
        int $guestTeamId,
        int $beforeMatchNumber,
        int $limit = self::DEFAULT_LIMIT
    ): array {
        if ($eventId <= 0 || $beforeMatchNumber <= 1 || !Loader::includeModule('iblock')) {
            return ['home' => [], 'guest' => []];
        }

        $history = $this->loadFinishedMatchesBefore($eventId, $beforeMatchNumber);

        return [
            'home' => $this->buildForm($history, $homeTeamId, $limit),
            'guest' => $this->buildForm($history, $guestTeamId, $limit),
        ];
    }

    /**
     * @return list<array{home_id:int,guest_id:int,goal_home:int,goal_guest:int}>
     */
    private function loadFinishedMatchesBefore(int $eventId, int $beforeMatchNumber): array
    {
        $rows = [];
        $response = \CIBlockElement::GetList(
            ['PROPERTY_number' => 'ASC', 'ID' => 'ASC'],
            [
                'IBLOCK_ID' => self::MATCHES_IBLOCK_ID,
                'PROPERTY_EVENTS' => $eventId,
                'ACTIVE' => 'N',
            ],
            false,
            false,
            [
                'ID',
                'PROPERTY_home',
                'PROPERTY_guest',
                'PROPERTY_goal_home',
                'PROPERTY_goal_guest',
                'PROPERTY_number',
            ]
        );

        while ($row = $response->GetNext()) {
            $matchNumber = (int)($row['PROPERTY_NUMBER_VALUE'] ?? 0);
            if ($matchNumber <= 0 || $matchNumber >= $beforeMatchNumber) {
                continue;
            }

            $rows[] = [
                'home_id' => (int)($row['PROPERTY_HOME_VALUE'] ?? 0),
                'guest_id' => (int)($row['PROPERTY_GUEST_VALUE'] ?? 0),
                'goal_home' => (int)($row['PROPERTY_GOAL_HOME_VALUE'] ?? 0),
                'goal_guest' => (int)($row['PROPERTY_GOAL_GUEST_VALUE'] ?? 0),
            ];
        }

        return $rows;
    }

    /**
     * @param list<array{home_id:int,guest_id:int,goal_home:int,goal_guest:int}> $matches
     * @return string[]
     */
    private function buildForm(array $matches, int $teamId, int $limit): array
    {
        if ($teamId <= 0 || $limit <= 0) {
            return [];
        }

        $outcomes = [];
        foreach ($matches as $match) {
            $outcome = $this->resolveOutcomeForTeam($match, $teamId);
            if ($outcome !== null) {
                $outcomes[] = $outcome;
            }
        }

        if (count($outcomes) <= $limit) {
            return $outcomes;
        }

        return array_slice($outcomes, -$limit);
    }

    /**
     * @param array{home_id:int,guest_id:int,goal_home:int,goal_guest:int} $match
     */
    private function resolveOutcomeForTeam(array $match, int $teamId): ?string
    {
        $homeId = $match['home_id'];
        $guestId = $match['guest_id'];

        if ($teamId !== $homeId && $teamId !== $guestId) {
            return null;
        }

        $homeGoals = $match['goal_home'];
        $guestGoals = $match['goal_guest'];

        if ($homeGoals === $guestGoals) {
            return 'Н';
        }

        if ($teamId === $homeId) {
            return $homeGoals > $guestGoals ? 'В' : 'П';
        }

        return $guestGoals > $homeGoals ? 'В' : 'П';
    }
}
