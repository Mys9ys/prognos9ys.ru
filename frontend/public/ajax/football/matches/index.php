<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header("Access-Control-Allow-Headers: X-Requested-With");

header('Content-Type: text/html; charset=utf-8');

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Loader;

$_REQUEST['date'] = date(\CDatabase::DateFormatToPHP("DD.MM.YYYY HH:MI:SS"), time());

file_put_contents('../../_logs/request.log', json_encode($_REQUEST) . PHP_EOL, FILE_APPEND);

if($_REQUEST){
    $res = new FootballHandlerClass($_REQUEST);

    echo json_encode($res->result());
}


class FootballHandlerClass
{

    protected $eventsIb;
    protected $matchesIb;
    protected $groupIb;
    protected $prognIb;
    protected $resultIb;

    protected $userId;
    protected $eventId;

    protected $arTeams = [];

    protected $arError = [];

    protected $arNumbertoMatchId = [];

    protected $arUserPrognosis = [];
    protected $arUserResults = [];

    protected $arResult = [
        'status' => 'ok'
    ];

    public function __construct($data)
    {
        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        }

        $this->eventsIb = \CIBlock::GetList([], ['CODE' => 'events'], false)->Fetch()['ID'] ?: 1;
        $this->matchesIb = \CIBlock::GetList([], ['CODE' => 'matches'], false)->Fetch()['ID'] ?: 2; // установочные данные матча
        $this->groupIb = \CIBlock::GetList([], ['CODE' => 'group'], false)->Fetch()['ID'] ?: 5;

        $this->prognIb = \CIBlock::GetList([], ['CODE' => 'prognosis'], false)->Fetch()['ID'] ?: 6; //прогнозы
        $this->resultIb = \CIBlock::GetList([], ['CODE' => 'result'], false)->Fetch()['ID'] ?: 7; //результаты футбол

        if ($data['eventId']) $this->eventId = $data['eventId'];

        if ($data['userToken']) {
            $userRes = new GetUserIdForToken($data['userToken']);
            $this->userId = $userRes->getId();
        }

        $this->getUserPrognos();
        $this->getUserResult();

        $team = new GetFootballTeams();
        $this->arTeams = $team->result();

        $this->getMatchOfData();

