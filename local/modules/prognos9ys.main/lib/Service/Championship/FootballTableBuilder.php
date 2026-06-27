<?php

namespace Prognos9ys\Main\Service\Championship;

use Prognos9ys\Main\Model\Repository\CountriesRepository;

class FootballTableBuilder
{
    /**
     * @param list<array<string, mixed>> $matches
     * @param array<int, array<string, mixed>> $teamsById
     * @param array<int, string> $userPrognosis
     * @param array<int, string> $userResults
     * @return array{
     *   groups: array,
     *   thirdPlaces: list<array>,
     *   groupMatches: array,
     *   playoffRounds: list<array>,
     *   playoffBracket: array
     * }
     */
    public function build(
        array $matches,
        array $teamsById,
        array $userPrognosis = [],
        array $userResults = []
    ): array {
        $groupMatches = [];
        $playoffMatches = [];

        foreach ($matches as $match) {
            if (PlayoffSlotHelper::isPlayoffMatch($match)) {
                $playoffMatches[] = $match;
            } else {
                $groupMatches[] = $match;
            }
        }

        [$groups, $thirdPlaces, $groupIndex] = $this->buildGroupStandings($groupMatches, $teamsById);
        $groupMatchesPayload = $this->buildGroupMatchesPayload(
            $groupMatches,
            $teamsById,
            $userPrognosis,
            $userResults,
            $groupIndex
        );

        [$playoffRounds, $playoffBracket] = $this->buildPlayoffPayload(
            $playoffMatches,
            $teamsById,
            $userPrognosis,
            $userResults
        );

        return [
            'groups' => $groups,
            'thirdPlaces' => $thirdPlaces,
            'groupMatches' => $groupMatchesPayload,
            'playoffRounds' => $playoffRounds,
            'playoffBracket' => $playoffBracket,
        ];
    }

    /**
     * @param list<array<string, mixed>> $groupMatches
     * @param array<int, array<string, mixed>> $teamsById
     * @return array{0: array, 1: list<array>, 2: array<int, string>}
     */
    private function buildGroupStandings(array $groupMatches, array $teamsById): array
    {
        $stats = [];
        $groupByTeam = [];

        foreach ($groupMatches as $match) {
            $homeId = (int)$match['home_id'];
            $guestId = (int)$match['guest_id'];
            if ($homeId <= 0 || $guestId <= 0) {
                continue;
            }

            $group = (string)$match['group'];
            if ($group !== '' && $group !== 'N') {
                $groupByTeam[$homeId] = $group;
                $groupByTeam[$guestId] = $group;
            }

            $this->ensureTeamStats($stats, $homeId, $teamsById);
            $this->ensureTeamStats($stats, $guestId, $teamsById);

            $result = (string)$match['result'];
            $stats[$homeId]['score'] += $this->getScore($result, 'home');
            $stats[$guestId]['score'] += $this->getScore($result);

            if ($group === 'N' || $result !== '') {
                $stats[$homeId]['matches']++;
                $stats[$guestId]['matches']++;
            }

            $this->applyWin($stats, $result, $homeId, $guestId);

            $goalHome = (int)($match['goal_home'] ?? 0);
            $goalGuest = (int)($match['goal_guest'] ?? 0);
            $stats[$homeId]['plus'] += $goalHome;
            $stats[$guestId]['plus'] += $goalGuest;
            $stats[$homeId]['minus'] += $goalGuest;
            $stats[$guestId]['minus'] += $goalHome;
            $stats[$homeId]['diff'] = $stats[$homeId]['plus'] - $stats[$homeId]['minus'];
            $stats[$guestId]['diff'] = $stats[$guestId]['plus'] - $stats[$guestId]['minus'];
        }

        if (!$stats) {
            return [[], [], []];
        }

        if (count($groupByTeam) > 1) {
            $groupTeams = [];
            foreach ($groupByTeam as $teamId => $groupName) {
                if (isset($stats[$teamId])) {
                    $groupTeams[$groupName][] = $stats[$teamId];
                }
            }

            $groups = [];
            foreach ($groupTeams as $groupName => $teams) {
                $groups[$groupName] = $this->sortStandings($teams);
            }
            ksort($groups, SORT_NATURAL);

            $thirdPlaces = $this->buildThirdPlacesOverview($groups);

            return [$groups, $thirdPlaces, $groupByTeam];
        }

        return [[0 => $this->sortStandings(array_values($stats))], [], $groupByTeam];
    }

