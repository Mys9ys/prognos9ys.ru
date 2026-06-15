<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header("Access-Control-Allow-Headers: X-Requested-With");

header('Content-Type: text/html; charset=utf-8');

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Loader;

$_REQUEST['date'] = date(\CDatabase::DateFormatToPHP("DD.MM.YYYY HH:MI:SS"), time());

file_put_contents('../../_logs/ratings.log', json_encode($_REQUEST) . PHP_EOL, FILE_APPEND);

if($_REQUEST){
    $res = new CreateFootballRatings($_REQUEST);

    echo json_encode($res->getResult());
}


class CreateFootballRatings{

    protected $resultIb;


    protected $arUsers = [];
    protected $arResults = [];
    protected $arMiddleResults = [];

    protected $arrSelector = [
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

    protected $arUserScore = [];

    protected $count = 0;

    protected $eventId;

    protected $ArMatchIdForNumber = [];

    public function __construct($data)
    {
        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        }

        $this->resultIb = \CIBlock::GetList([], ['CODE' => 'result'], false)->Fetch()['ID'] ?: 7;

        $this->eventId = $data["event"];

        if($this->eventId){
            $getArrMIFN = new GetArrMatchIdForNumber($this->eventId);
            $this->ArMatchIdForNumber = $getArrMIFN->getResult();

            $this->getUsers();

            $this->getResults();

            $this->arrSum();

            $this->arrProcessing();

        }
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

    protected function getResults()
    {
        $arFilter["IBLOCK_ID"] = $this->resultIb;

        if($this->eventId == 34) {
            $arFilter["!=PROPERTY_events"] = 6664;
        } else {
            $arFilter["PROPERTY_events"] = $this->eventId;
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
            $this->arUserScore[$res["PROPERTY_USER_ID_VALUE"]] += 1;

            foreach ($this->arrSelector as $selector) {
                $number = $this->ArMatchIdForNumber[$res["PROPERTY_MATCH_ID_VALUE"]];

                $this->arMiddleResults[$selector][$number][$res["PROPERTY_USER_ID_VALUE"]] = (float)$res["PROPERTY_" . strtoupper($selector) . "_VALUE"] ?? 0;

                if ($res["PROPERTY_ALL_VALUE"] > 30) {
                    $this->arResults['best'][$number][$res["PROPERTY_USER_ID_VALUE"]]=$res["PROPERTY_ALL_VALUE"];
                }
            }

        }

    }

    protected function arrSum(){
        foreach ($this->arMiddleResults as $selector=>$arCategory){
            foreach ($arCategory as $number=>$arScore){
                foreach ($this->arUserScore as $userId=>$count){
                    if($arScore[$userId]){
                        $this->arResults[$selector][$number][$userId] = $arScore[$userId];
                    }

                    if($this->arResults[$selector][$number-1][$userId])
                        $this->arResults[$selector][$number][$userId] += $this->arResults[$selector][$number-1][$userId];

                }
            }
        }
    }

    protected function arrProcessing(){

        function MysSortFunc($a, $b) {
            if ($a == $b) {
                return 0;
            }
            return ($a > $b) ? -1 : 1;
        }

        foreach ($this->arResults as $selector=>$match){

            foreach ($match as $id=>$scores){

                uasort($scores, 'MysSortFunc');

                $place = 1;
                $prev = '';
                $count = 1;
                $el = [];
                $arSorted = [];

                foreach ($scores as $uid=>$score){
                    if($score !== $prev) {
                        $place = $count;
                    }

                    $el['place'] = $place;
                    $el['user'] =  $this->arUsers[$uid];
                    $el['score'] = $score;

                    if($id>1 && $selector != 'best') {
                        $el['diff'] = $this->arResults[$selector][$id-1][$uid]['place'] - $place;
                        if($place === abs($el['diff'])) $el['diff'] = 0;
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
        foreach ($this->arResults as $selector=>$match){

            foreach ($match as $id=>$scores) {

                array_multisort(array_column($scores, 'score'), SORT_DESC, $scores);

                $this->arResults[$selector][$id] = $scores;
            }
        }


        file_put_contents('../../_logs/ratings.json', json_encode($this->arResults));
    }

    public function getResult(){
        return ['status'=>'ok', 'ratings'=>$this->arResults];
    }
}