        $this->reverseArrayOldMatches();

    }

    protected function getMatchOfData()
    {
        $arFilter = [
            'IBLOCK_ID' => $this->matchesIb,
            'PROPERTY_EVENTS' => $this->eventId
        ];

        $response = CIBlockElement::GetList(
            ["DATE_ACTIVE_FROM" => "ASC", "created" => "ASC"],
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
                "PROPERTY_group",
                "PROPERTY_stage",
                "PROPERTY_number",
                "PROPERTY_events",
            ]
        );

        while ($res = $response->GetNext()) {
            $this->arNumberToMatchId[$res["PROPERTY_NUMBER_VALUE"]] = $res['ID'];
            $el = [];

            $date = explode("+", ConvertDateTime($res["ACTIVE_FROM"], "DD.MM+HH:Mi"));

            $el["date"] = $date[0];
            $el["time"] = $date[1];

            $el["active"] = $res["ACTIVE"];
            $el["number"] = $res["PROPERTY_NUMBER_VALUE"];
            $el["event"] = $this->eventId;

            $el["teams"]["home"] = $this->getTeamData($this->arTeams[$res["PROPERTY_HOME_VALUE"]], $res["PROPERTY_GOAL_HOME_VALUE"]);
            $el["teams"]["guest"] = $this->getTeamData($this->arTeams[$res["PROPERTY_GUEST_VALUE"]], $res["PROPERTY_GOAL_GUEST_VALUE"]);

//            $el["write"] = $this->arUserPrognosis[$res["ID"]] ?? '';
            if ($this->eventId == 34) {
                $this->arNumbertoMatchId[$res["ID"]] = $el["number"];
                $this->getUserPrognosisOld($res["ID"]);
                $this->getUserResultOld($res["ID"]);
            }

            $el["send_info"]["send_time"] = $this->arUserPrognosis[$el["number"]] ?? '';
            $el["send_info"]["score_result"] = $this->arUserResults[$el["number"]] ?? '';

            // блок разделения матчей на 4 категории по дате
            $now = date(\CDatabase::DateFormatToPHP("DD.MM.YYYY"), time());
            $now = date_create($now);

            $dateMatch = date_create(explode(' ', $res["ACTIVE_FROM"])[0]);

            $interval = date_diff($dateMatch, $now);
            $intervalDay = $interval->format('%R%a');

            $el["ratio"] = $this->setRatio($res['ID']);

            if ($intervalDay > 0 && $intervalDay < 2) {
                $this->arResult['res']['recent']['matches'][$el["date"]][$el["number"]] = $el;
                $this->arResult['res']['recent']['count'] += 1;
                $this->arResult['res']['recent']['title'] = 'Недавние';
                $this->arResult['res']['recent']['visible'] = true;
                continue;
            }

            if ($intervalDay > 1) {
                $this->arResult['res']['past']['matches'][$el["date"]][$el["number"]] = $el;
                $this->arResult['res']['past']['count'] += 1;
                $this->arResult['res']['past']['title'] = 'Прошедшие';
                $this->arResult['res']['past']['visible'] = false;
                continue;
            }

            if ($intervalDay < 1 && $intervalDay > -2) {
                $this->arResult['res']['nearest']['matches'][$el["date"]][$el["number"]] = $el;
                $this->arResult['res']['nearest']['count'] += 1;
                $this->arResult['res']['nearest']['title'] = 'Ближайшие';
                $this->arResult['res']['nearest']['visible'] = true;
                continue;
            }
            if ($intervalDay < 0) {
                $this->arResult['res']['future']['matches'][$el["date"]][$el["number"]] = $el;
                $this->arResult['res']['future']['count'] += 1;
                $this->arResult['res']['future']['title'] = 'Будущие';
                $this->arResult['res']['future']['visible'] = false;
                continue;
            }

        }

    }

    protected function fillSectionArray($arr,$section, $title, $visible){

    }

    protected function reverseArrayOldMatches()
    {
        if(count($this->arResult['res']['recent']['matches']))
            $this->arResult['res']['recent']['matches'] = array_reverse($this->arResult['res']['recent']['matches'], true);
        if(count($this->arResult['res']['past']['matches']))
            $this->arResult['res']['past']['matches'] = array_reverse($this->arResult['res']['past']['matches'], true);
    }

    protected function getUserPrognos()
    {

        $arFilter = [
            'IBLOCK_ID' => $this->prognIb,
            'PROPERTY_EVENTS' => $this->eventId,
            'PROPERTY_USER_ID' => $this->userId
        ];

        $response = CIBlockElement::GetList(
            [],
            $arFilter,
            false,
            [],
            [
                "PROPERTY_number",
                "DATE_ACTIVE_FROM",
            ]
        );

        while ($res = $response->GetNext()) {
            $this->arUserPrognosis[$res["PROPERTY_NUMBER_VALUE"]] = ConvertDateTime($res["DATE_ACTIVE_FROM"], "DD.MM HH:Mi");
        }

    }

    protected function getUserPrognosisOld($matchId)
    {
        $arFilter = [
            'IBLOCK_ID' => $this->prognIb,
            'PROPERTY_MATCH_ID' => $matchId,
            'PROPERTY_USER_ID' => $this->userId
        ];

        $res = CIBlockElement::GetList(
            [],
            $arFilter,
            false,
            [],
            [
                "PROPERTY_number",
                "PROPERTY_match_id",
                "DATE_ACTIVE_FROM",
            ]
        )->GetNext();

        $this->arUserPrognosis[$this->arNumbertoMatchId[$res['PROPERTY_MATCH_ID_VALUE']]] = ConvertDateTime($res["DATE_ACTIVE_FROM"], "DD.MM HH:Mi");

    }

    protected function getUserResult()
    {
        $arFilter = [
            'IBLOCK_ID' => $this->resultIb,
            'PROPERTY_EVENTS' => $this->eventId,
            'PROPERTY_USER_ID' => $this->userId
        ];

        $response = CIBlockElement::GetList(
            [],
            $arFilter,
            false,
            [],
            [
                "PROPERTY_all",
                "PROPERTY_number",

            ]
        );

        while ($res = $response->GetNext()) {
            $this->arUserResults[$res['PROPERTY_NUMBER_VALUE']] = $res["PROPERTY_ALL_VALUE"];
        }
    }

    protected function getUserResultOld($matchId)
    {
        $arFilter = [
            'IBLOCK_ID' => $this->resultIb,
            'PROPERTY_MATCH_ID' => $matchId,
            'PROPERTY_USER_ID' => $this->userId
        ];

        $res = CIBlockElement::GetList(
            [],
            $arFilter,
            false,
            [],
            [
                "ID",
                "PROPERTY_all",
                "PROPERTY_match_id",
            ]
        )->GetNext();

        $this->arUserResults[$this->arNumbertoMatchId[$res['PROPERTY_MATCH_ID_VALUE']]] = $res["PROPERTY_ALL_VALUE"];

    }

    protected function getTeamData($data, $goals): array
    {
        return [
            'flag' => $data['flag'],
            'name' => $data['NAME'],
            'goals' => $goals ?? 0
        ];
    }

    protected function setRatio($matchId){

        $arFilter = [
            'IBLOCK_ID' => $this->prognIb,
            'PROPERTY_MATCH_ID' => $matchId,
        ];

        $arRatio = [
            'plus' => 0,
            'equal' => 0,
            'minus' => 0,
            'count' => 0
        ];

        $response = CIBlockElement::GetList(
            [],
            $arFilter,
            false,
            [],
            [
                "PROPERTY_diff",
            ]
        );

        while($res = $response->GetNext()){

            if($res['PROPERTY_DIFF_VALUE'] > 0)  $arRatio['plus'] +=1;
            if($res['PROPERTY_DIFF_VALUE'] === 0)  $arRatio['equal'] +=1;
            if($res['PROPERTY_DIFF_VALUE'] < 0)  $arRatio['minus'] +=1;

            $arRatio['count'] += 1;

        }

        $arRatioScore = [
            0 => ['name' => 'п1', 'count' => number_format (($arRatio['count']+1) / ($arRatio['plus']+1), 2)],
            1 => ['name' => 'н', 'count' => number_format (($arRatio['count']+1) / ($arRatio['equal']+1), 2)],
            2 => ['name' => 'п2', 'count' => number_format (($arRatio['count']+1) / ($arRatio['minus']+1), 2)],
            3 => ['name' => 'Σ', 'count' => $arRatio['count']]
        ];

        return $arRatioScore;

    }

    public function result()
    {
        file_put_contents('../../_logs/matches_l.log', $this->userId . PHP_EOL , FILE_APPEND);
        return $this->arResult;
    }
}