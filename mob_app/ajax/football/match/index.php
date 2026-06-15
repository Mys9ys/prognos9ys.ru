<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header("Access-Control-Allow-Headers: X-Requested-With");

header('Content-Type: text/html; charset=utf-8');

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Loader;

$_REQUEST['date'] = date(\CDatabase::DateFormatToPHP("DD.MM.YYYY HH:MI:SS"), time());

file_put_contents('../../_logs/match.log', json_encode($_REQUEST) . PHP_EOL, FILE_APPEND);

if($_REQUEST){
    $res = new FootballMatchLoadInfo($_REQUEST);

    echo json_encode($res->result());
}

class FootballMatchLoadInfo {

    protected $eventsIb;
    protected $matchesIb;
    protected $groupIb;
    protected $teamsIb;
    protected $prognIb;
    protected $resultIb;

    protected $userId;
    protected $eventId;
    protected $number;

    protected $arTeams = [];

    protected $arResult = [
        'status' => 'ok'
    ];

    public function __construct($data)
    {
        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        }

        $this->eventsIb = \CIBlock::GetList([], ['CODE' => 'events'], false)->Fetch()['ID'] ?: 1;
        $this->matchesIb = \CIBlock::GetList([], ['CODE' => 'matches'], false)->Fetch()['ID'] ?: 2; // установочные данные матча
        $this->groupIb = \CIBlock::GetList([], ['CODE' => 'group'], false)->Fetch()['ID'] ?: 5;
        $this->teamsIb = \CIBlock::GetList([], ['CODE' => 'countries'], false)->Fetch()['ID'] ?: 3; //команды/страны
        $this->prognIb = \CIBlock::GetList([], ['CODE' => 'prognosis'], false)->Fetch()['ID'] ?: 6; //прогнозы
        $this->resultIb = \CIBlock::GetList([], ['CODE' => 'result'], false)->Fetch()['ID'] ?: 7; //результаты футбол

        if ($data['eventId']) $this->eventId = $data['eventId'];

        if ($data['userToken']) {
            $userRes = new GetUserIdForToken($data['userToken']);
            $this->userId = $userRes->getId();
        }

        $this->number = $data['number'] ?? '';

        $team = new GetFootballTeams();
        $this->arTeams = $team->result();

        $this->getMatchStaticData();

    }

    protected function getMatchStaticData()
    {
        $arFilter = [
            'IBLOCK_ID' => $this->matchesIb,
            'PROPERTY_EVENTS' => $this->eventId,
            'PROPERTY_NUMBER' => $this->number
        ];

        $res = CIBlockElement::GetList(
            ["DATE_ACTIVE_FROM" => "ASC", "created" => "ASC"],
            $arFilter,
            false,
            [],
            [
                "ID",
                "ACTIVE",
                "DATE_ACTIVE_FROM",
                "PROPERTY_home",
                "PROPERTY_guest",
                "PROPERTY_group",
                "PROPERTY_stage",
                "PROPERTY_number",
                "PROPERTY_events",
                "PROPERTY_step",
            ]
        )->GetNext();

//            $this->arNumberToMatchId[$res["PROPERTY_NUMBER_VALUE"]] = $res['ID'];
            $el = [];

            $date = explode("+", ConvertDateTime($res["ACTIVE_FROM"], "DD.MM+HH:Mi"));

            $el["date"] = $date[0];
            $el["time"] = $date[1];

            $el["active"] = $res["ACTIVE"];
            $el["number"] = $res["PROPERTY_NUMBER_VALUE"];
            $el["step"] = $res["PROPERTY_STEP_VALUE"];
            $el["event"] = $this->eventId;
            $el["id"] = $res["ID"];

            $el["home"] = $this->getTeamData($this->arTeams[$res["PROPERTY_HOME_VALUE"]]);
            $el["guest"] = $this->getTeamData($this->arTeams[$res["PROPERTY_GUEST_VALUE"]]);

//            $el["write"] = $this->arUserPrognosis[$res["ID"]] ?? '';
            if ($this->eventId === 34) {
                $this->arNumbertoMatchId[$res["ID"]] = $el["number"];
//                $this->getUserPrognosisOld($res["ID"]);
//                $this->getUserResultOld($res["ID"]);
            }

        $el['prognosis'] = $this->getRecordData($this->prognIb, $el["id"]);
        $el['match_result'] = $this->getRecordData($this->matchesIb, $el["id"]);
        $el['prog_result'] = $this->getRecordData($this->resultIb, $el["id"]);

        $this->arResult['match'] = $el;

    }

    protected function getTeamData($data): array
    {
        return [
            'flag' => $data['flag'],
            'name' => $data['NAME'],
        ];
    }

    protected function getRecordData($ib, $matchId){
        $arFilter = [
            'IBLOCK_ID' => $ib,
        ];

        $arSelect= [
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
        ];      

        if($ib === 2) { // расписание матчей
            $arFilter["ID"] = $matchId;
        }

        if($ib === 6) { // прогнозы
            $arFilter["PROPERTY_MATCH_ID"] = $matchId;
            $arFilter["PROPERTY_USER_ID"] = $this->userId;
        }
        if($ib === 7) { // результаты
            $arFilter["PROPERTY_MATCH_ID"] = $matchId;
            $arFilter["PROPERTY_USER_ID"] = $this->userId;

            array_push($arSelect, "PROPERTY_all");
            array_push($arSelect, "PROPERTY_score");

        }

        $res = CIBlockElement::GetList(
            [],
            $arFilter,
            false,
            [],
            $arSelect,
        )->GetNext();

        $arr = [];

        $arr["id"] = $res["ID"];
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

        return $arr;

    }

    public function result()
    {
        return $this->arResult;
    }
}