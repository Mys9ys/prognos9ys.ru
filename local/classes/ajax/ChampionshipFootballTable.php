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

        $this->getTurMatches();
        die();

        if (count($this->arTable)) $this->setResult('ok', '', ['groups' => $this->arTable, 'info' => $arEventsInfo]);

    }

    protected function getTeamsOneTurIds()
    {
        $arFilter = [
            'IBLOCK_ID' => $this->arIbs['matches']['id'],
            'PROPERTY_events' => $this->data['events'],
            'PROPERTY_round' => [1, 2, 3]
        ];

        $response = CIBlockElement::GetList(
            [],
            $arFilter,
            false,
            [],
            [
                'PROPERTY_home', 'PROPERTY_guest'
            ]
        );

        $arr = [];

        while ($res = $response->GetNext()) {
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

            if ($res["PROPERTY_GROUP_VALUE"]) $this->arGroup[$res['PROPERTY_HOME_VALUE']] = $res["PROPERTY_GROUP_VALUE"];


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

                ksort($arGroupTemp, SORT_LOCALE_STRING);

                $this->arTable = $arGroupTemp;

            } else {
                $this->arTable = [];
                $this->arTable[0] = $this->myMultiSort($this->arTableUnsort);
            }

        }

    }

    protected function getTurMatches()
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
                "ID",
                "NAME",
                "PROPERTY_number",
                "ACTIVE",
                "DATE_ACTIVE_FROM",
                "PROPERTY_number",
                "PROPERTY_round",
            ]
        );

        $arTursMatches = [];

        while ($res = $response->GetNext()) {

            if(!$arTursMatches[$res['PROPERTY_ROUND_VALUE']]['period']['min'])
                $arTursMatches[$res['PROPERTY_ROUND_VALUE']]['period']['min'] = $arTursMatches[$res['PROPERTY_ROUND_VALUE']]['period']['max'] = strtotime($res['DATE_ACTIVE_FROM']);

            $arTursMatches[$res['PROPERTY_ROUND_VALUE']]['period']['min'] =
                $arTursMatches[$res['PROPERTY_ROUND_VALUE']]['period']['min'] > strtotime($res['DATE_ACTIVE_FROM'])
                    ? strtotime($res['DATE_ACTIVE_FROM'])
                    : $arTursMatches[$res['PROPERTY_ROUND_VALUE']]['period']['min'];

            $arTursMatches[$res['PROPERTY_ROUND_VALUE']]['period']['max'] =
                $arTursMatches[$res['PROPERTY_ROUND_VALUE']]['period']['max'] < strtotime($res['DATE_ACTIVE_FROM'])
                    ? strtotime($res['DATE_ACTIVE_FROM'])
                    : $arTursMatches[$res['PROPERTY_ROUND_VALUE']]['period']['max'];

            $arTursMatches[$res['PROPERTY_ROUND_VALUE']]['matches'][] = $res;
        }


        $arResult = [];
        foreach ($arTursMatches as $turnId=>$turnArr){
            $matches = [];
            foreach ($turnArr['matches'] as $matches){
                $data = [
                    'eventId' => $this->data['eventId'],
                    'userToken' => $this->data['token'],
                    'number' => $matches['PROPERTY_NUMBER_VALUE']
                ];
                $matches[] = (new FootballMatchLoadInfo($data))->result();
            }
            dump($matches);
            $arResult[$this->checkDateNearestMatch($turnArr['period'])][$turnId] = $turnArr;
        }



        dump($arResult);
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

    protected function checkDateNearestMatch($arr){
        if($arr['max'] < time() && $arr['max'] < time())
            return 'old';
        if($arr['max'] > time() && $arr['max'] > time())
            return 'nearest';
        return 'now';
    }
}