<?php

require_once __DIR__ . '/PlayoffSlotHelper.php';

use Bitrix\Main\Loader;

class ChampionshipFootballTable extends PrognosisGiveInfo
{
    protected $data;
    protected $arIbs = [
        'matches' => ['code' => 'matches', 'id' => 2],
    ];

    protected $teamsIds;
    protected $userId;

    protected $arTable;
    protected $arTableInfo;
    protected $arTableUnsort;

    protected $arGroup;
    protected $arGroupTeams;
    protected $arThirdPlaces = [];
    protected $arGroupMatches = [];
    protected $arPlayoffRounds = [];
    protected $arPlayoffBracket = [];
    protected $arUserPrognosis = [];
    protected $arUserResults = [];
    protected $matchPropertyCodes;
    protected $cachedEventMatches;
    /** @var string|false|null */
    protected $stageDetailPropertyCode;
    protected static $matchPropertyCodesStatic;

    public function __construct($data)
    {

        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        }

        $this->data = $data;
        $this->arTable = [];
        $this->arTableUnsort = [];

        $this->userId = (new GetUserIdForToken($data['token']))->getId();

        $this->getTeamsOneTurids();

        $eventsPayload = (new GetPrognosisEvents($this->data['events']))->result();
        $arEventsInfo = $eventsPayload['events'][$this->data['events']] ?? null;

        if (!$arEventsInfo) {
            $this->setResult('error', 'Событие не найдено');
            return;
        }

        if (count($this->teamsIds)) {
            $this->getTeamsInfo();
        }

        $this->calcAllTurs();
        $this->loadUserMatchMarks();
        $this->arGroupMatches = $this->buildGroupMatches();
        $this->arPlayoffRounds = $this->buildPlayoffRounds();

