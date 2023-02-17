<?php

use Bitrix\Main\{Loader, UserTable};

class KVNGame extends CBitrixComponent
{

    protected $teamsIb;

    protected $gameIb;
    protected $prognosisIb;

    protected $resultIb;

    protected $gameId;
    protected $numberId;
    protected $userId;

    protected $arTeams = [];
    protected $actEvent = '';


    public function __construct($component = null)
    {
        parent::__construct($component);
        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        }


        $this->teamsIb = \CIBlock::GetList([], ['CODE' => 'kvnteams'], false)->Fetch()['ID'] ?: 10;
        $this->gameIb = \CIBlock::GetList([], ['CODE' => 'kvngame'], false)->Fetch()['ID'] ?: 17;
        $this->prognosisIb = \CIBlock::GetList([], ['CODE' => 'prognoskvn'], false)->Fetch()['ID'] ?: 16;

        $this->resultIb = \CIBlock::GetList([], ['CODE' => 'resultkvn'], false)->Fetch()['ID'] ?: 15;

        $this->getUserInfo();

        $this->getTeamInfo();

    }

    public function executeComponent()
    {
        $this->getGameId();

        $check = $this->checkOldPrognosis();

        $this->getGameMainInfo();

        var_dump('dsgd');
        die();

        $this->getMatchInfo($check);

        $this->getMatchResult();
        $this->getUserScore();

        $this->includeComponentTemplate();

        $this->arResult['event'] = $this->actEvent;
    }

    public function onPrepareComponentParams($arParams)
    {
        $this->numberId = $arParams["id"];
    }

    protected function getUserInfo()
    {
        $uid = CUser::GetID();

        if ($uid) {
            $dbUser = UserTable::getList(array(
                'select' => array('ID', 'UF_EVENT'),
                'filter' => array('=ID' => $uid)
            ))->fetch();
            $this->userId = $dbUser["ID"];
        }

        $this->arResult['event'] = $this->actEvent;
    }

    protected function getGameId(){
        $arFilter["IBLOCK_ID"] = $this->gameIb;
        $arFilter["PROPERTY_NUMBER"] = $this->numberId;
        $arFilter["PROPERTY_EVENTS"] = $this->actEvent;

        $response = CIBlockElement::GetList(
            [],
            $arFilter,
            false,
            [],
            [
                "ID",
            ]
        )->GetNext();

        $this->gameId = $response["ID"];

    }

    protected function getGameMainInfo(){
        $arFilter["IBLOCK_ID"] = $this->gameIb;
        $arFilter["ID"] = $this->gameId;
        $arFilter["PROPERTY_EVENTS"] = $this->actEvent;

        $response = CIBlockElement::GetList(
            [],
            $arFilter,
            false,
            [],
            [
                "ID",
                "ACTIVE",
                "DATE_ACTIVE_FROM",
                "PROPERTY_number",
//                "PROPERTY_teams",
                "PROPERTY_stage1",
                "PROPERTY_stage2",
                "PROPERTY_stage3",
                "PROPERTY_result",
                "PROPERTY_events",
            ]
        );

        $res = $response->GetNext();

        $el = [];

        $date = explode("+", ConvertDateTime($res["DATE_ACTIVE_FROM"], "d.m+H:i:s"));
        $el["date"] = $date[0];
        $el["time"] = substr($date[1], 0,-3);

        $el["active"] = $res["ACTIVE"];

        $el["number"] =$res["PROPERTY_NUMBER_VALUE"];
        $el["id"] =$res["ID"];

        $el["teams"] = $this->getMultiValue($this->gameId,$this->gameIb);

        var_dump($el);

        $this->arResult["main"] = $el;

    }

    protected function getMultiValue($id, $ibId){
        // выгрузка множественного свойства через костыль

        $elem[$id] = [];
        CIBlockElement::GetPropertyValuesArray($elem, $ibId, $ibId, ["CODE" => "teams"]);

        $arElem = $elem[$id]["teams"]["VALUE"];

        $arTeam = [];

        foreach ($arElem as $item){
            $arTeam[$item] = $this->arTeams[$item];
        }

       return $arTeam;
    }

    protected function getMatchInfo($id = '')
    {

        $arFilter["IBLOCK_ID"] = $this->prognosisIb;
        $arFilter["PROPERTY_ID"] = $this->matchId;
        $arFilter["PROPERTY_USER_ID"] = $this->userId;

        $res = CIBlockElement::GetList(
            [],
            $arFilter,
            false,
            [],
            [
                "ID",
                "TIMESTAMP_X",
                "PROPERTY_goal_home",
                "PROPERTY_goal_guest",
                "PROPERTY_id",
                "PROPERTY_result",
                "PROPERTY_diff",
                "PROPERTY_corner",
                "PROPERTY_yellow",
                "PROPERTY_red",
                "PROPERTY_penalty",
                "PROPERTY_sum",
                "PROPERTY_offside",
                "PROPERTY_number",
                "PROPERTY_user",
                "PROPERTY_domination",
                "PROPERTY_otime",
                "PROPERTY_spenalty",
            ]
        )->GetNext();

        $el = [];

        if($id){
            $el["rewrite"] = $res["TIMESTAMP_X"] ?? '';
        }

        if(!$res["PROPERTY_NUMBER_VALUE"]){
            $this->arResult["null_prognosis"] = true;
        }

        $el["home_goals"] = $res["PROPERTY_GOAL_HOME_VALUE"] ?? 0;

        $el["guest_goals"] = $res["PROPERTY_GOAL_GUEST_VALUE"] ?? 0;
        $el["result"] = $res["PROPERTY_RESULT_VALUE"] ?? 'н';
        $el["diff"] = $res["PROPERTY_DIFF_VALUE"] ?? 0;
        $el["corner"] = $res["PROPERTY_CORNER_VALUE"] ?? '';
        $el["yellow"] = $res["PROPERTY_YELLOW_VALUE"] ?? '';
        $el["red"] = $res["PROPERTY_RED_VALUE"] ?? '';
        $el["penalty"] = $res["PROPERTY_PENALTY_VALUE"] ?? '';
        $el["sum"] = $res["PROPERTY_SUM_VALUE"] ?? 0;
        $el["offside"] = $res["PROPERTY_OFFSIDE_VALUE"] ?? '';
        $el["domination"] = $res["PROPERTY_DOMINATION_VALUE"] ?: 50;
        $el["domination2"] = $res["PROPERTY_DOMINATION_VALUE"] ? 100 - $res["PROPERTY_DOMINATION_VALUE"]: 50;
        $el["otime"] = $res["PROPERTY_OTIME_VALUE"];
        $el["spenalty"] = $res["PROPERTY_SPENALTY_VALUE"];

        $this->arResult["main"] = $el;

    }

    protected function checkOldPrognosis(){

        $arFilter["IBLOCK_ID"] = $this->prognosisIb;
        $arFilter["PROPERTY_USER_ID"] = $this->userId;
        $arFilter["PROPERTY_ID"] = $this->gameId;

        $res = CIBlockElement::GetList(
            [],
            $arFilter,
            false,
            [],
            [   "ID",
            ]
        )->GetNext();

        return $res["ID"];

    }

    protected function getTeamInfo()
    {

        $arr = [];

        $response = \Bitrix\Iblock\ElementTable::getList(
            [
                'select' => ['ID', 'NAME', 'PREVIEW_PICTURE'],
                'filter' => [
                    "IBLOCK_ID" => $this->teamsIb,

                ]
            ]
        );

        while ($res = $response->fetch()) {
            $res["img"] = CFile::GetPath($res["PREVIEW_PICTURE"]);
            $arr[$res["ID"]] = $res;
        }

        $this->arTeams = $arr;

    }

    protected function getMatchResult(){
        $arFilter = [];
        $arFilter["IBLOCK_ID"] = $this->matchesIb;
        $arFilter["ID"] = $this->matchId;

        $response = CIBlockElement::GetList(
            [],
            $arFilter,
            false,
            [],
            [
                "ID",
                "PROPERTY_goal_home",
                "PROPERTY_goal_guest",
                "PROPERTY_id",
                "PROPERTY_result",
                "PROPERTY_diff",
                "PROPERTY_corner",
                "PROPERTY_yellow",
                "PROPERTY_red",
                "PROPERTY_penalty",
                "PROPERTY_sum",
                "PROPERTY_offside",
                "PROPERTY_number",
                "PROPERTY_user",
                "PROPERTY_domination",
                "PROPERTY_otime",
                "PROPERTY_spenalty",
            ]
        );

        $res = $response->GetNext();

        $arr = [];

        $arr["score"] = $res["PROPERTY_GOAL_HOME_VALUE"] . ' - ' . $res["PROPERTY_GOAL_GUEST_VALUE"];
        $arr["result"] = $res["PROPERTY_RESULT_VALUE"];
        $arr["sum"] = $res["PROPERTY_SUM_VALUE"];
        $arr["diff"] = $res["PROPERTY_DIFF_VALUE"];
        $arr["domination"] = $res["PROPERTY_DOMINATION_VALUE"] . ' - ' . (100 - $res["PROPERTY_DOMINATION_VALUE"]);
        $arr["yellow"] = $res["PROPERTY_YELLOW_VALUE"];
        $arr["red"] = $res["PROPERTY_RED_VALUE"];
        $arr["corner"] = $res["PROPERTY_CORNER_VALUE"];
        $arr["penalty"] = $res["PROPERTY_PENALTY_VALUE"];
        $arr["otime"] = $res["PROPERTY_OTIME_VALUE"];
        $arr["spenalty"] = $res["PROPERTY_SPENALTY_VALUE"];

        $this->arResult["match_result"] = $arr;
    }

    protected function getUserScore(){

        $arFilter["IBLOCK_ID"] = $this->resultIb;
        $arFilter["PROPERTY_MATCH_ID"] = $this->matchId;
        $arFilter["PROPERTY_USER_ID"] = $this->userId;


        $response = CIBlockElement::GetList(
            [],
            $arFilter,
            false,
            [],
            [
                "ID",
                "PROPERTY_goal_home",
                "PROPERTY_goal_guest",
                "PROPERTY_id",
                "PROPERTY_all",
                "PROPERTY_score",
                "PROPERTY_result",
                "PROPERTY_diff",
                "PROPERTY_corner",
                "PROPERTY_yellow",
                "PROPERTY_red",
                "PROPERTY_penalty",
                "PROPERTY_sum",
                "PROPERTY_offside",
                "PROPERTY_number",
                "PROPERTY_user",
                "PROPERTY_domination",
                "PROPERTY_otime",
                "PROPERTY_spenalty",
            ]
        );

        $res = $response->GetNext();

        $arr = [];

        $arr["all"] = $this->greenWrap($res["PROPERTY_ALL_VALUE"]);
        $arr["score"] = $this->greenWrap($res["PROPERTY_SCORE_VALUE"]);
        $arr["result"] = $this->greenWrap($res["PROPERTY_RESULT_VALUE"]);
        $arr["sum"] = $this->greenWrap($res["PROPERTY_SUM_VALUE"]);
        $arr["diff"] = $this->greenWrap($res["PROPERTY_DIFF_VALUE"]);
        $arr["domination"] = $this->greenWrap($res["PROPERTY_DOMINATION_VALUE"]);
        $arr["yellow"] = $this->greenWrap($res["PROPERTY_YELLOW_VALUE"] );
        $arr["red"] = $this->greenWrap($res["PROPERTY_RED_VALUE"]);
        $arr["corner"] = $this->greenWrap($res["PROPERTY_CORNER_VALUE"]);
        $arr["penalty"] = $this->greenWrap($res["PROPERTY_PENALTY_VALUE"]);
        $arr["otime"] = $this->greenWrap($res["PROPERTY_OTIME_VALUE"]);
        $arr["spenalty"] = $this->greenWrap($res["PROPERTY_SPENALTY_VALUE"]);

        $this->arResult["user_score"] = $arr;
    }

    protected function greenWrap($val){
        return $val ? '<span class="text-success">'.$val.'</span>' : 0;
    }
}
