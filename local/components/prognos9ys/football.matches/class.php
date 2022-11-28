<?php

use Bitrix\Main\{Loader, UserTable};

class FootballMatches extends CBitrixComponent
{
    protected $matchesIb;
    protected $groupIb;
    protected $countriesIb;
    protected $prognosisIb;

    protected $arCountries = [];
    protected $arGroup = [];
    protected $arUserPrognosis = [];

    protected $userId;

    public function __construct($component = null)
    {
        parent::__construct($component);
        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        }

        $this->arResult["active_count"] = 0;
        $this->arResult["not_active_count"] = 0;

        $this->matchesIb = \CIBlock::GetList([], ['CODE' => 'matches'], false)->Fetch()['ID'] ?: 2;
        $this->groupIb = \CIBlock::GetList([], ['CODE' => 'group'], false)->Fetch()['ID'] ?: 5;
        $this->countriesIb = \CIBlock::GetList([], ['CODE' => 'countries'], false)->Fetch()['ID'] ?: 3;
        $this->prognosisIb = \CIBlock::GetList([], ['CODE' => 'prognosis'], false)->Fetch()['ID'] ?: 6;

        $this->userId = CUser::GetID();

        if($this->userId) $this->getUserPrognosis();

        $this->arCountries = $this->getTeamInfo();
        $this->arGroup = $this->getGroupInfo();

    }

    public function executeComponent()
    {

        $this->arFilter["IBLOCK_ID"] = $this->matchesIb;


        $response = CIBlockElement::GetList(
            ["DATE_ACTIVE_FROM" => "ASC"],
            $this->arFilter,
            false,
            [
//                "nTopCount" => 6
            ],
            [
                "ID",
                "ACTIVE",
                "DATE_ACTIVE_FROM",
                "PROPERTY_home",
                "PROPERTY_goal_home",
                "PROPERTY_guest",
                "PROPERTY_goal_guest",
                "PROPERTY_group",
                "PROPERTY_stage",
                "PROPERTY_number",
            ]
        );

        while ($res = $response->GetNext()) {
            $el = [];

            $date = explode("+",ConvertDateTime($res["ACTIVE_FROM"], "d.m+H:i:s"));
            $el["date"] = $date[0];
            $el["time"] = trim($date[1], ':00') . ':00';

            $el["home"] = $this->arCountries[$res["PROPERTY_HOME_VALUE"]];
            $el["home"]["goals"] = $res["PROPERTY_GOAL_HOME_VALUE"] ?? '<span class="text-secondary">0</span>';

            $el["number"] = $res["PROPERTY_NUMBER_VALUE"];

            $el["active"] = $res["ACTIVE"];

            $el["guest"] = $this->arCountries[$res["PROPERTY_GUEST_VALUE"]];
            $el["guest"]["goals"] = $res["PROPERTY_GOAL_GUEST_VALUE"] ?? '<span class="text-secondary">0</span>';

            $el["group"] = $this->arGroup[$res["PROPERTY_GROUP_VALUE"]];
            $el["write"] = $this->arUserPrognosis[$res["ID"]] ?? '';

            $this->arResult["teams"][$res["ID"]] = $el;

            if($el["active"] === "Y"){
                $this->arResult["active_count"]++;
            } else {
                $this->arResult["not_active_count"]++;
            }

        }

        $this->includeComponentTemplate();
    }

    protected function getTeamInfo(){

        $arr = [];

        $response = \Bitrix\Iblock\ElementTable::getList(
            [
                'select' => ['ID', 'NAME','PREVIEW_PICTURE'],
                'filter' => [
                    "IBLOCK_ID" => $this->countriesIb,

                ]
            ]
        );

        while($res = $response->fetch()){
            $res["img"] = CFile::GetPath($res["PREVIEW_PICTURE"]);
            $arr[$res["ID"]] = $res;
        }


        return $arr;
    }

    protected function getGroupInfo(){

        $arr = [];
        $response = \Bitrix\Iblock\ElementTable::getList(
            [
                'select' => ['ID', 'PREVIEW_TEXT'],
                'filter' => [
                    "IBLOCK_ID" => $this->groupIb,
                ]
            ]
        );

        while($res = $response->fetch()){

            $arr[$res["ID"]] = $res["PREVIEW_TEXT"];
        }

        return $arr;

    }

    protected function getUserPrognosis(){
        $this->arFilter["IBLOCK_ID"] = $this->prognosisIb;
        $this->arFilter["PROPERTY_USER_ID"] = $this->userId;

        $response = CIBlockElement::GetList(
            [],
            $this->arFilter,
            false,
            [],
            [
                "ID",
                "TIMESTAMP_X",
                "PROPERTY_ID",
            ]
        );

        while($res = $response->GetNext()){
            $this->arUserPrognosis[$res["PROPERTY_ID_VALUE"]] = $res["TIMESTAMP_X"];
        }
    }
}
