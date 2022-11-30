<?php

use Bitrix\Main\{Loader};

class FootballOneMatch extends CBitrixComponent
{
    protected $resultIb;

    protected $arUsers = [];
    protected $arResults = [];

    protected $best = [];

    protected $count = 0;

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

        if ($this->arResults) $this->calcRating();

        arsort($this->best);

        $this->getBestScore();

    }

    public function executeComponent()
    {

        $this->includeComponentTemplate();
    }

    public function onPrepareComponentParams($arParams)
    {
        $this->matchId = $arParams["id"];
    }

    protected function getUsers()
    {
        $row = Bitrix\Main\UserTable::getList([
            "select" => ["ID", "NAME"],
        ]);

        while ($res = $row->fetch()) {
            $this->arUsers[$res["ID"]] = $res["NAME"];
        }
    }

    protected function getResults()
    {
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

            if ($res["PROPERTY_ALL_VALUE"] > 30) {
                $this->best[$res["PROPERTY_USER_ID_VALUE"] . '-' . $res["PROPERTY_MATCH_ID_VALUE"]] = $res["PROPERTY_ALL_VALUE"];
            }
        }

        $this->count = count($this->arResults[20]);
    }

    protected function calcRating()
    {
        $volume = [];
        $count = 0;

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

        foreach ($this->arResults as $userId => $match) {
            $count++;

            foreach ($match as $info) {

                foreach ($arrSelector as $selector) {

                    $this->arResult[$selector][$userId]["score"] += +$info["PROPERTY_" . strtoupper($selector) . "_VALUE"];
                    $this->arResult[$selector][$userId]["nick"] = $this->arUsers[$info["PROPERTY_USER_ID_VALUE"]];
                    $this->arResult[$selector][$userId]["id"] = $userId;

                    $volume[$selector][$userId] = $this->arResult[$selector][$userId]["score"];
                }
            }
        }

        foreach ($arrSelector as $selector) {
            array_multisort($volume[$selector], SORT_DESC, $this->arResult[$selector]);
        }

        $this->arResult["count"] = $this->count;
    }

    protected function getBestScore()
    {
        foreach ($this->best as $key => $item) {
            $el = [];
            $arr = explode("-", $key);
            $el['nick'] = '<a href="/p/profile/'.$arr[0].'/">'.$this->arUsers[$arr[0]].'</a>';
            $el['match'] = $arr[1];
            $el['score'] = $item;

            $this->arResult["best_score"][$key] = $el;
        }
    }
}