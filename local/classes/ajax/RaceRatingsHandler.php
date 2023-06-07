<?php

use Bitrix\Main\Loader;

class RaceRatingsHandler
{

    protected $data;

    protected $arIbs = [
        'resultf1' => ['code' => 'resultf1', 'id' => 14]
    ];

    protected $arUsers = [];
    protected $arResults = [];
    protected $arResult = [];

    protected $arMiddleResults = [];

    protected $arrSelector = [
        "all",
        "qual_sum",
        "sprint_sum",
        "race_sum",
        "best_lap",
    ];

    protected $arUserScore = [];

    protected $count = 0;

    protected $eventId;

    public function __construct($data)
    {
        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        }

        $this->data = $data;

        if ($this->data['events']) {

            $this->getUsers();

            $this->getResults();

            $this->arrSum();

            $this->arrProcessing();

            $this->setResult('ok', 'mes', $info = $this->arResults);

        }

    }

    protected function getResults(){
        $arFilter = [
            'IBLOCK_ID' => $this->arIbs['resultf1']['id'],
            'PROPERTY_events' => $this->data['events']
        ];

        $arSelect = [
            "ID",
            "DATE_ACTIVE_FROM",
            "PROPERTY_number",
            "PROPERTY_user_id",
        ];

        foreach ($this->arrSelector as $select){
            $arSelect[] = 'PROPERTY_'.$select;
        }

        $response = CIBlockElement::GetList(
            [],
            $arFilter,
            false,
            [],
            $arSelect
        );

        while ($res = $response->GetNext()) {
            $this->arUserScore[$res["PROPERTY_USER_ID_VALUE"]] += 1;

            foreach ($this->arrSelector as $selector) {
                $number = $res["PROPERTY_NUMBER_VALUE"];

                $this->arMiddleResults[$selector][$number][$res["PROPERTY_USER_ID_VALUE"]] = (float)$res["PROPERTY_" . strtoupper($selector) . "_VALUE"] ?? 0;

                if($selector === 'best_lap') {
                    $this->arMiddleResults[$selector][$number][$res["PROPERTY_USER_ID_VALUE"]] = array_shift(json_decode($res["~PROPERTY_" . strtoupper($selector) . "_VALUE"], true));
                }

                if(!$this->arMiddleResults[$selector][$number-1][$res["PROPERTY_USER_ID_VALUE"]] && $number!=='1')
                $this->arMiddleResults[$selector][$number-1][$res["PROPERTY_USER_ID_VALUE"]] = 0;

                if ($res["PROPERTY_ALL_VALUE"] > 30) {
                    $this->arResults['best'][$number][$res["PROPERTY_USER_ID_VALUE"]] = $res["PROPERTY_ALL_VALUE"];
                }
            }
        }

        foreach ($this->arMiddleResults as $selector=>$arr){
            ksort($arr);

            $this->arMiddleResults[$selector] = $arr;
        }

    }

    protected function arrSum()
    {

        foreach ($this->arMiddleResults as $selector => $arCategory) {
            foreach ($arCategory as $number => $arScore) {
                foreach ($this->arUserScore as $userId => $count) {

                    if ($arScore[$userId]) {
                        $this->arResults[$selector][$number][$userId] = $arScore[$userId];
                    } else {
                        $this->arResults[$selector][$number][$userId] = 0;
                    }

                    if ($this->arResults[$selector][$number - 1][$userId])
                        $this->arResults[$selector][$number][$userId] += $this->arResults[$selector][$number - 1][$userId];

                }
            }
        }
    }

    protected function arrProcessing()
    {

        function MysSortFunc($a, $b)
        {
            if ($a == $b) {
                return 0;
            }
            return ($a > $b) ? -1 : 1;
        }

        foreach ($this->arResults as $selector => $match) {

            foreach ($match as $id => $scores) {

                uasort($scores, 'MysSortFunc');

                $place = 1;
                $prev = '';
                $count = 1;
                $el = [];
                $arSorted = [];

                foreach ($scores as $uid => $score) {
                    if ($score !== $prev) {
                        $place = $count;
                    }

                    $el['place'] = $place;
                    $el['user'] = $this->arUsers[$uid];
                    $el['score'] = $score;

                    if ($id > 1 && $selector != 'best') {
                        $el['diff'] = $this->arResults[$selector][$id - 1][$uid]['place'] - $place;
                        if ($place === abs($el['diff'])) $el['diff'] = 0;
                    } else {
                        $el['diff'] = 0;
                    }
                    $arSorted[$uid] = $el;

                    $prev = $score;
                    $count++;
                }
                $this->arResults[$selector][$id] = $arSorted;
            }

        }
        foreach ($this->arResults as $selector => $match) {

            foreach ($match as $id => $scores) {

                array_multisort(array_column($scores, 'score'), SORT_DESC, $scores);

                $this->arResults[$selector][$id] = $scores;
            }
        }

        file_put_contents('../../_logs/race_ratings.json', json_encode($this->arResults));
    }

    protected function getUsers()
    {
        $row = Bitrix\Main\UserTable::getList([
            "select" => ["ID", "NAME", "PERSONAL_PHOTO"],
        ]);

        while ($res = $row->fetch()) {
            $this->arUsers[$res["ID"]]["id"] = $res["ID"];
            $this->arUsers[$res["ID"]]["name"] = $res["NAME"];
            $this->arUsers[$res["ID"]]["img"] = $res["PERSONAL_PHOTO"] ? CFile::GetPath($res["PERSONAL_PHOTO"]) : null;
        }
    }

    protected function setResult($status, $mes, $info = '')
    {
        $this->arResult['status'] = $status;
        $this->arResult['mes'] = $mes;
        $this->arResult['ratings'] = $info;
    }

    public function result()
    {
        return $this->arResult;
    }
}