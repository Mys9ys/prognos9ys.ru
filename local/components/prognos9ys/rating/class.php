<?php

use Bitrix\Main\{Loader};

class EventRatings extends CBitrixComponent
{
    protected $resultIb;
    protected $eventsIb;

    protected $arUsers = [];
    protected $arResults = [];
    protected $arEvents = [];
    protected $arOneEventResult = [];

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
        $this->eventsIb = \CIBlock::GetList([], ['CODE' => 'events'], false)->Fetch()['ID'] ?: 1;

        $this->getUsers();

        $this->getEventsInfo();


        if($this->arEvents){
            foreach ($this->arEvents as $evId=>$item){
                $this->arOneEventResult = [];

                $this->getResults($evId);

                if ($this->arResults) $this->calcRating();

                arsort($this->best);

                $this->getBestScore();

                $this->fillAllUsers();

                $this->sortAllChange();

                $this->sortForNumber();

                $this->arResult[$evId] = $this->arOneEventResult;

            }

            $this->arResult["events"] = $this->arEvents;

        }

//        $this->getResults();
//
//        if ($this->arResults) $this->calcRating();
//
//        arsort($this->best);
//
//        $this->getBestScore();
//
//        $this->fillAllUsers();
//
//        $this->sortAllChange();
//
//        $this->sortForNumber();

    }

    public function executeComponent()
    {

        $this->includeComponentTemplate();
    }

    protected function getEventsInfo(){
        $arFilter["IBLOCK_ID"] = $this->eventsIb;
        $arFilter["PROPERTY_e_type"] = 6836;

        $response = CIBlockElement::GetList(
            ["ID" => "DESC"],
            $arFilter,
            false,
            [],
            [
                "ID",
                "DATE_ACTIVE_FROM",
                "NAME",
                "PREVIEW_TEXT",
                "PREVIEW_PICTURE",
            ]
        );

        while ($res = $response->GetNext()) {
            $res['img'] = CFile::GetPath($res["PREVIEW_PICTURE"]);
            $this->arEvents[$res["ID"]] = $res;
        }
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

    protected function getResults($evId)
    {
        $arFilter["IBLOCK_ID"] = $this->resultIb;


        if($evId === 34) {
            $arFilter["!=PROPERTY_events"] = 6664;
        } else {
            $arFilter["PROPERTY_events"] = $evId;
        }

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
                "PROPERTY_otime",
                "PROPERTY_spenalty",
            ]
        );

        while ($res = $response->GetNext()) {
            $this->arResults[$res["PROPERTY_USER_ID_VALUE"]][$res["PROPERTY_MATCH_ID_VALUE"]] = $res;

            if ($res["PROPERTY_ALL_VALUE"] > 30) {
                if(!$res["PROPERTY_NUMBER_VALUE"]) {
                    $res["PROPERTY_NUMBER_VALUE"] = +$res["PROPERTY_MATCH_ID_VALUE"];
                } else {
                    $res["PROPERTY_NUMBER_VALUE"] += 42;
                }
                $this->best[$res["PROPERTY_USER_ID_VALUE"] . '-' . $res["PROPERTY_NUMBER_VALUE"]] = $res["PROPERTY_ALL_VALUE"];
            }

            $this->arOneEventResult["users"][$res["PROPERTY_USER_ID_VALUE"]] = [];
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
            "otime",
            "spenalty",
        ];

        foreach ($this->arResults as $userId => $match) {
            $count++;

            foreach ($match as $info) {

                foreach ($arrSelector as $selector) {

                    $this->arOneEventResult[$selector][$userId]["score"] += +$info["PROPERTY_" . strtoupper($selector) . "_VALUE"];
                    $this->arOneEventResult[$selector][$userId]["nick"] =
                        '<a class="r_profile_link" href="/p/profile/'.$info["PROPERTY_USER_ID_VALUE"].'/">'
                        . $this->arUsers[$info["PROPERTY_USER_ID_VALUE"]]
                        .' <i class="bi bi-box-arrow-up-right"></i></a>';
                    $this->arOneEventResult[$selector][$userId]["id"] = $userId;


                    if($selector === "all"){
                        $number = $info['PROPERTY_NUMBER_VALUE'] ?? +$info['PROPERTY_MATCH_ID_VALUE'] - 42;

                        $this->arOneEventResult["all_change"][$number][$userId]["score"] = $this->arOneEventResult["all"][$userId]["score"];
                        $this->arOneEventResult["all_change"][$number][$userId]["nick"] = $this->arUsers[$info["PROPERTY_USER_ID_VALUE"]];
                        $this->arOneEventResult["all_change"][$number][$userId]["id"] = $this->arOneEventResult["all"][$userId]["id"];

//                        if($number === 6) {
//                            dump($this->arOneEventResult["all_change"][$number][$userId]);
//
//                        }
                    }

                    $volume[$selector][$userId] = $this->arOneEventResult[$selector][$userId]["score"];
                }

            }
        }

        foreach ($arrSelector as $selector) {
            array_multisort($volume[$selector], SORT_DESC, $this->arOneEventResult[$selector]);
        }

        $this->arOneEventResult["count"] = $this->count;

    }

    protected function getBestScore()
    {
        foreach ($this->best as $key => $item) {
            $el = [];
            $arr = explode("-", $key);
            $el['nick'] = '<a class="r_profile_link" href="/p/profile/'.$arr[0].'/">'.$this->arUsers[$arr[0]]
                .' <i class="bi bi-box-arrow-up-right"></i></a>';
            $el['match'] = $arr[1];
            $el['score'] = $item;

            $this->arOneEventResult["best_score"][$key] = $el;
        }
    }

    protected function fillAllUsers(){
        ksort($this->arOneEventResult["all_change"]);
        $arrScore = [];
        foreach ($this->arOneEventResult["all_change"] as $number=>$users){

            foreach ($users as $user){
                $arrScore[$user["id"]] = $user;
            }

//            if($number === 6){
//                dump($arrScore);
//                dump($number);
//                dump($users);
//                die();
//            }

            $this->arOneEventResult["all_number"][$number] = $arrScore;
        }

    }

    protected function sortAllChange(){

        foreach ($this->arOneEventResult["all_number"] as $number=>$users){
            $arSort = $users;
            array_multisort(array_column($arSort, 'score'), SORT_DESC, $arSort);
//            dump($arSort);
            $place = 1;
            $count = 1;
            $score = 0;
            foreach ($arSort as $user){

                if($user["score"] < $score) {
                    $place = $count;
                }

                $oldPlace = $this->arOneEventResult["all_number"][$number-1][$user["id"]]["place"];

//                if($number === 7){
//                    dump($oldPlace);
//                    dump($user);
//                }

                if($oldPlace && $count !== 1){
                    $user["diff"] = $oldPlace - $place;
                }

                if(!$user["diff"]) $user["diff"] = 0;

                $score = $user["score"];
                $user["place"] = $place;

                $this->arOneEventResult["all_result"][$number][$count] = $user;
                $this->arOneEventResult["all_number"][$number][$user["id"]]["place"] = $user["place"];
                $count++;
            }
        }
    }

    protected function sortForNumber(){
        foreach ($this->arOneEventResult["all_number"] as $number=>$users){
            array_multisort(array_column($users, 'score'), SORT_DESC, $users);

            $this->arOneEventResult["all_number"][$number] = $users;
        }
    }
}