<?php

use Bitrix\Main\{Loader, UserTable};

class FootballOneMatch extends CBitrixComponent
{
    protected $matchesIb;
    protected $groupIb;
    protected $countriesIb;

    protected $matchId;

    protected $arCountries = [];
    protected $arGroup = [];

    public function __construct($component = null)
    {
        parent::__construct($component);
        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        }

        $this->matchesIb = \CIBlock::GetList([], ['CODE' => 'matches'], false)->Fetch()['ID'] ?: 2;
        $this->groupIb = \CIBlock::GetList([], ['CODE' => 'group'], false)->Fetch()['ID'] ?: 5;
        $this->countriesIb = \CIBlock::GetList([], ['CODE' => 'countries'], false)->Fetch()['ID'] ?: 3;

        $this->arCountries = $this->getTeamInfo();
        $this->arGroup = $this->getGroupInfo();

    }

    public function executeComponent()
    {

        $this->getNewMatchInfo();

        $this->includeComponentTemplate();
    }

    public function onPrepareComponentParams($arParams)
    {
        $this->matchId = $arParams["id"];
    }

    protected function getNewMatchInfo()
    {
        $this->arFilter["IBLOCK_ID"] = $this->matchesIb;
        $this->arFilter["ID"] = $this->matchId;

        $response = CIBlockElement::GetList(
            ["DATE_ACTIVE_FROM" => "ASC"],
            $this->arFilter,
            false,
            [],
            [
                "ID",
                "DATE_ACTIVE_FROM",
                "PROPERTY_home",
                "PROPERTY_home_goals",
                "PROPERTY_guest",
                "PROPERTY_guest_goals",
                "PROPERTY_group",
                "PROPERTY_stage",
                "PROPERTY_number",
            ]
        );

        $res = $response->GetNext();

        $el = [];

        $date = explode("+", ConvertDateTime($res["ACTIVE_FROM"], "d.m+H:i:s"));
        $el["date"] = $date[0];
        $el["time"] = trim($date[1], ':00') . ':00';

        $el["home"] = $this->arCountries[$res["PROPERTY_HOME_VALUE"]];
        $el["home"]["goals"] = $res["PROPERTY_HOME_GOALS_VALUE"] ?: 0;

        $el["guest"] = $this->arCountries[$res["PROPERTY_GUEST_VALUE"]];
        $el["guest"]["goals"] = $res["PROPERTY_GUEST_GOALS_VALUE"] ?: 0;

        $el["group"] = $this->arGroup[$res["PROPERTY_GROUP_VALUE"]];

        $el["number"] = $res["PROPERTY_NUMBER_VALUE"];

        $this->arResult = $el;

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
