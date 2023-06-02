<?php

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Loader;

class RaceManyHandler
{
    protected $arCountry;
    protected $arIBs = [
        'f1races' => ['code' => 'f1races', 'id' => 11],
        'prognosf1' => ['code' => 'prognosf1', 'id' => 13],
        'resultf1' => ['code' => 'resultf1', 'id' => 14]
    ];

    protected $arResult;

    protected $arFill;

    protected $arPrognosis;
    protected $arProgResult;

    protected $data = [];

    public function __construct($data)
    {
        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        }

        $this->data = $data;

        $this->arCountry = (new GetFootballTeams())->result();

        $this->data['userId'] = (new GetUserIdForToken($_REQUEST['userToken']))->getID();

        $this->getUserPrognosis();
        $this->getUserResult();

        $this->getResult();

        if ($this->arFill) {
            $this->setResult('ok', '', $this->arFill);
        } else {
            $this->setResult('error', 'Ошибка запроса');
        }
    }


    protected function getResult()
    {

        $arFilter = [
            "IBLOCK_ID" => $this->arIBs['f1races']['id'],
            'PROPERTY_EVENTS' => $this->data['events']
        ];

        $response = CIBlockElement::GetList(
            ["PROPERTY_72_VALUE" => "DESC"],
            $arFilter,
            false,
            [],
            [
                'ID', 'NAME', 'PREVIEW_PICTURE',
                'ACTIVE_FROM',
                'ACTIVE_TO',
                'ACTIVE',
                'PROPERTY_country',
                'PROPERTY_number',
                'PROPERTY_sprint',
                'PROPERTY_events',
                'PROPERTY_status'
            ]
        );

        while ($res = $response->GetNext()) {
            $el = [];

            $el["img"] = CFile::GetPath($res["PREVIEW_PICTURE"]);
            $el["country"] = $this->arCountry[$res["PROPERTY_COUNTRY_VALUE"]];

            $el["qual"] = $this->convertData($res["ACTIVE_FROM"]);

            $el["date"] = $el["qual"]["date"];
            $el["active"] = $res["ACTIVE"];
            $el["event"] = $res["PROPERTY_EVENTS_VALUE"];

            $el["race"] = $this->convertData($res["ACTIVE_TO"]);

            if ($res["PROPERTY_SPRINT_VALUE"]) {

                $el["sprint"] = $this->convertData($res["PROPERTY_SPRINT_VALUE"]);
            }

            $el["fill"] = $this->arPrognosis[$res["ID"]];
            $el["result"] = $this->arProgResult[$res["ID"]];

            $el["status"] = 'Ожидается';

            if ($res["PROPERTY_STATUS_VALUE"]) {
                $el["status"] = 'Отменена';
            } else {
                if ($el["active"] === 'N') {
                    $el["status"] = 'Завершена';
                }
            }

            $el["name"] = $res["NAME"];

            $el["number"] = $res["PROPERTY_NUMBER_VALUE"];

            $arrIDs = $this->fillSectionArray($res["ACTIVE_FROM"]);

            $this->arFill[$arrIDs['section']]['items'][$el["date"]][$el["number"]] = $el;
            if (!$this->arFill[$arrIDs['section']]['info']) $this->arFill[$arrIDs['section']]['info'] = $arrIDs;
        }

        foreach ($this->arFill as $section => $arr) {
            $this->arFill[$section]['info']['count'] = count($arr['items']);

            if($section === 'nearest' || $section === 'future'){
                krsort($this->arFill[$section]['items']);
            }
        }
    }

    protected function getUserPrognosis(){
        $arFilter = [
            "IBLOCK_ID" => $this->arIBs['prognosf1']['id'],
            'PROPERTY_EVENTS' => $this->data['events'],
            'PROPERTY_user_id' => $this->data['userId']
        ];

        $response = CIBlockElement::GetList(
            [],
            $arFilter,
            false,
            [],
            [
                'PROPERTY_race_id',
                'TIMESTAMP_X',
            ]
        );

        while ($res = $response->GetNext()) {
            $this->arPrognosis[$res['PROPERTY_RACE_ID_VALUE']] = $res['TIMESTAMP_X'];
        }
    }

    protected function getUserResult(){
        $arFilter = [
            "IBLOCK_ID" => $this->arIBs['resultf1']['id'],
            'PROPERTY_EVENTS' => $this->data['events'],
            'PROPERTY_user_id' => $this->data['userId']
        ];

        $response = CIBlockElement::GetList(
            [],
            $arFilter,
            false,
            [],
            [
                'PROPERTY_race_id',
                'PROPERTY_all',
                'PROPERTY_qual_sum',
                'PROPERTY_race_sum',
                'PROPERTY_sprint_sum',
            ]
        );

        while ($res = $response->GetNext()) {
            $el = [];
            $el['qual_sum'] = $res['PROPERTY_QUAL_SUM_VALUE'] ?? 0;
            $el['race_sum'] = $res['PROPERTY_RACE_SUM_VALUE'] ?? 0;
            $el['sprint_sum'] = $res['PROPERTY_SPRINT_SUM_VALUE'] ?? 0;
            $el['all'] = $res['PROPERTY_ALL_VALUE'] ?? 0;

            $this->arProgResult[$res['PROPERTY_RACE_ID_VALUE']] = $el;
        }
    }

    protected function convertData($data){
        $date = explode("+", ConvertDateTime($data, "DD.MM+HH:Mi"));

        return [
            "date" => $date[0],
            "time" => $date[1]
        ];
    }

    protected function fillSectionArray($date)
    {

        $arr = ['section' => '', 'title' => '', 'visible' => false];

        $now = date(\CDatabase::DateFormatToPHP("DD.MM.YYYY"), time());
        $now = date_create($now);

        $dateMatch = date_create(explode(' ', $date)[0]);

        $interval = date_diff($dateMatch, $now);
        $intervalDay = $interval->format('%R%a');

        if ($intervalDay > 0 && $intervalDay < 2) {
            $arr['section'] = 'recent';
            $arr['title'] = 'Недавние';
            $arr['visible'] = true;
        }

        if ($intervalDay > 1) {
            $arr['section'] = 'past';
            $arr['title'] = 'Прошедшие';
            $arr['visible'] = false;
        }

        if ($intervalDay < 1 && $intervalDay > -2) {
            $arr['section'] = 'nearest';
            $arr['title'] = 'Ближайшие';
            $arr['visible'] = true;
        }

        if ($intervalDay < 0) {
            $arr['section'] = 'future';
            $arr['title'] = 'Будущие';
            $arr['visible'] = false;
        }

        return $arr;

    }

    protected function setResult($status, $mes, $info = '')
    {
        $this->arResult['status'] = $status;
        $this->arResult['mes'] = $mes;
        if ($info) $this->arResult['info'] = $info;
    }

    public function result()
    {
        return $this->arResult;
    }
}