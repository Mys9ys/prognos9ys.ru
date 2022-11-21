<?php

use Bitrix\Main\{Loader, UserTable};

class FootballOneMatch extends CBitrixComponent
{
    protected $resultIb;

    protected $arUsers = [];
    protected $arResults = [];

    public function __construct($component = null)
    {
        parent::__construct($component);
        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        }

        $this->resultIb = \CIBlock::GetList([], ['CODE' => 'result'], false)->Fetch()['ID'] ?: 7;

        $this->getUsers();

        $this->getResults();

        if($this->arResults) $this->calcRating();

    }

    public function executeComponent()
    {

        $this->includeComponentTemplate();
    }

    public function onPrepareComponentParams($arParams)
    {
        $this->matchId = $arParams["id"];
    }

    protected function getUsers(){
        $row = Bitrix\Main\UserTable::getList([
            "select" => ["ID","NAME"],
        ]);

        while ($res = $row->fetch()){
            $this->arUsers[$res["ID"]] = $res["NAME"];
        }
    }

    protected function getResults(){
        $arFilter["IBLOCK_ID"] = $this->resultIb;

        $response = CIBlockElement::GetList(
            [],
            $arFilter,
            false,
            [],
            [
                "ID",
                "DATE_ACTIVE_FROM",
                "PROPERTY_all",
                "PROPERTY_score",
                "PROPERTY_number",
                "PROPERTY_match_id",
                "PROPERTY_user_id",
                "PROPERTY_result",
                "PROPERTY_diff",
                "PROPERTY_corner",
                "PROPERTY_yellow",
                "PROPERTY_red",
                "PROPERTY_penalty",
                "PROPERTY_sum",
                "PROPERTY_domination",
            ]
        );

        while ($res = $response->GetNext()) {
            $this->arResults[$res["PROPERTY_USER_ID_VALUE"]][$res["PROPERTY_MATCH_ID_VALUE"]] = $res;
        }
    }

    protected function calcRating(){
        $volume = [];

        $arrSelector = [
                "all",
                "score",
                "result",
                "sum",
                "diff",
                "domination",
                "yellow",
                "red",
                "corner",
                "penalty",
        ];

        foreach ($this->arResults as $userId=>$match){

            foreach ($match as $info){

                foreach ($arrSelector as $selector){

                    $this->arResult[$selector][$userId]["score"] += +$info["PROPERTY_".strtoupper($selector)."_VALUE"];
                    $this->arResult[$selector][$userId]["nick"] = $this->arUsers[$info["PROPERTY_USER_ID_VALUE"]];
                    $this->arResult[$selector][$userId]["id"] = $userId;

                    $volume[$selector][$userId] = $this->arResult[$selector][$userId]["score"];
                }
            }
        }

        foreach ($arrSelector as $selector){
            array_multisort($volume[$selector], SORT_DESC, $this->arResult[$selector]);
        }

    }

}