    /**
     * @param list<array<string, mixed>> $groupMatches
     * @param array<int, string> $groupByTeam
     */
    private function buildGroupMatchesPayload(
        array $groupMatches,
        array $teamsById,
        array $userPrognosis,
        array $userResults,
        array $groupByTeam
    ): array {
        if (count($groupByTeam) <= 1) {
            return [];
        }

        $payload = [];

        foreach ($groupMatches as $match) {
            $group = (string)$match['group'];
            if ($group === '' || $group === 'N') {
                continue;
            }

            $matchId = (int)$match['id'];
            $date = $this->formatMatchDate($match['date_active_from']);

            $payload[$group][] = [
                'number' => $match['number'],
                'event' => $match['event_id'],
                'date' => $date['date'],
                'time' => $date['time'],
                'active' => $match['active'],
                'teams' => [
                    'home' => $this->formatGroupTeam($match['home_id'], $match['goal_home'], $teamsById),
                    'guest' => $this->formatGroupTeam($match['guest_id'], $match['goal_guest'], $teamsById),
                ],
                'send_info' => [
                    'send_time' => $userPrognosis[$matchId] ?? '',
                    'score_result' => $userResults[$matchId] ?? '',
                ],
                'ratio' => [],
            ];
        }

        if (!$payload) {
            return [];
        }

        ksort($payload, SORT_NATURAL);

        return $payload;
    }

    /**
     * @param list<array<string, mixed>> $playoffMatches
     * @param array<int, array<string, mixed>> $teamsById
     * @return array{0: list<array>, 1: array}
     */
    private function buildPlayoffPayload(
        array $playoffMatches,
        array $teamsById,
        array $userPrognosis,
        array $userResults
    ): array {
        if (!$playoffMatches) {
            return [[], []];
        }

        usort($playoffMatches, static function (array $a, array $b): int {
            $codeDiff = PlayoffSlotHelper::compareBracketCodes(
                (string)($a['bracket_code'] ?? ''),
                (string)($b['bracket_code'] ?? '')
            );
            if ($codeDiff !== 0) {
                return $codeDiff;
            }

            $stepDiff = ((int)($a['step'] ?? 0)) <=> ((int)($b['step'] ?? 0));
            if ($stepDiff !== 0) {
                return $stepDiff;
            }

            return ((int)($a['number'] ?? 0)) <=> ((int)($b['number'] ?? 0));
        });

        $this->ensurePlayoffTeamsLoaded($playoffMatches, $teamsById);

        $playoffByRound = [];
        foreach ($playoffMatches as $match) {
            $stageRound = $this->resolvePlayoffStageRound($match);
            $playoffByRound[$stageRound][] = $this->mapPlayoffMatchRow(
                $match,
                $stageRound,
                $teamsById,
                $userPrognosis,
                $userResults
            );
        }

        if (!$playoffByRound) {
            return [[], []];
        }

        ksort($playoffByRound, SORT_NUMERIC);

        $tabs = $this->groupPlayoffIntoTabs($playoffByRound);
        $this->attachPlayoffAdvanceHints($tabs);
        $bracket = $this->buildPlayoffBracketFromByRound($playoffByRound);

        return [$tabs, $bracket];
    }

