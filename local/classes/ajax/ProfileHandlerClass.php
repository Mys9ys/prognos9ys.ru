<?php

use Bitrix\Main\Loader;
use Bitrix\Main\UserTable;

class ProfileHandlerClass
{

    protected $data;

    protected $arResult = [];

    protected $arRes = [];

    protected $arEvents = [];
    protected $arTeams = [];

    protected $arCountry = [];
    protected $arRacers = [];

    protected $arStageName = [
        'qual' => 'Квалификация',
        'race' => 'Гонка',
        'sprint' => 'Спринт',
        'lap' => 'Лучший круг',
        'all' => 'Итого',
    ];

    protected $arEntity = [
        'football',
        'race',
    ];

    protected $arFootballIbs = [
        'result' => ['id' => 7, 'filter' => 'PROPERTY_USER_ID', 'select' => 'PROPERTY_MATCH_ID_VALUE'],
        'matches' => ['id' => 2 , 'filter' => '', 'select' => 'ID'],
        'prognosis' => ['id' => 6, 'filter' => 'PROPERTY_USER_ID', 'select' => 'PROPERTY_MATCH_ID_VALUE'],
    ];
    
    protected $arRaceIbs = [
        'f1races' => ['code' => 'f1races', 'id' => 11, 'filter' => '', 'select' => 'ID'],
        'prognosf1' => ['code' => 'prognosf1', 'id' => 13, 'filter' => 'PROPERTY_USER_ID', 'select' => 'PROPERTY_RACE_ID_VALUE'],
        'resultf1' => ['code' => 'resultf1', 'id' => 14, 'filter' => 'PROPERTY_USER_ID', 'select' => 'PROPERTY_RACE_ID_VALUE']
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

        $this->data = $data;

        $this->arEvents = (new GetPrognosisEvents())->result()['events'];
        $this->arTeams = (new GetFootballTeams())->result();

        $this->arCountry = (new GetFootballTeams())->result();
        $this->arRacers = (new GetF1RacersClass())->result();

        $this->getUserInfo();

        $this->getUserPrognosis();

        foreach ($this->arEntity as $entity){
            $this->sortComplexArray($entity);
        }

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

        foreach ($this->arRaceIbs as $code => $arr){
            $this->getRaceData($arr, $code);
        }

    }

