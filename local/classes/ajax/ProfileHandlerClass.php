<?php

use Bitrix\Main\Loader;
use Bitrix\Main\UserTable;

class ProfileHandlerClass
{

    protected $data;

    protected $arResult = [];

    protected $arRes = [];

    protected $arFresh = [];

    protected $arEvents = [];

    protected $arTeams = [];

    protected $arFootballIbs = [
        'result' => ['id' => 7, 'filter' => 'PROPERTY_USER_ID', 'select' => 'PROPERTY_MATCH_ID_VALUE'],
        'matches' => ['id' => 2 , 'filter' => '', 'select' => 'ID'],
        'prognosis' => ['id' => 6, 'filter' => 'PROPERTY_USER_ID', 'select' => 'PROPERTY_MATCH_ID_VALUE'],
    ];
    
    protected $arRaceIbs = [
        'f1races' => ['code' => 'f1races', 'id' => 11],
        'prognosf1' => ['code' => 'prognosf1', 'id' => 13],
        'resultf1' => ['code' => 'resultf1', 'id' => 14]
    ];

    public function __construct($data)
    {
        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        }

        if($data['token']) {
            $this->data['userId'] = (new GetUserIdForToken($data['token']))->getId();
        } else {
            $this->data['userId'] = $data['userId'];
        }

        $this->arEvents = (new GetPrognosisEvents())->result()['events'];

        $this->data = $data;

        $this->arTeams = (new GetFootballTeams())->result();

        $this->getUserInfo();

        $this->getUserPrognosis();

        $this->setResult('ok', '');

    }

    protected function getUserInfo()
    {
        $filter['ID'] = $this->data['userId'];
        $dbUser = UserTable::getList(array(
            'select' => array('ID', 'NAME', 'PERSONAL_PHOTO', 'DATE_REGISTER'),
            'filter' => $filter
        ))->fetch();

        $dbUser['reg'] = $dbUser['DATE_REGISTER']->format("d.m.Y");

        unset($dbUser['DATE_REGISTER']);

        if($dbUser['PERSONAL_PHOTO']) $dbUser['img'] = CFile::GetPath($dbUser['PERSONAL_PHOTO']);

        if ($dbUser['ID']) {
            $this->arRes['info'] = $dbUser;
        } else {
            $this->setResult('error', 'Пользователь не найден');
        }

    }

    protected function getUserPrognosis()
    {
        foreach ($this->arFootballIbs as $code => $arr) {
            $this->getFootBallPr($arr, $code);
        }

    }

    protected function getFootBallPr($info, $code)
    {

        $arFilter = [
            "IBLOCK_ID" => $info['id'],
        ];

        if($info['filter']) $arFilter[$info['filter']] = $this->data['userId'];

        $arSelect = [
            "ID",
            "TIMESTAMP_X",
            "PROPERTY_goal_home",
            "PROPERTY_goal_guest",
            "PROPERTY_result",
            "PROPERTY_diff",
            "PROPERTY_corner",
            "PROPERTY_yellow",
            "PROPERTY_red",
            "PROPERTY_penalty",
            "PROPERTY_sum",
            "PROPERTY_offside",
            "PROPERTY_number",
            "PROPERTY_domination",
            "PROPERTY_otime",
            "PROPERTY_spenalty",
            "PROPERTY_all",
            "PROPERTY_score",

            "PROPERTY_match_id",
            "PROPERTY_events",

            "PROPERTY_home",
            "PROPERTY_guest",
        ];

        $response = CIBlockElement::GetList(
            [],
            $arFilter,
            false,
            [],
            $arSelect,
        );

        while ($res = $response->GetNext()) {

            $arr = [];

            $arr["id"] = $res[$info['select']];
            $arr["goal_home"] = $res["PROPERTY_GOAL_HOME_VALUE"];
            $arr["goal_guest"] = $res["PROPERTY_GOAL_GUEST_VALUE"];
            $arr["all"] = $res["PROPERTY_ALL_VALUE"];
            $arr["score"] = $res["PROPERTY_SCORE_VALUE"];
            $arr["result"] = $res["PROPERTY_RESULT_VALUE"];
            $arr["sum"] = $res["PROPERTY_SUM_VALUE"];
            $arr["diff"] = $res["PROPERTY_DIFF_VALUE"];
            $arr["domination"] = $res["PROPERTY_DOMINATION_VALUE"];
            $arr["yellow"] = $res["PROPERTY_YELLOW_VALUE"];
            $arr["red"] = $res["PROPERTY_RED_VALUE"];
            $arr["corner"] = $res["PROPERTY_CORNER_VALUE"];
            $arr["penalty"] = $res["PROPERTY_PENALTY_VALUE"];
            $arr["otime"] = $res["PROPERTY_OTIME_VALUE"];
            $arr["spenalty"] = $res["PROPERTY_SPENALTY_VALUE"];
            $arr["number"] = $res["PROPERTY_NUMBER_VALUE"];

            if($res["PROPERTY_HOME_VALUE"]){
                $arr['home'] = $this->arTeams[$res["PROPERTY_HOME_VALUE"]];
                $arr['guest'] = $this->arTeams[$res["PROPERTY_GUEST_VALUE"]];
            }

            $events = $res['PROPERTY_EVENTS_VALUE'];

            if ($res["PROPERTY_ALL_VALUE"] > -1) $this->arRes['info']['count'] += 1;

            $this->arFresh[$code][$events][$res[$info['select']]] = $arr;

        }

        $this->sortFootballInfo();

    }

    protected function sortFootballInfo(){
        foreach ($this->arFresh as $code=>$arEvents){
            foreach ($arEvents as $eventId=>$arMatches){
                foreach ($arMatches as $matchId=>$matchInfo){
                    $this->arRes['football'][$eventId]['matches'][$matchId][$code] = $matchInfo;
                }
                $this->arRes['football'][$eventId]['info'] = $this->arEvents[$eventId];
            }
        }

        foreach ($this->arRes['football'] as $e_id=>$event){
            $arSort = [];
            foreach ($event['matches'] as $m_id=>$arr){
                if(count($arr) ===3){
                    $arSort[$m_id] = $arr;
                }
            }
            $this->arRes['football'][$e_id]['matches'] = $arSort;

        }

    }

    protected function setResult($status, $mes, $info = '')
    {
        $this->arResult['profile'] = $this->arRes;
        $this->arResult['status'] = $status;
        $this->arResult['mes'] = $mes;
    }

    public function result()
    {
        return $this->arResult;
    }

}