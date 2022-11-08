<?php

use Bitrix\Main\{Loader, UserTable};

class FootballMatches extends CBitrixComponent
{
    protected $matchesIb;
    protected $groupIb;
    protected $countriesIb;

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

    }

    public function executeComponent()
    {

        $this->arFilter["IBLOCK_ID"] = $this->matchesIb;


        $response = CIBlockElement::GetList(
            ["DATE_ACTIVE_FROM" => "ASC"],
            $this->arFilter,
            false,
            ["nTopCount" => 6],
            [
                "ID",
                "DATE_ACTIVE_FROM",
                "PROPERTY_home",
                "PROPERTY_home_goals",
                "PROPERTY_guest",
                "PROPERTY_guest_goals",
                "PROPERTY_group",
                "PROPERTY_stage",
            ]
        );

        while ($res = $response->GetNext()) {
            $el = [];

            $date = explode("+",ConvertDateTime($res["ACTIVE_FROM"], "m.d+H:i:s"));
            $el["date"] = $date[0];
            $el["time"] = trim($date[1], ':00') . ':00';
            $el["home"] = $this->getTeamInfo($res["PROPERTY_HOME_VALUE"]);
            $el["home"]["goals"] = $res["PROPERTY_HOME_GOALS_VALUE"] ?: 0;

            $el["guest"] = $this->getTeamInfo($res["PROPERTY_GUEST_VALUE"]);
            $el["guest"]["goals"] = $res["PROPERTY_GUEST_GOALS_VALUE"] ?: 0;

            $el["group"] = $this->getGroupInfo($res["PROPERTY_GROUP_VALUE"]);
            $this->arResult["teams"][$res["ID"]] = $el;

        }

        $this->includeComponentTemplate();
    }

    protected function getTeamInfo($id){

        $res = \Bitrix\Iblock\ElementTable::getList(
            [
                'select' => ['NAME','PREVIEW_PICTURE'],
                'filter' => [
                    "IBLOCK_ID" => $this->countriesIb,
                    "ID" => $id
                ]
            ]
        )->fetch();
        $res["img"] = CFile::GetPath($res["PREVIEW_PICTURE"]);

        return $res;
    }

    protected function getGroupInfo($id){

        $res = \Bitrix\Iblock\ElementTable::getList(
            [
                'select' => ['PREVIEW_TEXT'],
                'filter' => [
                    "IBLOCK_ID" => $this->groupIb,
                    "ID" => $id
                ]
            ]
        )->fetch();

        return $res["PREVIEW_TEXT"];

    }
}