        $this->setResult('ok', '', [
            'groups' => $this->arTable,
            'thirdPlaces' => $this->arThirdPlaces,
            'groupMatches' => $this->arGroupMatches,
            'playoffRounds' => $this->arPlayoffRounds,
            'playoffBracket' => $this->arPlayoffBracket,
            'info' => $arEventsInfo,
        ]);

    }

    protected function getTeamsOneTurIds()
    {
        $arFilter = [
            'IBLOCK_ID' => $this->arIbs['matches']['id'],
            'PROPERTY_events' => $this->data['events'],
        ];

        $response = CIBlockElement::GetList(
            [],
            $arFilter,
            false,
            [],
            [
                'PROPERTY_home',
                'PROPERTY_guest',
                'PROPERTY_group',
            ]
        );

        $arr = [];

        while ($res = $response->GetNext()) {
            if ($res['PROPERTY_HOME_VALUE']) {
                $arr[] = $res['PROPERTY_HOME_VALUE'];
            }
            if ($res['PROPERTY_GUEST_VALUE']) {
                $arr[] = $res['PROPERTY_GUEST_VALUE'];
            }
        }

        $this->teamsIds = array_unique($arr, SORT_NUMERIC);

    }

    protected function getTeamsInfo()
    {

        $arFilter = [
            'ID' => $this->teamsIds
        ];

        $response = CIBlockElement::GetList(
            ['NAME' => 'ASC'],
            $arFilter,
            false,
            [],
            ['NAME', 'ID', 'PREVIEW_PICTURE']
        );
        while ($res = $response->GetNext()) {
            $res['img'] = CFile::GetPath($res['PREVIEW_PICTURE']);
            $this->arTable[] = ['info' => $res];
            $this->arTableInfo[$res["ID"]] = $res;
        }
    }

    protected function calcAllTurs()
    {
        foreach ($this->getEventMatchesCache() as $res) {
            if ($this->isPlayoffMatchRow($res)) {
                continue;
            }

            if ($res['PROPERTY_GROUP_VALUE'] && $res['PROPERTY_GROUP_VALUE'] !== 'N') {
                $this->arGroup[$res['PROPERTY_HOME_VALUE']] = $res['PROPERTY_GROUP_VALUE'];
                $this->arGroup[$res['PROPERTY_GUEST_VALUE']] = $res['PROPERTY_GROUP_VALUE'];
            }


            $this->arTableUnsort[$res['PROPERTY_HOME_VALUE']]['score'] += $this->getScore($res['PROPERTY_RESULT_VALUE'], 'home');
            $this->arTableUnsort[$res['PROPERTY_GUEST_VALUE']]['score'] += $this->getScore($res['PROPERTY_RESULT_VALUE']);

            if ($res["PROPERTY_GROUP_VALUE"] == 'N' || !empty($res['PROPERTY_RESULT_VALUE'])) {
                $this->arTableUnsort[$res['PROPERTY_HOME_VALUE']]['matches']++;
                $this->arTableUnsort[$res['PROPERTY_GUEST_VALUE']]['matches']++;
            }

            if (!$this->arTableUnsort[$res['PROPERTY_HOME_VALUE']]['info']) $this->arTableUnsort[$res['PROPERTY_HOME_VALUE']]['info'] = $this->arTableInfo[$res['PROPERTY_HOME_VALUE']];
            if (!$this->arTableUnsort[$res['PROPERTY_GUEST_VALUE']]['info']) $this->arTableUnsort[$res['PROPERTY_GUEST_VALUE']]['info'] = $this->arTableInfo[$res['PROPERTY_GUEST_VALUE']];

            $this->getWin($res['PROPERTY_RESULT_VALUE'], $res['PROPERTY_HOME_VALUE'], $res['PROPERTY_GUEST_VALUE']);

            $this->arTableUnsort[$res['PROPERTY_HOME_VALUE']]['plus'] += $res['PROPERTY_GOAL_HOME_VALUE'];
            $this->arTableUnsort[$res['PROPERTY_GUEST_VALUE']]['plus'] += $res['PROPERTY_GOAL_GUEST_VALUE'];

            $this->arTableUnsort[$res['PROPERTY_HOME_VALUE']]['minus'] += $res['PROPERTY_GOAL_GUEST_VALUE'];
            $this->arTableUnsort[$res['PROPERTY_GUEST_VALUE']]['minus'] += $res['PROPERTY_GOAL_HOME_VALUE'];

            $this->arTableUnsort[$res['PROPERTY_HOME_VALUE']]['diff'] =
                $this->arTableUnsort[$res['PROPERTY_HOME_VALUE']]['plus'] - $this->arTableUnsort[$res['PROPERTY_HOME_VALUE']]['minus'];
            $this->arTableUnsort[$res['PROPERTY_GUEST_VALUE']]['diff'] =
                $this->arTableUnsort[$res['PROPERTY_GUEST_VALUE']]['plus'] - $this->arTableUnsort[$res['PROPERTY_GUEST_VALUE']]['minus'];

        }

        if ($this->arTableUnsort) {

            if (count($this->arGroup) > 1) {

                foreach ($this->arGroup as $id => $group) {
                    $this->arGroupTeams[$group][] = $this->arTableUnsort[$id];
                }

                $arGroupTemp = [];

                foreach ($this->arGroupTeams as $groupName => $teams) {
                    $arGroupTemp[$groupName] = $this->myMultiSort($teams);
                }

                ksort($arGroupTemp, SORT_NATURAL); /// test

                $this->arTable = $arGroupTemp;

            } else {
                $this->arTable = [];
                $this->arTable[0] = $this->myMultiSort($this->arTableUnsort);
            }

            $this->arThirdPlaces = $this->buildThirdPlacesOverview();
        }

    }

    /**
     * Сводная таблица команд на 3-м месте в своих группах (регламент ЧМ).
     */
    protected function buildThirdPlacesOverview(): array
    {
        if (!$this->arTable || count($this->arGroup) <= 1) {
            return [];
        }

        $thirdPlaces = [];

        foreach ($this->arTable as $groupName => $teams) {
            if ($groupName === 0 || $groupName === '0' || !is_array($teams) || count($teams) < 3) {
                continue;
            }

            $team = $teams[2];
            $team['sourceGroup'] = $groupName;
            $thirdPlaces[] = $team;
        }

        if (!$thirdPlaces) {
            return [];
        }

        return $this->myMultiSort($thirdPlaces);
    }

    /**
     * Матчи группового этапа, сгруппированные по букве группы (A, B, C…).
     */
    protected function buildGroupMatches(): array
    {
        if (!$this->arTable || count($this->arGroup) <= 1) {
            return [];
        }

        $groupMatches = [];

        foreach ($this->getEventMatchesCache() as $res) {
            if ($this->isPlayoffMatchRow($res)) {
                continue;
            }

            $group = $res['PROPERTY_GROUP_VALUE'];
            if ($group === 'N' || $group === '' || $group === null) {
                continue;
            }

            $matchId = $res['ID'];
            $date = explode('+', ConvertDateTime($res['DATE_ACTIVE_FROM'], 'DD.MM+HH:Mi'));

            $groupMatches[$group][] = [
                'number' => $res['PROPERTY_NUMBER_VALUE'],
                'event' => $res['PROPERTY_EVENTS_VALUE'],
                'date' => $date[0] ?? '',
                'time' => $date[1] ?? '',
                'active' => $res['ACTIVE'],
                'teams' => [
                    'home' => $this->formatGroupMatchTeam(
                        $res['PROPERTY_HOME_VALUE'],
                        $res['PROPERTY_GOAL_HOME_VALUE']
                    ),
                    'guest' => $this->formatGroupMatchTeam(
                        $res['PROPERTY_GUEST_VALUE'],
                        $res['PROPERTY_GOAL_GUEST_VALUE']
                    ),
                ],
                'send_info' => [
                    'send_time' => $this->arUserPrognosis[$matchId] ?? '',
                    'score_result' => $this->arUserResults[$matchId] ?? '',
                ],
                'ratio' => [],
            ];
        }

        if (!$groupMatches) {
            return [];
        }

        ksort($groupMatches, SORT_NATURAL);

        return $groupMatches;
    }

    /**
     * Плей-офф: вкладки по раундам (1/16 … финал + 3-е место).
     *
     * @return list<array{key:string,label:string,matches:array<int,array>}>
     */
    protected function buildPlayoffRounds(): array
    {
        if (!$this->eventHasPlayoffMatches()) {
            $this->arPlayoffBracket = [];

            return [];
        }

        $this->ensurePlayoffTeamsLoaded();

        $playoffByRound = [];

        foreach ($this->loadPlayoffMatchRows() as $res) {
            $stageRound = $this->resolvePlayoffStageRound($res);
            $playoffByRound[$stageRound][] = $this->mapPlayoffMatchRow($res, $stageRound);
        }

        if (!$playoffByRound) {
            return [];
        }

        ksort($playoffByRound, SORT_NUMERIC);

        $tabs = $this->groupPlayoffIntoTabs($playoffByRound);
        $this->attachPlayoffAdvanceHints($tabs);
        $this->arPlayoffBracket = $this->buildPlayoffBracketFromByRound($playoffByRound);

        return $tabs;
    }

    /**
     * Колонки сетки: по стадии bracket_code (или legacy PROPERTY_round).
     *
     * @param array<int, list<array>> $playoffByRound
     */
    protected function buildPlayoffBracketFromByRound(array $playoffByRound): array
    {
        if (!$playoffByRound) {
            return [];
        }

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
                static fn($match) => !PlayoffSlotHelper::isThirdPlaceMatch($match)
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
     * @param array<int, list<array>> $playoffByRound
     * @return list<array{label:string,matches:list<array>}>
     */
    protected function resolvePlayoffStageGroups(array $playoffByRound): array
    {
        if (count($playoffByRound) > 1) {
            $groups = [];

            foreach ($playoffByRound as $matches) {
                $sorted = $this->sortPlayoffMatches($matches);
                $groups[] = [
                    'label' => $this->playoffLabelByMatchCount(count($sorted)),
                    'matches' => $sorted,
                ];
            }

            return $groups;
        }

        $allMatches = $this->sortPlayoffMatches(reset($playoffByRound) ?: []);

        return $this->splitPlayoffMatchesIntoStages($allMatches);
    }

    /**
     * @param list<array> $matches
     * @return list<array{label:string,matches:list<array>}>
     */
    protected function splitPlayoffMatchesIntoStages(array $matches): array
    {
        $count = count($matches);
        if ($count <= 0) {
            return [];
        }

        if ($count === 1) {
            return [
                [
                    'label' => 'Финал',
                    'matches' => $matches,
                ],
            ];
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
     * @param list<array> $matches
     * @return list<array>
     */
    protected function sortPlayoffMatches(array $matches): array
    {
        usort($matches, static function ($a, $b) {
            $codeDiff = PlayoffSlotHelper::compareBracketCodes(
                $a['bracket_code'] ?? '',
                $b['bracket_code'] ?? ''
            );
            if ($codeDiff !== 0) {
                return $codeDiff;
            }

            $stepDiff = ($a['step'] ?? 0) <=> ($b['step'] ?? 0);
            if ($stepDiff !== 0) {
                return $stepDiff;
            }

            return ($a['number'] ?? 0) <=> ($b['number'] ?? 0);
        });

        return array_values($matches);
    }

    protected function normalizeBracketSlotCount(int $count): int
    {
        $size = 1;
        while ($size < $count) {
            $size *= 2;
        }

        return min(32, max(1, $size));
    }

    /**
     * @param list<array> $matches
     * @return list<array|null>
     */
    protected function fillBracketSlots(array $matches, int $slotCount): array
    {
        $slots = array_fill(0, $slotCount, null);

        foreach ($matches as $idx => $match) {
            $index = (int)($match['step'] ?? 0);
            if ($index > 0) {
                $index -= 1;
            } else {
                $index = $idx;
            }

            if ($index >= 0 && $index < $slotCount && $slots[$index] === null) {
                $slots[$index] = $match;
                continue;
            }

            foreach ($slots as $slotIndex => $slot) {
                if ($slot === null) {
                    $slots[$slotIndex] = $match;
                    break;
                }
            }
        }

        return $slots;
    }

    protected function isPlayoffMatchRow(array $res): bool
    {
        return PlayoffSlotHelper::isPlayoffMatchRow($res, $this->getStageDetailPropertyCode());
    }

    protected function eventHasPlayoffMatches(): bool
    {
        foreach ($this->getEventMatchesCache() as $row) {
            if ($this->isPlayoffMatchRow($row)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Один запрос по событию — без фильтров по stage/bracket_code (на бою они вешают MySQL).
     *
     * @return list<array>
     */
    protected function getEventMatchesCache(): array
    {
        if ($this->cachedEventMatches !== null) {
            return $this->cachedEventMatches;
        }

        $select = [
            'ID',
            'NAME',
            'ACTIVE',
            'DATE_ACTIVE_FROM',
            'XML_ID',
            'PROPERTY_home',
            'PROPERTY_guest',
            'PROPERTY_goal_home',
            'PROPERTY_goal_guest',
            'PROPERTY_result',
            'PROPERTY_group',
            'PROPERTY_stage',
            'PROPERTY_number',
            'PROPERTY_events',
            'PROPERTY_step',
        ];
        $this->appendMatchSelectField($select, 'round');
        $this->appendMatchSelectField($select, 'bracket_code');
        $this->appendMatchSelectField($select, 'home_label');
        $this->appendMatchSelectField($select, 'guest_label');

        $detailCode = $this->getStageDetailPropertyCode();
        if ($detailCode) {
            $select[] = 'PROPERTY_' . $detailCode;
        }

        $response = CIBlockElement::GetList(
            ['PROPERTY_number' => 'ASC'],
            $this->getEventMatchesFilter(),
            false,
            false,
            $select
        );

        $rows = [];
        while ($res = $response->GetNext()) {
            $rows[] = $res;
        }

        $this->cachedEventMatches = $rows;

        return $rows;
    }

    protected function getStageDetailPropertyCode(): ?string
    {
        if ($this->stageDetailPropertyCode === false) {
            return null;
        }
        if ($this->stageDetailPropertyCode !== null) {
            return $this->stageDetailPropertyCode;
        }

        $response = CIBlockProperty::GetList(
            ['SORT' => 'ASC'],
            [
                'IBLOCK_ID' => $this->arIbs['matches']['id'],
                'NAME' => 'Этап расширенный',
            ]
        );
        if ($row = $response->Fetch()) {
            $code = trim((string)($row['CODE'] ?? ''));
            if ($code !== '') {
                $this->stageDetailPropertyCode = $code;

                return $code;
            }
        }

        foreach (['stage_detail', 'stage_d', 'stage_ext', 'extended_stage'] as $code) {
            if ($this->hasMatchProperty($code)) {
                $this->stageDetailPropertyCode = $code;

                return $code;
            }
        }

        $this->stageDetailPropertyCode = false;

        return null;
    }

    protected function getEventMatchesFilter(): array
    {
        return [
            'IBLOCK_ID' => $this->arIbs['matches']['id'],
            'PROPERTY_events' => $this->data['events'],
        ];
    }

    protected function loadPlayoffMatchRows(): array
    {
        $rows = [];
        foreach ($this->getEventMatchesCache() as $res) {
            if ($this->isPlayoffMatchRow($res)) {
                $rows[] = $res;
            }
        }

        if (!$rows) {
            return [];
        }

        usort($rows, static function ($a, $b) {
            $codeDiff = PlayoffSlotHelper::compareBracketCodes(
                (string)($a['PROPERTY_BRACKET_CODE_VALUE'] ?? ''),
                (string)($b['PROPERTY_BRACKET_CODE_VALUE'] ?? '')
            );
            if ($codeDiff !== 0) {
                return $codeDiff;
            }

            $stepDiff = ((int)($a['PROPERTY_STEP_VALUE'] ?? 0)) <=> ((int)($b['PROPERTY_STEP_VALUE'] ?? 0));
            if ($stepDiff !== 0) {
                return $stepDiff;
            }

            return ((int)($a['PROPERTY_NUMBER_VALUE'] ?? 0)) <=> ((int)($b['PROPERTY_NUMBER_VALUE'] ?? 0));
        });

        return $rows;
    }

    protected function resolvePlayoffStageRound(array $res): int
    {
        $detail = PlayoffSlotHelper::readPropertyValue($res, $this->getStageDetailPropertyCode());
        $stageRound = PlayoffSlotHelper::bracketStageFromDetail($detail);
        if ($stageRound > 0) {
            return $stageRound;
        }

        $bracketCode = (string)($res['PROPERTY_BRACKET_CODE_VALUE'] ?? '');
        $stageRound = PlayoffSlotHelper::bracketStageFromCode($bracketCode);
        if ($stageRound <= 0) {
            $stageRound = (int)($res['PROPERTY_ROUND_VALUE'] ?? 0);
        }

        return $stageRound;
    }

    protected function mapPlayoffMatchRow(array $res, int $stageRound): array
    {
        $matchId = (int)$res['ID'];
        $homeId = (int)$res['PROPERTY_HOME_VALUE'];
        $guestId = (int)$res['PROPERTY_GUEST_VALUE'];
        $date = explode('+', ConvertDateTime($res['DATE_ACTIVE_FROM'], 'DD.MM+HH:Mi'));
        $bracketCode = (string)($res['PROPERTY_BRACKET_CODE_VALUE'] ?? '');
        $stageDetail = PlayoffSlotHelper::readPropertyValue($res, $this->getStageDetailPropertyCode());
        $cardTitle = '';
        if (PlayoffSlotHelper::isThirdPlaceMatch(['bracket_code' => $bracketCode, 'stage_detail' => $stageDetail])) {
            $cardTitle = '3-е место';
        } elseif (PlayoffSlotHelper::isFinalMatch(['bracket_code' => $bracketCode, 'stage_detail' => $stageDetail])) {
            $cardTitle = 'Финал';
        }

        return [
            'id' => $matchId,
            'number' => (int)$res['PROPERTY_NUMBER_VALUE'],
            'event' => $res['PROPERTY_EVENTS_VALUE'],
            'round' => $stageRound,
            'step' => (int)$res['PROPERTY_STEP_VALUE'],
            'date' => $date[0] ?? '',
            'time' => $date[1] ?? '',
            'active' => $res['ACTIVE'],
            'finished' => $res['ACTIVE'] === 'N',
            'home_id' => $homeId,
            'guest_id' => $guestId,
            'winner' => $this->resolveMatchWinner(
                $homeId,
                $guestId,
                $res['PROPERTY_GOAL_HOME_VALUE'],
                $res['PROPERTY_GOAL_GUEST_VALUE'],
                $res['PROPERTY_RESULT_VALUE']
            ),
            'bracket_code' => $bracketCode,
            'stage_detail' => $stageDetail,
            'card_title' => $cardTitle,
            'teams' => [
                'home' => $this->formatPlayoffTeam(
                    $homeId,
                    $res['PROPERTY_GOAL_HOME_VALUE'],
                    (string)($res['PROPERTY_HOME_LABEL_VALUE'] ?? '')
                ),
                'guest' => $this->formatPlayoffTeam(
                    $guestId,
                    $res['PROPERTY_GOAL_GUEST_VALUE'],
                    (string)($res['PROPERTY_GUEST_LABEL_VALUE'] ?? '')
                ),
            ],
            'send_info' => [
                'send_time' => $this->arUserPrognosis[$matchId] ?? '',
                'score_result' => $this->arUserResults[$matchId] ?? '',
            ],
        ];
    }

    protected function hasMatchProperty(string $code): bool
    {
        if (self::$matchPropertyCodesStatic === null) {
            self::$matchPropertyCodesStatic = [];
            $response = CIBlockProperty::GetList(
                ['SORT' => 'ASC'],
                ['IBLOCK_ID' => $this->arIbs['matches']['id'], 'ACTIVE' => 'Y']
            );
            while ($row = $response->Fetch()) {
                $propertyCode = (string)($row['CODE'] ?? '');
                if ($propertyCode !== '') {
                    self::$matchPropertyCodesStatic[$propertyCode] = true;
                }
            }
        }

        return !empty(self::$matchPropertyCodesStatic[$code]);
    }

    protected function appendMatchSelectField(array &$select, string $code): void
    {
        if ($this->hasMatchProperty($code)) {
            $select[] = 'PROPERTY_' . $code;
        }
    }

    protected function ensurePlayoffTeamsLoaded(): void
    {
        $missingIds = [];

        foreach ($this->loadPlayoffMatchRows() as $res) {
            foreach (['PROPERTY_HOME_VALUE', 'PROPERTY_GUEST_VALUE'] as $field) {
                $teamId = (int)$res[$field];
                if ($teamId > 0 && empty($this->arTableInfo[$teamId])) {
                    $missingIds[$teamId] = $teamId;
                }
            }
        }

        if (!$missingIds) {
            return;
        }

        $response = CIBlockElement::GetList(
            ['NAME' => 'ASC'],
            ['ID' => $missingIds],
            false,
            [],
            ['NAME', 'ID', 'PREVIEW_PICTURE']
        );

        while ($res = $response->GetNext()) {
            $res['img'] = CFile::GetPath($res['PREVIEW_PICTURE']);
            $this->arTableInfo[$res['ID']] = $res;
        }
    }

    /**
     * @param array<int, list<array>> $playoffByRound
     * @return list<array{key:string,label:string,matches:array<int,array>}>
     */
    protected function groupPlayoffIntoTabs(array $playoffByRound): array
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

            usort($finalMatches, static function ($a, $b) {
                return ($a['number'] ?? 0) <=> ($b['number'] ?? 0);
            });

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

    protected function playoffLabelByMatchCount(int $count): string
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
     * @param list<array{key:string,label:string,matches:array<int,array>}> $tabs
     */
    protected function attachPlayoffAdvanceHints(array &$tabs): void
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

    protected function resolveMatchWinner(
        int $homeId,
        int $guestId,
        $goalsHome,
        $goalsGuest,
        $result
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

    protected function formatGroupMatchTeam($teamId, $goals): array
    {
        $info = $this->arTableInfo[$teamId] ?? [];

        return [
            'flag' => $info['img'] ?? '',
            'name' => $info['NAME'] ?? '',
            'goals' => $goals ?? 0,
        ];
    }

    protected function formatPlayoffTeam($teamId, $goals, string $slotLabel = ''): array
    {
        $info = $this->arTableInfo[$teamId] ?? [];

        return PlayoffSlotHelper::teamPayload($teamId, $info, $goals, $slotLabel);
    }

    protected function loadUserMatchMarks(): void
    {
        if (!$this->userId) {
            return;
        }

        $prognosisIb = \CIBlock::GetList([], ['CODE' => 'prognosis'], false)->Fetch()['ID'] ?: 6;
        $resultIb = \CIBlock::GetList([], ['CODE' => 'result'], false)->Fetch()['ID'] ?: 7;

        $prognosisResponse = CIBlockElement::GetList(
            [],
            [
                'IBLOCK_ID' => $prognosisIb,
                'PROPERTY_EVENTS' => $this->data['events'],
                'PROPERTY_USER_ID' => $this->userId,
            ],
            false,
            [],
            [
                'PROPERTY_match_id',
                'DATE_ACTIVE_FROM',
            ]
        );

        while ($res = $prognosisResponse->GetNext()) {
            $this->arUserPrognosis[$res['PROPERTY_MATCH_ID_VALUE']] = ConvertDateTime(
                $res['DATE_ACTIVE_FROM'],
                'DD.MM HH:Mi'
            );
        }

        $resultResponse = CIBlockElement::GetList(
            [],
            [
                'IBLOCK_ID' => $resultIb,
                'PROPERTY_EVENTS' => $this->data['events'],
                'PROPERTY_USER_ID' => $this->userId,
            ],
            false,
            [],
            [
                'PROPERTY_all',
                'PROPERTY_match_id',
            ]
        );

        while ($res = $resultResponse->GetNext()) {
            $this->arUserResults[$res['PROPERTY_MATCH_ID_VALUE']] = $res['PROPERTY_ALL_VALUE'];
        }
    }

    protected function getWin($res, $home, $guest)
    {

        if (!$this->arTableUnsort[$home]['win']) $this->arTableUnsort[$home]['win'] = 0;
        if (!$this->arTableUnsort[$guest]['win']) $this->arTableUnsort[$guest]['win'] = 0;

        switch ($res) {
            case 'п1':
                $this->arTableUnsort[$home]['win']++;
                $this->arTableUnsort[$guest]['lose']++;
                break;
            case 'н':
                $this->arTableUnsort[$home]['draw']++;
                $this->arTableUnsort[$guest]['draw']++;
                break;
            case 'п2':
                $this->arTableUnsort[$home]['lose']++;
                $this->arTableUnsort[$guest]['win']++;
                break;
        }
    }

    protected function myMultiSort($arr)
    {

        array_multisort(
            array_column($arr, 'score'), SORT_DESC, SORT_NUMERIC,
            array_column($arr, 'win'), SORT_DESC, SORT_NUMERIC,
            array_column($arr, 'diff'), SORT_DESC, SORT_NUMERIC,
            array_column($arr, 'plus'), SORT_DESC, SORT_NUMERIC,
            $arr);

        return $arr;

    }

    protected function getScore($res, $side = 'guest')
    {
        switch ($res) {
            case 'п1':
                return $side === 'home' ? 3 : 0;
                break;
            case 'н':
                return 1;
                break;
            case 'п2':
                return $side === 'home' ? 0 : 3;
                break;
        }
    }
}
