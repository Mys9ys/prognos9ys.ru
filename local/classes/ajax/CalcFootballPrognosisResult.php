<?php

use Bitrix\Main\Loader;

class CalcFootballPrognosisResult
{
    protected $data;

    protected $arIbs = [
        'matches' => ['code' => 'matches', 'id' => 2],
        'prognosis' => ['code' => 'prognosis', 'id' => 6],
        'result' => ['code' => 'result', 'id' => 7]
    ];

    protected $arResult;
    protected $arResults;

    protected $arMiddleResult;

    protected $arProps = [
        33 => "goals",
        34 => "result",
        35 => "diff",
        36 => "sum",
        37 => "domination",
        38 => "yellow",
        39 => "red",
        40 => "corner",
        41 => "penalty",
        42 => "all",
        43 => "match",
        44 => "user",
        49 => "otime",
        50 => "spenalty",
        51 => "number",
        53 => "event",
    ];

    protected $arSelect = [
        "ID",
        "ACTIVE",
        "DATE_ACTIVE_FROM",
        "PROPERTY_home",
        "PROPERTY_goal_home",
        "PROPERTY_guest",
        "PROPERTY_goal_guest",
        "PROPERTY_number",
        "PROPERTY_match_id",
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
        "PROPERTY_otime",
        "PROPERTY_spenalty",
        "PROPERTY_events",
    ];

    public function __construct($data)
    {

        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        }

        $this->data = $data;

       $this->getEventResult();
       $this->getPrognosisArray();

        if ($this->arMiddleResult) $this->calcResultPrognosisUser();

