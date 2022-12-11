<?php use Bitrix\Main\Loader;

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

file_put_contents('set_debug_request.json', json_encode($_REQUEST));
//$_REQUEST = json_decode(file_get_contents('set_debug_request.json'), true);

if ($_REQUEST['type'] === 'set_result') {
    $res = new SetResultAllUsers($_REQUEST['id']);
}

class SetResultAllUsers
{

    protected $matchesIb;
    protected $countriesIb;
    protected $prognosisIb;
    protected $resultIb;

    protected $matchId;

    protected $arCountries = [];
    protected $arMatchResult = [];
    protected $arUsers = [];
    protected $arMatchPrognosis = [];
    protected $arResults = [];

    public function __construct($matchId)
    {

        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        }

        $this->matchId = $matchId;

        $this->matchesIb = \CIBlock::GetList([], ['CODE' => 'matches'], false)->Fetch()['ID'] ?: 2;
        $this->prognosisIb = \CIBlock::GetList([], ['CODE' => 'prognosis'], false)->Fetch()['ID'] ?: 6;
        $this->resultIb = \CIBlock::GetList([], ['CODE' => 'result'], false)->Fetch()['ID'] ?: 7;
        $this->countriesIb = \CIBlock::GetList([], ['CODE' => 'countries'], false)->Fetch()['ID'] ?: 3;

        $this->arCountries = $this->getTeamInfo();
        $this->arMatchResult = $this->getMatchResult();

        $this->arMatchPrognosis = $this->getMatchPrognosis();

        if ($this->arMatchPrognosis && $this->arMatchResult) $this->calcResultPrognosisUser();

