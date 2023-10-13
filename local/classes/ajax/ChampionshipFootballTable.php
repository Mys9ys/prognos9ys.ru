<?php

use Bitrix\Main\Loader;

class ChampionshipFootballTable extends PrognosisGiveInfo
{
    protected $data;
    protected $arIbs = [
        'matches' => ['code' => 'matches', 'id' => 2],
    ];

    protected $teamsIds;

    protected $arTable;
    protected $arTableInfo;
    protected $arTableUnsort;

    public function __construct($data)
    {

        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        }

        $this->data = $data;

        $this->getTeamsOneTurids();

        $arEvents = (new GetPrognosisEvents())->result()['events'];

        if (count($this->teamsIds)) $this->getTeamsInfo();

        $this->calcAllTurs();

        if (count($this->arTable)) $this->setResult('ok', '', ['teams' => $this->arTable, 'info' => $arEvents[$this->data['events']]]);

    }

    protected function getTeamsOneTurIds()
    {
        $arFilter = [
            'IBLOCK_ID' => $this->arIbs['matches']['id'],
            'PROPERTY_events' => $this->data['events'],
            'PROPERTY_round' => 1
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

        while ($res = $response->GetNext()) {
            $this->teamsIds[] = $res['PROPERTY_HOME_VALUE'];
            $this->teamsIds[] = $res['PROPERTY_GUEST_VALUE'];
        }
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
            'ACTIVE' => "N",
        ];

        $response = CIBlockElement::GetList(
            [],
            $arFilter,
            false,
            [],
            [
                "PROPERTY_home",
                "PROPERTY_guest",
                "PROPERTY_goal_home",
                "PROPERTY_goal_guest",
                "PROPERTY_result",
            ]
        );

        while ($res = $response->GetNext()) {

            if(!$this->arTableUnsort[$res['PROPERTY_HOME_VALUE']]['win']) $this->arTableUnsort[$res['PROPERTY_HOME_VALUE']]['win'] = 0;
            if(!$this->arTableUnsort[$res['PROPERTY_GUEST_VALUE']]['win']) $this->arTableUnsort[$res['PROPERTY_GUEST_VALUE']]['win'] = 0;

            $this->arTableUnsort[$res['PROPERTY_HOME_VALUE']]['score'] += $this->getScore($res['PROPERTY_RESULT_VALUE'], 'home');
            $this->arTableUnsort[$res['PROPERTY_GUEST_VALUE']]['score'] += $this->getScore($res['PROPERTY_RESULT_VALUE']);

            $this->arTableUnsort[$res['PROPERTY_HOME_VALUE']]['matches']++;
            $this->arTableUnsort[$res['PROPERTY_GUEST_VALUE']]['matches']++;

            if (!$this->arTableUnsort[$res['PROPERTY_HOME_VALUE']]['info']) $this->arTableUnsort[$res['PROPERTY_HOME_VALUE']]['info'] = $this->arTableInfo[$res['PROPERTY_HOME_VALUE']];
            if (!$this->arTableUnsort[$res['PROPERTY_GUEST_VALUE']]['info']) $this->arTableUnsort[$res['PROPERTY_GUEST_VALUE']]['info'] = $this->arTableInfo[$res['PROPERTY_GUEST_VALUE']];

            switch ($res['PROPERTY_RESULT_VALUE']) {
                case 'п1':
                    $this->arTableUnsort[$res['PROPERTY_HOME_VALUE']]['win']++;
                    $this->arTableUnsort[$res['PROPERTY_GUEST_VALUE']]['lose']++;
                    break;
                case 'н':
                    $this->arTableUnsort[$res['PROPERTY_HOME_VALUE']]['draw']++;
                    $this->arTableUnsort[$res['PROPERTY_GUEST_VALUE']]['draw']++;
                    break;
                case 'п2':
                    $this->arTableUnsort[$res['PROPERTY_GUEST_VALUE']]['win']++;
                    $this->arTableUnsort[$res['PROPERTY_HOME_VALUE']]['lose']++;
                    break;
            }

            $this->arTableUnsort[$res['PROPERTY_HOME_VALUE']]['plus'] += $res['PROPERTY_GOAL_HOME_VALUE'];
            $this->arTableUnsort[$res['PROPERTY_GUEST_VALUE']]['plus'] += $res['PROPERTY_GOAL_GUEST_VALUE'];

            $this->arTableUnsort[$res['PROPERTY_HOME_VALUE']]['minus'] += $res['PROPERTY_GOAL_GUEST_VALUE'];
            $this->arTableUnsort[$res['PROPERTY_GUEST_VALUE']]['minus'] += $res['PROPERTY_GOAL_HOME_VALUE'];

            $this->arTableUnsort[$res['PROPERTY_HOME_VALUE']]['diff'] =
                $this->arTableUnsort[$res['PROPERTY_HOME_VALUE']]['plus'] - $this->arTableUnsort[$res['PROPERTY_HOME_VALUE']]['minus'];
            $this->arTableUnsort[$res['PROPERTY_GUEST_VALUE']]['diff'] =
                $this->arTableUnsort[$res['PROPERTY_GUEST_VALUE']]['plus'] - $this->arTableUnsort[$res['PROPERTY_GUEST_VALUE']]['minus'];

        }

        array_multisort(
            array_column($this->arTableUnsort, 'score'), SORT_DESC, SORT_NUMERIC,
            array_column($this->arTableUnsort, 'win'), SORT_DESC, SORT_NUMERIC,
            array_column($this->arTableUnsort, 'diff'), SORT_DESC, SORT_NUMERIC,
            array_column($this->arTableUnsort, 'plus'), SORT_DESC, SORT_NUMERIC,
            $this->arTableUnsort);

        if($this->arTableUnsort) $this->arTable = $this->arTableUnsort;
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