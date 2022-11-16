<?php

use Bitrix\Main\{Loader, UserTable};

class FootballOneMatch extends CBitrixComponent
{
    protected $matchesIb;
    protected $groupIb;
    protected $countriesIb;
    protected $prognosisIb;

    protected $matchId;
    protected $userId;

    protected $arCountries = [];
    protected $arGroup = [];

    public function __construct($component = null)
    {
        parent::__construct($component);
        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        }

        $this->userId = CUser::GetID();

        $this->matchesIb = \CIBlock::GetList([], ['CODE' => 'matches'], false)->Fetch()['ID'] ?: 2;
        $this->groupIb = \CIBlock::GetList([], ['CODE' => 'group'], false)->Fetch()['ID'] ?: 5;
        $this->countriesIb = \CIBlock::GetList([], ['CODE' => 'countries'], false)->Fetch()['ID'] ?: 3;
        $this->prognosisIb = \CIBlock::GetList([], ['CODE' => 'prognosis'], false)->Fetch()['ID'] ?: 6;

        $this->arCountries = $this->getTeamInfo();
        $this->arGroup = $this->getGroupInfo();

    }

    public function executeComponent()
    {

        $check = $this->checkOldPrognosis();

        $this->getMatchOtherInfo();

        $this->getMatchInfo($check);

        $this->includeComponentTemplate();
    }

    public function onPrepareComponentParams($arParams)
    {
        $this->matchId = $arParams["id"];
    }

    protected function getMatchOtherInfo(){
        $this->arFilter["IBLOCK_ID"] = $this->matchesIb;
        $this->arFilter["ID"] = $this->matchId;

        $response = CIBlockElement::GetList(
            [],
            $this->arFilter,
            false,
            [],
            [
                "ID",
                "DATE_ACTIVE_FROM",
                "PROPERTY_home",
                "PROPERTY_guest",
                "PROPERTY_group",
                "PROPERTY_stage",
                "PROPERTY_number",
            ]
        );

        $res = $response->GetNext();

        $el = [];

        $date = explode("+", ConvertDateTime($res["DATE_ACTIVE_FROM"], "d.m+H:i:s"));
        $el["date"] = $date[0];
        $el["time"] = trim($date[1], ':00') . ':00';

        $el["home"] = $this->arCountries[$res["PROPERTY_HOME_VALUE"]];

        $el["guest"] = $this->arCountries[$res["PROPERTY_GUEST_VALUE"]];

        $el["group"] = $this->arGroup[$res["PROPERTY_GROUP_VALUE"]];
        $el["number"] =$res["PROPERTY_NUMBER_VALUE"];
        $el["id"] =$res["ID"];

        $this->arResult["other"] = $el;

    }

    protected function getMatchInfo($id = '')
    {
        if($id) {
            $this->arFilter["IBLOCK_ID"] = $this->prognosisIb;
            $this->arFilter["ID"] = $id;
        } else {
            $this->arFilter["IBLOCK_ID"] = $this->matchesIb;
            $this->arFilter["ID"] = $this->matchId;
        }

        $response = CIBlockElement::GetList(
            [],
            $this->arFilter,
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
            ]
        );

        $res = $response->GetNext();

        $el = [];

        $el["home_goals"] = $res["PROPERTY_GOAL_HOME_VALUE"] ?? '';

        $el["guest_goals"] = $res["PROPERTY_GOAL_GUEST_VALUE"] ?? '';
        $el["result"] = $res["PROPERTY_RESULT_VALUE"] ?? '';
        $el["diff"] = $res["PROPERTY_DIFF_VALUE"] ?? '';
        $el["corner"] = $res["PROPERTY_CORNER_VALUE"] ?? '';
        $el["yellow"] = $res["PROPERTY_YELLOW_VALUE"] ?? '';
        $el["red"] = $res["PROPERTY_RED_VALUE"] ?? '';
        $el["penalty"] = $res["PROPERTY_PENALTY_VALUE"] ?? '';
        $el["sum"] = $res["PROPERTY_SUM_VALUE"] ?? '';
        $el["offside"] = $res["PROPERTY_OFFSIDE_VALUE"] ?? '';
        $el["domination"] = $res["PROPERTY_DOMINATION_VALUE"] ?: 50;
        $el["domination2"] = $res["PROPERTY_DOMINATION_VALUE"] ? 100 - $res["PROPERTY_DOMINATION_VALUE"]: 50;

        $this->arResult["main"] = $el;

    }

    protected function checkOldPrognosis(){

        $this->arFilter["IBLOCK_ID"] = $this->prognosisIb;
        $this->arFilter["PROPERTY_USER_ID"] = $this->userId;
        $this->arFilter["PROPERTY_ID"] = $this->matchId;

        $res = CIBlockElement::GetList(
            [],
            $this->arFilter,
            false,
            [],
            [   "ID",
            ]
        );

        $response = $res->GetNext();

        return $response["ID"];

    }

    protected function getTeamInfo()
    {

        $arr = [];

        $response = \Bitrix\Iblock\ElementTable::getList(
            [
                'select' => ['ID', 'NAME', 'PREVIEW_PICTURE'],
                'filter' => [
                    "IBLOCK_ID" => $this->countriesIb,

                ]
            ]
        );

        while ($res = $response->fetch()) {
            $res["img"] = CFile::GetPath($res["PREVIEW_PICTURE"]);
            $arr[$res["ID"]] = $res;
        }

        return $arr;
    }

    protected function getGroupInfo()
    {

        $arr = [];
        $response = \Bitrix\Iblock\ElementTable::getList(
            [
                'select' => ["ID","PREVIEW_TEXT"],
                'filter' => [
                    "IBLOCK_ID" => $this->groupIb,
                ]
            ]
        );

        while ($res = $response->fetch()) {

            $arr[$res["ID"]] = $res["PREVIEW_TEXT"];
        }

        return $arr;

    }
}
