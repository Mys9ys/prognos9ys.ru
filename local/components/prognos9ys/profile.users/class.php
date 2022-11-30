<?php

use Bitrix\Main\{Loader, UserTable};

class ProfileUsers extends CBitrixComponent{

    protected $userId;

    protected $matchesIb;
    protected $prognosisIb;
    protected $countriesIb;
    protected $resultIb;

    protected $arCountries = [];
    protected $arCloseManches = [];
    protected $arPrognosis = [];
    protected $arUserResult = [];

    public function __construct($component = null)
    {
        parent::__construct($component);

        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        };

        $this->prognosisIb = \CIBlock::GetList([], ['CODE' => 'prognosis'], false)->Fetch()['ID'] ?: 6;
        $this->matchesIb = \CIBlock::GetList([], ['CODE' => 'matches'], false)->Fetch()['ID'] ?: 2;
        $this->countriesIb = \CIBlock::GetList([], ['CODE' => 'countries'], false)->Fetch()['ID'] ?: 3;

        $this->resultIb = \CIBlock::GetList([], ['CODE' => 'result'], false)->Fetch()['ID'] ?: 7;

        $this->arCountries = $this->getTeamInfo();

    }


    public function executeComponent()
    {
        if($this->userId) {

            $user =[];
            $dbUser = UserTable::getList(array(
                'select' => array('ID', 'NAME', 'PERSONAL_PHOTO', 'PERSONAL_PAGER', 'WORK_PAGER'),
                'filter' => array('ID' => $this->userId)
            ));
            if ($arUser = $dbUser->fetch()){

                $user['name'] = $arUser['NAME'];
                $user['id'] = $arUser['ID'];
                $user['ref_link'] = 'https://prognos9ys.ru/auth/?register=yes&ref=' . $arUser['PERSONAL_PAGER'];
                $user['ref_nik'] = '';
               
                if($arUser['WORK_PAGER']) $user['ref_nik'] = $this->getRefNik($arUser['WORK_PAGER']) ?: '';

                if($arUser['PERSONAL_PAGER']) $user['you_ref'] = $this->getRefUsers($arUser['PERSONAL_PAGER']);
                $this->arResult = $user;
            }
        }

        $this->getUserPrognosis();
        $this->getUserScore();

        $this->getCloseMatches();

        $this->includeComponentTemplate();
    }

    public function onPrepareComponentParams($arParams)
    {
        $this->userId = $arParams["id"];
    }

    /**
     * @return string
     */
    protected function getRefNik($ref)
    {
        $dbUser = UserTable::getList(array(
            'select' => array('NAME'),
            'filter' => array('PERSONAL_PAGER' => $ref)
        ))->fetch();

        return $dbUser["NAME"];
    }

    protected function getRefUsers($ref)
    {
        $arr = [];
        $arrActive = [];
        $dbUser = UserTable::getList(array(
            'select' => array('ID'),
            'filter' => array('WORK_PAGER' => $ref)
        ));
        
        while($res = $dbUser->fetch()){
            $arr[] = $res["ID"];
            $arrActive[] = ["ID"];
        }

        return ["count" => count($arr), "active" => count($arrActive)];
    }

    protected function getTeamInfo(){

        $arr = [];

        $response = \Bitrix\Iblock\ElementTable::getList(
            [
                'select' => ['ID', 'NAME'],
                'filter' => [
                    "IBLOCK_ID" => $this->countriesIb,

                ]
            ]
        );

        while ($res = $response->fetch()) {
            $arr[$res["ID"]] = $res;
        }

        return $arr;

    }

    protected function getUserPrognosis()
    {

        $arFilter["IBLOCK_ID"] = $this->prognosisIb;
        $arFilter["=PROPERTY_USER_ID"] = $this->userId;

        $response = CIBlockElement::GetList(
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
                "PROPERTY_user_id",
                "PROPERTY_domination",
            ]
        );

        while ($res = $response->GetNext()){

            $el = [];

            if($res["PROPERTY_NUMBER_VALUE"]){
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

                $this->arPrognosis[$res["PROPERTY_NUMBER_VALUE"]] = $el;
            }
        }
    }

    protected function getCloseMatches(){

        $arFilter = [];
        $arFilter["IBLOCK_ID"] = $this->matchesIb;
        $arFilter["ACTIVE"] = 'N';

        $response = CIBlockElement::GetList(
            [],
            $arFilter,
            false,
            [],
            [
                "ID",
                "PROPERTY_home",
                "PROPERTY_guest",
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
                "PROPERTY_number",
                "PROPERTY_domination",
            ]
        );

        while($res = $response->GetNext()){
            $arr = [];

            $arr["name"] = $this->arCountries[$res["PROPERTY_HOME_VALUE"]] . ' - ' . $this->arCountries[$res["PROPERTY_HOME_VALUE"]];

            $arr["score"] = $res["PROPERTY_GOAL_HOME_VALUE"] . ' - ' . $res["PROPERTY_GOAL_GUEST_VALUE"];
            $arr["result"] = $res["PROPERTY_RESULT_VALUE"];
            $arr["sum"] = $res["PROPERTY_SUM_VALUE"];
            $arr["diff"] = $res["PROPERTY_DIFF_VALUE"];
            $arr["domination"] = $res["PROPERTY_DOMINATION_VALUE"] . ' - ' . (100 - $res["PROPERTY_DOMINATION_VALUE"]);
            $arr["yellow"] = $res["PROPERTY_YELLOW_VALUE"];
            $arr["red"] = $res["PROPERTY_RED_VALUE"];
            $arr["corner"] = $res["PROPERTY_CORNER_VALUE"];
            $arr["penalty"] = $res["PROPERTY_PENALTY_VALUE"];

            if($this->arUserResult[$res["ID"]]){
                $this->arResult["items"][$res["PROPERTY_NUMBER_VALUE"]]["match_result"] = $arr;
                $this->arResult["items"][$res["PROPERTY_NUMBER_VALUE"]]["match_prognosis"] = $this->arPrognosis[$res["PROPERTY_NUMBER_VALUE"]];
                $this->arResult["items"][$res["PROPERTY_NUMBER_VALUE"]]["user_score"] = $this->arUserResult[$res["ID"]];
            }

        }
    }

    protected function getUserScore(){

        $arFilter = [];
        $arFilter["IBLOCK_ID"] = $this->resultIb;
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
                "PROPERTY_match_id",
                "PROPERTY_user",
                "PROPERTY_domination",
            ]
        );

        while($res = $response->GetNext()) {
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

            $this->arUserResult[$res["PROPERTY_MATCH_ID_VALUE"]] = $arr;
        }

    }

    protected function greenWrap($val){
        return $val ? '<span class="text-success">'.$val.'</span>' : 0;
    }
}