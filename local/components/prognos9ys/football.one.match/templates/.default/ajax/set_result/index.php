<?php use Bitrix\Main\Loader;

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

file_put_contents('debug_request.json',json_encode($_REQUEST));

if ($_REQUEST['type'] === 'match') {

    $res = new SetMatchResult($_REQUEST);

//    if($res->getResult()){
//        $request = ["status" => "ok", "mes" => "Прогноз принят"];
//    } else {
//        $request = ["status" => "err", "mes" => "Что то пошло не так"];
//    }
//
//    echo json_encode($request);
}

class SetMatchResult {

    protected $matchesIb;
    protected $data = [];
    protected $result;

    protected $prop;
    protected $now;

    public function __construct($arr)
    {

        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        }

        $this->matchesIb = \CIBlock::GetList([], ['CODE' => 'matches'], false)->Fetch()['ID'] ?: 2;

        $this->data = $arr;

        $this->now = date(\CDatabase::DateFormatToPHP("DD.MM.YYYY HH:MI:SS"), time());
        $this->prop = [
            7 => $this->data["m_goal_home"],
            8 => $this->data["m_goal_guest"],
            9 => $this->data["m_result"],
            10 => $this->data["m_domination"],
            11 => $this->data["m_corner"],
            12 => $this->data["m_yellow"],
            13 => $this->data["m_red"],
            14 => $this->data["m_penalty"],
            25 => $this->data["m_diff"],
            26 => $this->data["m_sum"],

            24 => $this->data["m_number"],
            2 => $this->data["m_events"],
            3 => $this->data["m_team_home"],
            4 => $this->data["m_team_guest"],
            5 => $this->data["m_group"],
            6 => $this->data["m_stage"],
            47 => $this->data["m_otime"],
            48 => $this->data["m_spenalty"],
        ];

        $ib = new CIBlockElement;
        $data = [
            "IBLOCK_ID" => $this->matchesIb,
            "PROPERTY_VALUES"=> $this->prop,
        ];

        $this->result = $ib->Update($this->data["m_id"], $data);

    }
}