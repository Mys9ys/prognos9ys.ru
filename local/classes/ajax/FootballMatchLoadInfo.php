<?php

use Bitrix\Main\Loader;

class FootballMatchLoadInfo extends PrognosisGiveInfo
{
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

    protected $arResult;

    protected $arIbs = [
        'events' => ['code' => 'events', 'id' => 1],
        'matches' => ['code' => 'matches', 'id' => 2],
        'group' => ['code' => 'group', 'id' => 5],
        'prognosis' => ['code' => 'prognosis', 'id' => 6],
        'result' => ['code' => 'result', 'id' => 7],
    ];

    public function __construct($data)
    {
        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        }

        if ($data['eventId']) $this->eventId = $data['eventId'];

        if ($data['userToken']) {
            $this->userId = (new GetUserIdForToken($data['userToken']))->getId();
        }

        $this->number = $data['number'] ?? '';

        $this->arTeams = (new GetFootballTeams())->result();

        $this->getMatchStaticData();

        if($this->arResult) $this->setResult('ok', '', $this->arResult);

    }

    protected function getMatchStaticData()
    {
        $arFilter = [
            'IBLOCK_ID' => $this->arIbs['matches']['id'],
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
                "PROPERTY_round",
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
        $el["tur"] = $res["PROPERTY_ROUND_VALUE"];
        $el["event"] = $this->eventId;
        $el["id"] = $res["ID"];

        $el["stage"] = $res["PROPERTY_STAGE_VALUE"];

        $el["home"] = $this->getTeamData($this->arTeams[$res["PROPERTY_HOME_VALUE"]]);
        $el["guest"] = $this->getTeamData($this->arTeams[$res["PROPERTY_GUEST_VALUE"]]);


        $el['prognosis'] = $this->getRecordData($this->arIbs['prognosis']['id'], $el["id"]);
        $el['match_result'] = $this->getRecordData($this->arIbs['matches']['id'], $el["id"]);
        $el['prog_result'] = $this->getRecordData($this->arIbs['result']['id'], $el["id"]);
        $el['max'] = $this->getCountMatches();

        $this->arResult = $el;

    }

    protected function getTeamData($data): array
    {
        return [
            'flag' => $data['flag'],
            'name' => $data['NAME'],
        ];
    }

    protected function getCountMatches()
    {
        $arCount = [];
        $arFilter = [
            'IBLOCK_ID' => $this->arIbs['matches']['id'],
            'PROPERTY_EVENTS' => $this->eventId,
        ];

        $recourse = CIBlockElement::GetList(
            ["DATE_ACTIVE_FROM" => "ASC", "created" => "ASC"],
            $arFilter,
            false,
            [],
            [
                "ID",
            ]
        );
        while ($res = $recourse->GetNext()) {
            $arCount[] = $res['ID'];
        }

        return count($arCount);
    }

    protected function getRecordData($ib, $matchId)
    {
        $arFilter = [
            'IBLOCK_ID' => $ib,
        ];

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
        ];

        if ($ib == 2) { // расписание матчей
            $arFilter["ID"] = $matchId;

            array_push($arSelect, "PROPERTY_stage");
        }

        if ($ib == 6) { // прогнозы
            $arFilter["PROPERTY_MATCH_ID"] = $matchId;
            $arFilter["PROPERTY_USER_ID"] = $this->userId;

            array_push($arSelect, "TIMESTAMP_X");
        }
        if ($ib == 7) { // результаты
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

        if ($ib === 6) $arr['time_send'] = $res['TIMESTAMP_X'] ?? '';
        $arr["goal_home"] = $res["PROPERTY_GOAL_HOME_VALUE"];
        $arr["goal_guest"] = $res["PROPERTY_GOAL_GUEST_VALUE"];
        $arr["goal_score"] = $ib !== 7 ? $res["PROPERTY_GOAL_HOME_VALUE"] .' - '. $res["PROPERTY_GOAL_GUEST_VALUE"] : $res["PROPERTY_SCORE_VALUE"];

        $arr["all"] = $res["PROPERTY_ALL_VALUE"];
        $arr["score"] = $res["PROPERTY_SCORE_VALUE"];
        $arr["result"] = $res["PROPERTY_RESULT_VALUE"];
        $arr["sum"] = $res["PROPERTY_SUM_VALUE"];
        $arr["diff"] = $res["PROPERTY_DIFF_VALUE"];
        $arr["domination"] = $res["PROPERTY_DOMINATION_VALUE"];
        $arr["domination2"] = $res["PROPERTY_DOMINATION_VALUE"] .' - '. (100-$res["PROPERTY_DOMINATION_VALUE"]);
        $arr["yellow"] = $res["PROPERTY_YELLOW_VALUE"];
        $arr["red"] = $res["PROPERTY_RED_VALUE"];
        $arr["corner"] = $res["PROPERTY_CORNER_VALUE"];
        $arr["penalty"] = $res["PROPERTY_PENALTY_VALUE"];
        $arr["otime"] = $res["PROPERTY_OTIME_VALUE"];
        $arr["spenalty"] = $res["PROPERTY_SPENALTY_VALUE"];
        $arr["stage"] = $res["PROPERTY_STAGE_VALUE"];
        $arr["number"] = $res["PROPERTY_NUMBER_VALUE"];
        $arr["match_id"] = $res["PROPERTY_MATCH_ID_VALUE"];

        return $arr;

    }
}