    /**
     * @param array<int, array<string, mixed>> $stats
     * @param array<int, array<string, mixed>> $teamsById
     */
    private function ensureTeamStats(array &$stats, int $teamId, array $teamsById): void
    {
        if (isset($stats[$teamId])) {
            return;
        }

        $stats[$teamId] = [
            'score' => 0,
            'win' => 0,
            'lose' => 0,
            'draw' => 0,
            'matches' => 0,
            'plus' => 0,
            'minus' => 0,
            'diff' => 0,
            'info' => $teamsById[$teamId] ?? null,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $stats
     */
    private function applyWin(array &$stats, string $result, int $homeId, int $guestId): void
    {
        switch ($result) {
            case 'п1':
                $stats[$homeId]['win']++;
                $stats[$guestId]['lose']++;
                break;
            case 'н':
                $stats[$homeId]['draw']++;
                $stats[$guestId]['draw']++;
                break;
            case 'п2':
                $stats[$homeId]['lose']++;
                $stats[$guestId]['win']++;
                break;
        }
    }

    private function getScore(string $result, string $side = 'guest'): int
    {
        switch ($result) {
            case 'п1':
                return $side === 'home' ? 3 : 0;
            case 'н':
                return 1;
            case 'п2':
                return $side === 'home' ? 0 : 3;
            default:
                return 0;
        }
    }

    /**
     * @param list<array<string, mixed>> $teams
     * @return list<array<string, mixed>>
     */
    private function sortStandings(array $teams): array
    {
        array_multisort(
            array_column($teams, 'score'),
            SORT_DESC,
            SORT_NUMERIC,
            array_column($teams, 'win'),
            SORT_DESC,
            SORT_NUMERIC,
            array_column($teams, 'diff'),
            SORT_DESC,
            SORT_NUMERIC,
            array_column($teams, 'plus'),
            SORT_DESC,
            SORT_NUMERIC,
            $teams
        );

        return $teams;
    }

    /**
     * @param array<string|int, list<array<string, mixed>>> $groups
     * @return list<array<string, mixed>>
     */
    private function buildThirdPlacesOverview(array $groups): array
    {
        $thirdPlaces = [];

        foreach ($groups as $groupName => $teams) {
            if ($groupName === 0 || $groupName === '0' || count($teams) < 3) {
                continue;
            }

            $team = $teams[2];
            $team['sourceGroup'] = $groupName;
            $thirdPlaces[] = $team;
        }

        return $thirdPlaces ? $this->sortStandings($thirdPlaces) : [];
    }

    /**
     * @param array<int, list<array<string, mixed>>> $playoffByRound
     * @return list<array<string, mixed>>
     */
    private function groupPlayoffIntoTabs(array $playoffByRound): array
    {
        $tabs = [];

        while ($playoffByRound) {
            $round = array_key_first($playoffByRound);
            $matches = $playoffByRound[$round];
            unset($playoffByRound[$round]);
            $count = count($matches);
            $remainingCount = array_sum(array_map('count', $playoffByRound));

            if ($count >= 4) {
                $tabs[] = [
                    'key' => 'round_' . $round,
                    'label' => $this->playoffLabelByMatchCount($count),
                    'matches' => $matches,
                ];
                continue;
            }

            if ($count === 2 && $remainingCount >= 2) {
                $tabs[] = [
                    'key' => 'round_' . $round,
                    'label' => '1/2',
                    'matches' => $matches,
                ];
                continue;
            }

            $finalMatches = $matches;
            foreach ($playoffByRound as $roundMatches) {
                $finalMatches = array_merge($finalMatches, $roundMatches);
            }

            usort($finalMatches, static fn(array $a, array $b) => ($a['number'] ?? 0) <=> ($b['number'] ?? 0));

            if (count($finalMatches) === 2) {
                foreach ($finalMatches as &$finalMatch) {
                    if (PlayoffSlotHelper::isThirdPlaceMatch($finalMatch)) {
                        $finalMatch['card_title'] = '3-е место';
                    } elseif (PlayoffSlotHelper::isFinalMatch($finalMatch)) {
                        $finalMatch['card_title'] = 'Финал';
                    }
                }
                unset($finalMatch);

                if (!PlayoffSlotHelper::isThirdPlaceMatch($finalMatches[0])
                    && !PlayoffSlotHelper::isFinalMatch($finalMatches[0])) {
                    $finalMatches[0]['card_title'] = '3-е место';
                    $finalMatches[1]['card_title'] = 'Финал';
                }
            } elseif (count($finalMatches) === 1) {
                $finalMatches[0]['card_title'] = PlayoffSlotHelper::isThirdPlaceMatch($finalMatches[0])
                    ? '3-е место'
                    : 'Финал';
            }

            $tabs[] = [
                'key' => 'final',
                'label' => 'Финал',
                'matches' => $finalMatches,
            ];
            break;
        }

        return $tabs;
    }

    /**
     * @param list<array<string, mixed>> $tabs
     */
    private function attachPlayoffAdvanceHints(array &$tabs): void
    {
        for ($tabIndex = 0; $tabIndex < count($tabs) - 1; $tabIndex++) {
            $nextTab = $tabs[$tabIndex + 1];
            $nextMatches = $nextTab['matches'] ?? [];

            foreach ($tabs[$tabIndex]['matches'] as &$match) {
                if (!$match['finished'] || !$match['winner']) {
                    continue;
                }

                $winnerId = $match['winner'] === 'home'
                    ? (int)$match['home_id']
                    : (int)$match['guest_id'];

                if ($winnerId <= 0) {
                    continue;
                }

                foreach ($nextMatches as $nextMatch) {
                    if ($winnerId === (int)$nextMatch['home_id'] || $winnerId === (int)$nextMatch['guest_id']) {
                        $match['advance_label'] = '→ ' . ($nextTab['label'] ?? '');
                        $match['advance_number'] = (int)($nextMatch['number'] ?? 0);
                        break;
                    }
                }
            }
            unset($match);
        }
    }

    /**
     * @param array<int, list<array<string, mixed>>> $playoffByRound
     */
    private function buildPlayoffBracketFromByRound(array $playoffByRound): array
    {
        $stageGroups = $this->resolvePlayoffStageGroups($playoffByRound);
        if (!$stageGroups) {
            return [];
        }

        $columns = [];
        $thirdPlace = null;
        $lastIndex = count($stageGroups) - 1;

        foreach ($stageGroups as $index => $stage) {
            $matches = $stage['matches'];
            $label = $stage['label'];

            foreach ($matches as $match) {
                if (PlayoffSlotHelper::isThirdPlaceMatch($match)) {
                    $thirdPlace = $match;
                }
            }

            $matches = array_values(array_filter(
                $matches,
                static fn(array $match) => !PlayoffSlotHelper::isThirdPlaceMatch($match)
            ));

            if (!$matches) {
                continue;
            }

            if (count($matches) === 1 && $index === $lastIndex) {
                $label = 'Финал';
            }

            $columns[] = [
                'key' => 'stage_' . $index,
                'label' => $label,
                'matches' => $matches,
            ];
        }

        if (!$columns) {
            return [];
        }

        $firstCount = count($columns[0]['matches'] ?? []);
        $baseSlots = $this->normalizeBracketSlotCount(max(1, $firstCount));

        foreach ($columns as $index => &$column) {
            $matches = $this->sortPlayoffMatches($column['matches'] ?? []);
            $column['slotCount'] = max(1, (int)($baseSlots / (2 ** $index)));
            $column['slots'] = $matches;
            unset($column['matches']);
        }
        unset($column);

        return [
            'baseSlots' => $baseSlots,
            'columns' => $columns,
            'thirdPlace' => $thirdPlace,
        ];
    }

    /**
     * @param array<int, list<array<string, mixed>>> $playoffByRound
     * @return list<array{label:string,matches:list<array>}>
     */
    private function resolvePlayoffStageGroups(array $playoffByRound): array
    {
        if (count($playoffByRound) > 1) {
            $groups = [];

            foreach ($playoffByRound as $matches) {
                $groups[] = [
                    'label' => $this->playoffLabelByMatchCount(count($this->sortPlayoffMatches($matches))),
                    'matches' => $this->sortPlayoffMatches($matches),
                ];
            }

            return $groups;
        }

        return $this->splitPlayoffMatchesIntoStages(
            $this->sortPlayoffMatches(reset($playoffByRound) ?: [])
        );
    }

    /**
     * @param list<array<string, mixed>> $matches
     * @return list<array{label:string,matches:list<array>}>
     */
    private function splitPlayoffMatchesIntoStages(array $matches): array
    {
        $count = count($matches);
        if ($count <= 0) {
            return [];
        }

        if ($count === 1) {
            return [['label' => 'Финал', 'matches' => $matches]];
        }

        $sizes = [];
        $remaining = $count;

        foreach ([16, 8, 4, 2, 1] as $size) {
            if ($remaining >= $size) {
                $sizes[] = $size;
                $remaining -= $size;
            }
        }

        if ($remaining > 0) {
            $sizes[0] = ($sizes[0] ?? 0) + $remaining;
        }

        $groups = [];
        $offset = 0;

        foreach ($sizes as $size) {
            $chunk = array_slice($matches, $offset, $size);
            if (!$chunk) {
                continue;
            }

            $groups[] = [
                'label' => $this->playoffLabelByMatchCount(count($chunk)),
                'matches' => $chunk,
            ];
            $offset += $size;
        }

        return $groups;
    }

    /**
     * @param list<array<string, mixed>> $matches
     * @return list<array<string, mixed>>
     */
    private function sortPlayoffMatches(array $matches): array
    {
        usort($matches, static function (array $a, array $b): int {
            $codeDiff = PlayoffSlotHelper::compareBracketCodes(
                (string)($a['bracket_code'] ?? ''),
                (string)($b['bracket_code'] ?? '')
            );
            if ($codeDiff !== 0) {
                return $codeDiff;
            }

            $stepDiff = ((int)($a['step'] ?? 0)) <=> ((int)($b['step'] ?? 0));
            if ($stepDiff !== 0) {
                return $stepDiff;
            }

            return ((int)($a['number'] ?? 0)) <=> ((int)($b['number'] ?? 0));
        });

        return array_values($matches);
    }

    private function normalizeBracketSlotCount(int $count): int
    {
        $size = 1;
        while ($size < $count) {
            $size *= 2;
        }

        return min(32, max(1, $size));
    }

    private function playoffLabelByMatchCount(int $count): string
    {
        if ($count >= 16) {
            return '1/16';
        }
        if ($count >= 8) {
            return '1/8';
        }
        if ($count >= 4) {
            return '1/4';
        }
        if ($count >= 2) {
            return '1/2';
        }
        if ($count === 1) {
            return 'Финал';
        }

        return 'Этап';
    }

    /**
     * @param array<string, mixed> $match
     */
    private function resolvePlayoffStageRound(array $match): int
    {
        $stageRound = PlayoffSlotHelper::bracketStageFromDetail((string)($match['stage_detail'] ?? ''));
        if ($stageRound > 0) {
            return $stageRound;
        }

        $stageRound = PlayoffSlotHelper::bracketStageFromCode((string)($match['bracket_code'] ?? ''));
        if ($stageRound <= 0) {
            $stageRound = (int)($match['round'] ?? 0);
        }

        return $stageRound;
    }

    /**
     * @param array<string, mixed> $match
     * @param array<int, array<string, mixed>> $teamsById
     */
    private function mapPlayoffMatchRow(
        array $match,
        int $stageRound,
        array &$teamsById,
        array $userPrognosis,
        array $userResults
    ): array {
        $matchId = (int)$match['id'];
        $homeId = (int)$match['home_id'];
        $guestId = (int)$match['guest_id'];
        $date = $this->formatMatchDate($match['date_active_from']);
        $bracketCode = (string)($match['bracket_code'] ?? '');
        $stageDetail = (string)($match['stage_detail'] ?? '');

        $cardTitle = '';
        if (PlayoffSlotHelper::isThirdPlaceMatch(['bracket_code' => $bracketCode, 'stage_detail' => $stageDetail])) {
            $cardTitle = '3-е место';
        } elseif (PlayoffSlotHelper::isFinalMatch(['bracket_code' => $bracketCode, 'stage_detail' => $stageDetail])) {
            $cardTitle = 'Финал';
        }

        return [
            'id' => $matchId,
            'number' => (int)$match['number'],
            'event' => $match['event_id'],
            'round' => $stageRound,
            'step' => (int)$match['step'],
            'date' => $date['date'],
            'time' => $date['time'],
            'active' => $match['active'],
            'finished' => $match['active'] === 'N',
            'home_id' => $homeId,
            'guest_id' => $guestId,
            'winner' => $this->resolveMatchWinner(
                $homeId,
                $guestId,
                $match['goal_home'],
                $match['goal_guest'],
                (string)$match['result']
            ),
            'bracket_code' => $bracketCode,
            'stage_detail' => $stageDetail,
            'card_title' => $cardTitle,
            'teams' => [
                'home' => PlayoffSlotHelper::teamPayload(
                    $homeId,
                    $teamsById[$homeId] ?? null,
                    $match['goal_home'],
                    (string)$match['home_label']
                ),
                'guest' => PlayoffSlotHelper::teamPayload(
                    $guestId,
                    $teamsById[$guestId] ?? null,
                    $match['goal_guest'],
                    (string)$match['guest_label']
                ),
            ],
            'send_info' => [
                'send_time' => $userPrognosis[$matchId] ?? '',
                'score_result' => $userResults[$matchId] ?? '',
            ],
        ];
    }

    /**
     * @param list<array<string, mixed>> $playoffMatches
     * @param array<int, array<string, mixed>> $teamsById
     */
    private function ensurePlayoffTeamsLoaded(array $playoffMatches, array &$teamsById): void
    {
        $missingIds = [];

        foreach ($playoffMatches as $match) {
            foreach (['home_id', 'guest_id'] as $field) {
                $teamId = (int)($match[$field] ?? 0);
                if ($teamId > 0 && empty($teamsById[$teamId])) {
                    $missingIds[$teamId] = $teamId;
                }
            }
        }

        if (!$missingIds) {
            return;
        }

        $loaded = (new CountriesRepository())->findIndexedByIds(array_values($missingIds));
        foreach ($loaded as $teamId => $team) {
            $teamsById[$teamId] = $team;
        }
    }

    private function resolveMatchWinner(
        int $homeId,
        int $guestId,
        $goalsHome,
        $goalsGuest,
        string $result
    ): ?string {
        if ($result === 'п1') {
            return 'home';
        }
        if ($result === 'п2') {
            return 'guest';
        }

        $goalsHome = (int)$goalsHome;
        $goalsGuest = (int)$goalsGuest;

        if ($goalsHome > $goalsGuest) {
            return 'home';
        }
        if ($goalsGuest > $goalsHome) {
            return 'guest';
        }

        return null;
    }

    /**
     * @param array<int, array<string, mixed>> $teamsById
     */
    private function formatGroupTeam(int $teamId, $goals, array $teamsById): array
    {
        $info = $teamsById[$teamId] ?? [];

        return [
            'flag' => $info['img'] ?? '',
            'name' => $info['NAME'] ?? '',
            'goals' => $goals ?? 0,
        ];
    }

    /**
     * @return array{date:string,time:string}
     */
    private function formatMatchDate(string $dateActiveFrom): array
    {
        $parts = explode('+', ConvertDateTime($dateActiveFrom, 'DD.MM+HH:Mi'));

        return [
            'date' => $parts[0] ?? '',
            'time' => $parts[1] ?? '',
        ];
    }
}
