<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header("Access-Control-Allow-Headers: X-Requested-With");

header('Content-Type: text/html; charset=utf-8');

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\{Loader, UserTable};

$res = new KVNajax();

echo json_encode($res->getArResult());

class KVNajax {

    protected $teamIb;
    protected $gameIb;
    protected $eventIb;
    protected $prognosisIb;

    protected $arTeams = [];
    protected $arResult = [];

    protected $userId;

    protected $actEvent = 6700;

    public function __construct()
    {

        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        }

        $this->teamIb = \CIBlock::GetList([], ['CODE' => 'kvnteams'], false)->Fetch()['ID'] ?: 10;
        $this->gameIb = \CIBlock::GetList([], ['CODE' => 'kvngame'], false)->Fetch()['ID'] ?: 17;
        $this->eventIb = \CIBlock::GetList([], ['CODE' => 'events'], false)->Fetch()['ID'] ?: 1;

//        $this->userId = CUser::GetID();
        $this->getUserInfo();
        $this->getTeamInfo();

        $this->executeComponent();



    }

    protected function executeComponent()
    {

        $arFilter["IBLOCK_ID"] = $this->gameIb;

        $response = CIBlockElement::GetList(
            ["DATE_ACTIVE_FROM" => "ASC"],
            $arFilter,
            false,
            [
//                "nTopCount" => 6
            ],
            [
                "ID",
                "ACTIVE",
                "NAME",
                "DATE_ACTIVE_FROM",
//                "PROPERTY_teams",
                "PROPERTY_stage1",
                "PROPERTY_stage2",
                "PROPERTY_stage3",
                "PROPERTY_result",
                "PROPERTY_event",
                "PROPERTY_number",
            ]
        );

        while ($res = $response->GetNext()) {
            $el = [];

//            var_dump($res);

            $el["ID"] = $res["ID"];

            $date = explode("+", ConvertDateTime($res["ACTIVE_FROM"], "d.m+H:i:s"));

            $el["title"]["date"] = $date[0];
            $el["title"]["time"] = substr($date[1], 0,-3);

            $el["title"]["number"] = $res["PROPERTY_NUMBER_VALUE"];

            $el["title"]["name"] = $res["NAME"];

            $el["active"] = $res["ACTIVE"];

            $el["write"] = $this->arUserPrognosis[$res["ID"]] ?? '';

//            var_dump($res["PROPERTY_TEAMS_VALUE"]);

//            $this->arResult["teams"][$res["ID"]] = $el;

            // выгрузка множественного свойства через костыль
            $elem[$res["ID"]] = [];
            CIBlockElement::GetPropertyValuesArray($elem, $arFilter["IBLOCK_ID"], $arFilter, ["CODE" => "teams"]);

            $arElem = $elem[$res["ID"]]["teams"]["VALUE"];

            $arTeam = [];

            $el["stage1"] = $this->fillStageArray($res["PROPERTY_STAGE1_VALUE"]);
            $el["stage2"] = $this->fillStageArray($res["PROPERTY_STAGE2_VALUE"]);
            $el["stage3"] = $this->fillStageArray($res["PROPERTY_STAGE3_VALUE"]);

            $el["result"] = $this->fillStageArray($res["PROPERTY_RESULT_VALUE"]);

            foreach ($arElem as $key=>$item){
                $arTeam[$item]['name'] = $this->arTeams[$item]['NAME'];
                $arTeam[$item]['stage1'] = $el["stage1"][$key];
                $arTeam[$item]['stage2'] = $el["stage2"][$key];
                $arTeam[$item]['stage3'] = $el["stage3"][$key];

                $arTeam[$item]['result'] = $el["result"][$key];
            }

            $el["teams"] = $arTeam;

//            $res["PROPERTY_STAGE1_VALUE"] = '5,4.2,4.4,5,4';

            $this->arResult["games"][] = $el;

            if ($el["active"] === "Y") {
                $this->arResult["active_count"]++;
            } else {
                $this->arResult["not_active_count"]++;
            }

            if($this->actEvent) $this->arResult['event_active'] = $this->getEventInfo();

        }

    }

    protected function fillStageArray($str){
        return $str ? explode( ',', $str) : array_fill(0, 5, 0);
    }

    protected function getUserInfo()
    {
        $uid = CUser::GetID();

        if ($uid) {
            $dbUser = UserTable::getList(array(
                'select' => array('ID', 'UF_EVENT'),
                'filter' => array('=ID' => $uid)
            ))->fetch();
            $this->userId = $dbUser["ID"];
//            $this->actEvent = $dbUser["UF_EVENT"];
        }

    }

    protected function getEventInfo(){
        $response = \Bitrix\Iblock\ElementTable::getList(
            [
                'select' => ['ID', 'PREVIEW_TEXT', 'PREVIEW_PICTURE', 'DETAIL_TEXT'],
                'filter' => [
                    "IBLOCK_ID" => $this->eventIb,
                    "=ID" => $this->actEvent
                ]
            ]
        )->fetch();
        $response['img'] = CFile::GetPath($response["PREVIEW_PICTURE"]);

        return $response;
    }

    protected function getTeamInfo()
    {

        $arr = [];

        $response = \Bitrix\Iblock\ElementTable::getList(
            [
                'select' => ['ID', 'NAME', 'PREVIEW_PICTURE'],
                'filter' => [
                    "IBLOCK_ID" => $this->teamIb,

                ]
            ]
        );

        while ($res = $response->fetch()) {
            $res["img"] = CFile::GetPath($res["PREVIEW_PICTURE"]);
            $arr[$res["ID"]] = $res;
        }

        $this->arTeams = $arr;

    }

    /**
     * @return array
     */
    public function getArResult(): array
    {
        return $this->arResult;
    }

}