        if($this->arResults) $this->setManyResult();

    }

    protected function getTeamInfo()
    {

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

    protected function getMatchResult()
    {

        $arFilter = [];
        $arFilter["IBLOCK_ID"] = $this->matchesIb;
        $arFilter["ID"] = $this->matchId;

        $response = CIBlockElement::GetList(
            [],
            $arFilter,
            false,
            [],
            [
                "ID",
                "ACTIVE",
                "DATE_ACTIVE_FROM",
                "PROPERTY_home",
                "PROPERTY_goal_home",
                "PROPERTY_guest",
                "PROPERTY_goal_guest",
                "PROPERTY_number",
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
                "PROPERTY_otime",
                "PROPERTY_spenalty",
            ]
        );

        $res = $response->GetNext();

//        dump($res);

        $el = [];

        $date = explode("+", ConvertDateTime($res["DATE_ACTIVE_FROM"], "d.m+H:i:s"));
        $el["date"] = $date[0];
        $el["time"] = trim($date[1], ':00') . ':00';

        $el["home"] = $this->arCountries[$res["PROPERTY_HOME_VALUE"]];

        $el["guest"] = $this->arCountries[$res["PROPERTY_GUEST_VALUE"]];

        $res["info"] = $el;
        return $res;

    }

    protected function getMatchPrognosis()
    {
        $arRes = [];

        $arFilter = [];
        $arFilter["IBLOCK_ID"] = $this->prognosisIb;
        $arFilter["PROPERTY_ID"] = $this->matchId;

        $response = CIBlockElement::GetList(
            [],
            $arFilter,
            false,
            [],
            [
                "ID",
                "ACTIVE",
                "DATE_ACTIVE_FROM",
                "PROPERTY_home",
                "PROPERTY_goal_home",
                "PROPERTY_guest",
                "PROPERTY_goal_guest",
                "PROPERTY_number",
                "PROPERTY_id",
                "PROPERTY_user_id",
                "PROPERTY_result",
                "PROPERTY_diff",
                "PROPERTY_corner",
                "PROPERTY_yellow",
                "PROPERTY_red",
                "PROPERTY_penalty",
                "PROPERTY_sum",
                "PROPERTY_offside",
                "PROPERTY_number",
                "PROPERTY_domination",
                "PROPERTY_otime",
                "PROPERTY_spenalty",
            ]
        );

        while ($res = $response->GetNext()) {
            $arRes[] = $res;
        }

        return $arRes;
    }

    protected function calcResultPrognosisUser()
    {
        $Res = $this->getMatchResult();
        foreach ($this->arMatchPrognosis as $prognosis) {
            $result = [];

            $all = 0;

            $result['user'] = $prognosis["PROPERTY_USER_ID_VALUE"];
            $result['match'] = $prognosis["PROPERTY_ID_VALUE"];
            $result['number'] = $prognosis["PROPERTY_NUMBER_VALUE"];

            // счет матча
            $arResGoals = ["home" => $prognosis["PROPERTY_GOAL_HOME_VALUE"],
                "guest" => $prognosis["PROPERTY_GOAL_GUEST_VALUE"],
            ];
            $arProgGoals = ["home" => $Res["PROPERTY_GOAL_HOME_VALUE"],
                "guest" => $Res["PROPERTY_GOAL_GUEST_VALUE"],
            ];
            $result['goals'] = $this->calcGoals($arResGoals, $arProgGoals);

            $all += $result['goals'];

            // исход матча
            $result['result'] = $this->calcConstScore($prognosis["PROPERTY_RESULT_VALUE"], $Res["PROPERTY_RESULT_VALUE"]);
            $all += $result['result'];

            // сумма голов
            $result['sum'] = $this->calcConstScore($prognosis["PROPERTY_SUM_VALUE"], $Res["PROPERTY_SUM_VALUE"]);
            $all += $result['sum'];

            // разница голов
            $result['diff'] = $this->calcConstScore($prognosis["PROPERTY_DIFF_VALUE"], $Res["PROPERTY_DIFF_VALUE"]);
            $all += $result['diff'];

            // % владения
            $result['domination'] = $this->calcDomination($prognosis["PROPERTY_DOMINATION_VALUE"], $Res["PROPERTY_DOMINATION_VALUE"]);
            $all += $result['domination'];

            // количество желтых карточек
            if ($prognosis["PROPERTY_YELLOW_VALUE"] || $prognosis["PROPERTY_YELLOW_VALUE"] !== null) {
                $result['yellow'] = $this->calcProgressScala($prognosis["PROPERTY_YELLOW_VALUE"], $Res["PROPERTY_YELLOW_VALUE"]);
                $all += $result['yellow'];
            } else { $result['yellow'] = 0;}

            // количество угловых
            if ($prognosis["PROPERTY_CORNER_VALUE"] || $prognosis["PROPERTY_CORNER_VALUE"] !== null) {
                $result['corner'] = $this->calcProgressScala($prognosis["PROPERTY_CORNER_VALUE"], $Res["PROPERTY_CORNER_VALUE"]);
                $all += $result['corner'];
            } else { $result['corner'] = 0;}

            // количество красных
            if ($prognosis["PROPERTY_RED_VALUE"] || $prognosis["PROPERTY_RED_VALUE"] !== null) {
                $result['red'] = $this->calcRedCard($prognosis["PROPERTY_RED_VALUE"], $Res["PROPERTY_RED_VALUE"]);
                $all += $result['red'];
            } else { $result['red'] = 0;}

            // количество пенальти
            if ($prognosis["PROPERTY_PENALTY_VALUE"] || $prognosis["PROPERTY_PENALTY_VALUE"] !== null) {
                $result['penalty'] = $this->calcRedCard($prognosis["PROPERTY_PENALTY_VALUE"], $Res["PROPERTY_PENALTY_VALUE"]);
                $all += $result['penalty'];
            } else { $result['penalty'] = 0;}


            // дополнительное время
            if ($prognosis["PROPERTY_OTIME_VALUE"] || $prognosis["PROPERTY_OTIME_VALUE"] !== null) {
                $result['otime'] = $this->calcRedCard($prognosis["PROPERTY_OTIME_VALUE"], $Res["PROPERTY_OTIME_VALUE"]);
                $all += $result['otime'];
            } else { $result['otime'] = 0;}

            // серия пенальти
            if ($prognosis["PROPERTY_SPENALTY_VALUE"] || $prognosis["PROPERTY_SPENALTY_VALUE"] !== null) {
                $result['spenalty'] = $this->calcRedCard($prognosis["PROPERTY_SPENALTY_VALUE"], $Res["PROPERTY_SPENALTY_VALUE"]);
                $all += $result['spenalty'];
            } else { $result['spenalty'] = 0;}

            $result["all"] = $all;

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
        ];

        $now = date(\CDatabase::DateFormatToPHP("DD.MM.YYYY HH:MI:SS"), time());

        $ib = new CIBlockElement;
        $data = [
            "NAME" => "Участник: " .$prop[44] . " Результаты прогноза на матч: " . $arr["number"],
            "IBLOCK_ID" => $this->resultIb ,
            'DATE_ACTIVE_FROM' => $now,
            "PROPERTY_VALUES"=>$prop
        ];

        $arSet[$prop[44]] = $ib->Add($data);

    }
}