    protected function getRaceData($arr, $code){
        $arFilter = [
            "IBLOCK_ID" => $arr['id'],
        ];

        if($arr['filter']) $arFilter[$arr['filter']] = $this->data['userId'];

        $arSelect = [
            "ID",
            "TIMESTAMP_X",
            'NAME',
            'PREVIEW_PICTURE',
            'ACTIVE_FROM',
            'ACTIVE_TO',
            'ACTIVE',
            'PROPERTY_country',
            'PROPERTY_number',
            'PROPERTY_sprint',
            'PROPERTY_events',
            'PROPERTY_status',

            'PROPERTY_race_id',
            'PROPERTY_all',
            'PROPERTY_qual_sum',
            'PROPERTY_race_sum',
            'PROPERTY_sprint_sum',
            'PROPERTY_qual_res',
            'PROPERTY_race_res',
            'PROPERTY_sprint_res',
            'PROPERTY_best_lap',

        ];

        $response = CIBlockElement::GetList(
            [],
            $arFilter,
            false,
            [],
            $arSelect,
        );

        while ($res = $response->GetNext()) {

            $el = [];

            $el["country"] = $this->arCountry[$res["PROPERTY_COUNTRY_VALUE"]];

            $el['qual_sum'] = $res['PROPERTY_QUAL_SUM_VALUE'] ?? 0;
            $el['race_sum'] = $res['PROPERTY_RACE_SUM_VALUE'] ?? 0;
            $el['sprint_sum'] = $res['PROPERTY_SPRINT_SUM_VALUE'] ?? 0;
            $el['all'] = $res['PROPERTY_ALL_VALUE'] ?? 0;

            $el["qual"] = $this->convertData($res["ACTIVE_FROM"]);

            $el["date"] = $el["qual"]["date"];
            $el["active"] = $res["ACTIVE"];
            $el["id"] = $res["ID"];

            $events = $res['PROPERTY_EVENTS_VALUE'];

            if($events) $this->arRes['race'][$events]['info'] = $this->arEvents[$events];

            $el["status"] = $res["PROPERTY_STATUS_VALUE"];

            $el["race"] = $this->convertData($res["ACTIVE_TO"]);

            $el["qual_res"]= json_decode($res["~PROPERTY_QUAL_RES_VALUE"]["TEXT"]) ?? [];
            $el["race_res"]= json_decode($res["~PROPERTY_RACE_RES_VALUE"]["TEXT"]) ?? [];
            $el["sprint_res"]= json_decode($res["~PROPERTY_SPRINT_RES_VALUE"]["TEXT"]) ?? [];
            $el["best_lap"]= json_decode($res["~PROPERTY_BEST_LAP_VALUE"]) ?? [];


            if ($res["PROPERTY_SPRINT_VALUE"]) {
                $el["sprint"] = $this->convertData($res["PROPERTY_SPRINT_VALUE"]);
            }

            $el["name"] = $res["NAME"];

            $el["number"] = $res["PROPERTY_NUMBER_VALUE"];

            $this->arRes['race'][$events]['items'][$el["number"]][$code] = $el;


            if($code == 'f1races'){
                $item = [];
                $item['stage']['qual']['info'] = $el["qual"];
                if($el["sprint"]) $item['stage']['sprint']['info'] = $el["sprint"];
                $item['stage']['race']['info'] = $el["race"];
                $item['stage']['lap']['info'] = '';
                $item['stage']['all']['info'] = '';
                $this->arRes['race'][$events]['items'][$el["number"]]['f1races']['stage'] = $item['stage'];

                foreach ($item['stage'] as $stageName=>$a){
                    $this->arRes['race'][$events]['items'][$el["number"]]['f1races']['stage'][$stageName]['name'] = $this->arStageName[$stageName];
                }
            }

            if($code == 'resultf1'){
                $item = [];
                $item['qual'] = $el['qual_sum'];
                $item['race'] = $el['race_sum'];
                $item['lap'] = $el["best_lap"][0];
                $item['all'] =  $el['all'];
                if($el["sprint_sum"]) $item['sprint'] = $el['sprint_sum'];
                foreach ($item as $name=>$sum){
                    $this->arRes['race'][$events]['items'][$el["number"]]['f1races']['stage'][$name]['sum'] = $sum;
                }

            }

        }
    }

    protected function convertData($data)
    {
        $date = explode("+", ConvertDateTime($data, "DD.MM+HH:Mi"));

        return [
            "date" => $date[0],
            "time" => $date[1]
        ];
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
            if(!$this->arRes['football'][$events]['info']) $this->arRes['football'][$events]['info'] = $this->arEvents[$events];

            if ($res["PROPERTY_ALL_VALUE"] > -1) $this->arRes['football'][$events]['info']['count'] += 1;

//            $this->arFresh[$code][$events][$arr["number"]] = $arr;

                $this->arRes['football'][$events]['items'][$arr["number"]][$code] = $arr;


        }

    }

    protected function sortComplexArray($selector){

        foreach ($this->arRes[$selector] as $e_id=>$event){
            $arSort = [];
            foreach ($event['items'] as $m_id=>$arr){
                if(count($arr) ===3){ // проверка на наличие всех 3х массивов протокола, результата и ставки
                    $arSort[$m_id] = $arr;
                }
            }
            $this->arRes[$selector][$e_id]['items'] = $arSort;
        }
    }

    protected function setResult($status, $mes, $info = '')
    {
        $this->arResult['profile'] = $this->arRes;
        $this->arResult['profile']["racers"] = $this->arRacers;
        $this->arResult['status'] = $status;
        $this->arResult['mes'] = $mes;
    }

    public function result()
    {
        return $this->arResult;
    }

}