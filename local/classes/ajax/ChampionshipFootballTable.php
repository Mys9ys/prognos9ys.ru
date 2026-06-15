<?php

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
    protected $arUserPrognosis = [];
    protected $arUserResults = [];

    public function __construct($data)
    {

        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        }

        $this->data = $data;

        $this->userId = (new GetUserIdForToken($data['token']))->getId();

        $this->getTeamsOneTurids();

        $arEventsInfo = (new GetPrognosisEvents($this->data['events']))->result()['events'][$this->data['events']];

        if (count($this->teamsIds)) $this->getTeamsInfo();

        $this->calcAllTurs();

//        $this->getTurMatches();
//        die();

        if (count($this->arTable)) {
            $this->arGroupMatches = $this->buildGroupMatches();

            $this->setResult('ok', '', [
                'groups' => $this->arTable,
                'thirdPlaces' => $this->arThirdPlaces,
                'groupMatches' => $this->arGroupMatches,
                'info' => $arEventsInfo,
            ]);
        }

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
            $group = $res['PROPERTY_GROUP_VALUE'];
            if ($group === 'N' || $group === '' || $group === null) {
                continue;
            }

            $arr[] = $res['PROPERTY_HOME_VALUE'];
            $arr[] = $res['PROPERTY_GUEST_VALUE'];
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
        $arFilter = [
            'IBLOCK_ID' => $this->arIbs['matches']['id'],
            'PROPERTY_events' => $this->data['events'],
//            'ACTIVE' => "N",
        ];

        $response = CIBlockElement::GetList(
            [],
            $arFilter,
            false,
            [],
            [
                "ACTIVE",
                "PROPERTY_home",
                "PROPERTY_guest",
                "PROPERTY_goal_home",
                "PROPERTY_goal_guest",
                "PROPERTY_result",
                "PROPERTY_group",
            ]
        );

        while ($res = $response->GetNext()) {

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

        $this->loadUserMatchMarks();

        $arFilter = [
            'IBLOCK_ID' => $this->arIbs['matches']['id'],
            'PROPERTY_events' => $this->data['events'],
        ];

        $response = CIBlockElement::GetList(
            ['DATE_ACTIVE_FROM' => 'ASC', 'PROPERTY_number' => 'ASC'],
            $arFilter,
            false,
            [],
            [
                'ID',
                'ACTIVE',
                'DATE_ACTIVE_FROM',
                'PROPERTY_home',
                'PROPERTY_guest',
                'PROPERTY_goal_home',
                'PROPERTY_goal_guest',
                'PROPERTY_group',
                'PROPERTY_number',
                'PROPERTY_events',
            ]
        );

        $groupMatches = [];

        while ($res = $response->GetNext()) {
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

    protected function formatGroupMatchTeam($teamId, $goals): array
    {
        $info = $this->arTableInfo[$teamId] ?? [];

        return [
            'flag' => $info['img'] ?? '',
            'name' => $info['NAME'] ?? '',
            'goals' => $goals ?? 0,
        ];
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