        if($this->arResults) $this->setManyResult();

    }

    protected function getEventResult()
    {
        $arFilter = [
            'IBLOCK_ID' => $this->arIbs['matches']['id'],
            'ID' => $this->data['matchId'],
        ];

        $res = CIBlockElement::GetList(
            [],
            $arFilter,
            false,
            [],
            $this->arSelect
        )->GetNext();

        $this->arMiddleResult['result'] = $res;
    }

    protected function getPrognosisArray()
    {
        $arFilter = [
            'IBLOCK_ID' => $this->arIbs['prognosis']['id'],
            'PROPERTY_match_id' => $this->data['matchId'],
        ];

        $response = CIBlockElement::GetList(
            [],
            $arFilter,
            false,
            [],
            $this->arSelect
        );

        while ($res = $response->GetNext()) {
            $this->arMiddleResult['prognosis'][$res['PROPERTY_USER_ID_VALUE']] = $res;
        }
    }

    protected function calcResultPrognosisUser()
    {
        $matchRes = $this->arMiddleResult['result'];
        foreach ($this->arMiddleResult['prognosis'] as $userId=>$prognosis) {
            $result = [];

            $all = 0;

            $result['user'] = $userId;
            $result['match'] = $prognosis["PROPERTY_MATCH_ID_VALUE"];
            $result['number'] = $prognosis["PROPERTY_NUMBER_VALUE"];

            // счет матча
            $arResGoals = ["home" => $prognosis["PROPERTY_GOAL_HOME_VALUE"],
                "guest" => $prognosis["PROPERTY_GOAL_GUEST_VALUE"],
            ];
            $arProgGoals = ["home" => $matchRes["PROPERTY_GOAL_HOME_VALUE"],
                "guest" => $matchRes["PROPERTY_GOAL_GUEST_VALUE"],
            ];
            $result['goals'] = $this->calcGoals($arResGoals, $arProgGoals);

            $all += $result['goals'];

            // исход матча
            $result['result'] = $this->calcConstScore($prognosis["PROPERTY_RESULT_VALUE"], $matchRes["PROPERTY_RESULT_VALUE"]);
            $all += $result['result'];

            // сумма голов
            $result['sum'] = $this->calcConstScore($prognosis["PROPERTY_SUM_VALUE"], $matchRes["PROPERTY_SUM_VALUE"]);
            $all += $result['sum'];

            // разница голов
            $result['diff'] = $this->calcConstScore($prognosis["PROPERTY_DIFF_VALUE"], $matchRes["PROPERTY_DIFF_VALUE"]);
            $all += $result['diff'];

            // % владения
            $result['domination'] = $this->calcDomination($prognosis["PROPERTY_DOMINATION_VALUE"], $matchRes["PROPERTY_DOMINATION_VALUE"]);
            $all += $result['domination'];

            // количество желтых карточек
            if ($prognosis["PROPERTY_YELLOW_VALUE"] || $prognosis["PROPERTY_YELLOW_VALUE"] !== null) {
                $result['yellow'] = $this->calcProgressScala($prognosis["PROPERTY_YELLOW_VALUE"], $matchRes["PROPERTY_YELLOW_VALUE"]);
                $all += $result['yellow'];
            } else { $result['yellow'] = 0;}

            // количество угловых
            if ($prognosis["PROPERTY_CORNER_VALUE"] || $prognosis["PROPERTY_CORNER_VALUE"] !== null) {
                $result['corner'] = $this->calcProgressScala($prognosis["PROPERTY_CORNER_VALUE"], $matchRes["PROPERTY_CORNER_VALUE"]);
                $all += $result['corner'];
            } else { $result['corner'] = 0;}

            // количество красных
            if ($prognosis["PROPERTY_RED_VALUE"] || $prognosis["PROPERTY_RED_VALUE"] !== null) {
                $result['red'] = $this->calcRedCard($prognosis["PROPERTY_RED_VALUE"], $matchRes["PROPERTY_RED_VALUE"]);
                $all += $result['red'];
            } else { $result['red'] = 0;}

            // количество пенальти
            if ($prognosis["PROPERTY_PENALTY_VALUE"] || $prognosis["PROPERTY_PENALTY_VALUE"] !== null) {
                $result['penalty'] = $this->calcRedCard($prognosis["PROPERTY_PENALTY_VALUE"], $matchRes["PROPERTY_PENALTY_VALUE"]);
                $all += $result['penalty'];
            } else { $result['penalty'] = 0;}


            // дополнительное время
            if ($prognosis["PROPERTY_OTIME_VALUE"] || $prognosis["PROPERTY_OTIME_VALUE"] !== null) {
                $result['otime'] = $this->calcPlayOff($prognosis["PROPERTY_OTIME_VALUE"], $matchRes["PROPERTY_OTIME_VALUE"]);
                $all += $result['otime'];
            } else { $result['otime'] = 0;}

            // серия пенальти
            if ($prognosis["PROPERTY_SPENALTY_VALUE"] || $prognosis["PROPERTY_SPENALTY_VALUE"] !== null) {
                $result['spenalty'] = $this->calcPlayOff($prognosis["PROPERTY_SPENALTY_VALUE"], $matchRes["PROPERTY_SPENALTY_VALUE"]);
                $all += $result['spenalty'];
            } else { $result['spenalty'] = 0;}

            $result["all"] = $all;

            $result["event"] = $prognosis["PROPERTY_EVENTS_VALUE"];

            $this->arResults[$result['user']] = $result;

        }
    }

    protected function calcGoals($arPrognos, $arRes)
    {
        if ($arPrognos["home"] === $arRes["home"] && $arPrognos["guest"] === $arRes["guest"]) {
            return 10;
        } else {
            return 0;
        }
    }

    protected function calcConstScore($prognos, $res)
    {

        if ($prognos === $res) {
            return 5;
        } else {
            return 0;
        }
    }

    protected function calcDomination($prognos, $res)
    {

        $diff = abs(+$prognos - +$res);

        if ($diff === 0) {
            return 5;
        } elseif ($diff < 6) {
            return 3;
        } elseif ($diff < 11) {
            return 1;
        } else {
            return 0;
        }

    }

    protected function calcProgressScala($prognos, $res)
    {
        $diff = abs(+$prognos - +$res);

        if ($diff === 0) {
            return 5;
        } elseif ($diff < 2) {
            return 3;
        } elseif ($diff < 3) {
            return 1;
        } else {
            return 0;
        }
    }

    protected function calcRedCard($prognos, $res)
    {

        if($prognos !== ''){
            if(+$prognos >9) return 0;
            if (+$prognos === 0 && +$res === 0) return 0.5;
            if ($prognos === $res && +$res > 0) return 5 +(($res-1)*2);
            if (+$prognos>0 && +$res > 0) return 0.5;
        }

        return 0;
    }

    protected function calcPlayOff($prognos, $res){
        if($prognos !== ''){
            if ($prognos === 'Не будет' && $res === 'Не будет') return 0.5;
            if ($prognos === 'Будет' && $res === 'Будет') return 5;
            return 0;
        }
        return 0;

    }

    protected function setManyResult()
    {
        foreach ($this->arResults as $res){
            $this->setOneResult($res);
        }
    }

    protected function setOneResult($arr){

        $arSet = [];

        $prop = [
            33 => $arr["goals"],
            34 => $arr["result"],
            35 => $arr["diff"],
            36 => $arr["sum"],
            37 => $arr["domination"],
            38 => $arr["yellow"],
            39 => $arr["red"],
            40 => $arr["corner"],
            41 => $arr["penalty"],
            42 => $arr["all"],
            43 => $arr["match"],
            44 => $arr["user"],
            49 => $arr["otime"],
            50 => $arr["spenalty"],
            51 => $arr["number"],
            53 => $arr["event"],
        ];

        $now = date(\CDatabase::DateFormatToPHP("DD.MM.YYYY HH:MI:SS"), time());

        $ib = new CIBlockElement;
        $data = [
            "NAME" => "Участник: " .$prop[44] . " Результаты прогноза на матч: " . $arr["number"],
            "IBLOCK_ID" => $this->arIbs['result']['id'],
            "DATE_ACTIVE_FROM" => $now,
            "PROPERTY_VALUES"=>$prop
        ];

        $arSet[$prop[44]] = $ib->Add($data);

        $this->setResult('ok', '');

    }

    protected function setResult($status, $mes, $info = '')
    {
        $this->arResult['status'] = $status;
        $this->arResult['mes'] = $mes;
    }

    public function result()
    {
        return $this->arResult;
    }

}