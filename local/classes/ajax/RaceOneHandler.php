<?php

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Loader;

class RaceOneHandler
{
    protected $arCountry;
    protected $arTeam;
    protected $arRacers;
    protected $arIBs = [
        'f1races' => ['code' => 'f1races', 'id' => 11],
    ];

    protected $arResult;

    protected $data = [];

    public function __construct($data)
    {
        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        }

        $this->data = $data;

        $this->arCountry = (new GetFootballTeams())->result();
        $this->arRacers = (new GetF1RacersClass())->result();

        $this->getResult();

        if ($this->arResult) {
            $this->setResult('ok', '');
        } else {
            $this->setResult('error', 'Ошибка запроса');
        }
    }


    protected function getResult()
    {

        $arFilter = [
            "IBLOCK_ID" => $this->arIBs['f1races']['id'],
            'PROPERTY_events' => $this->data['events'],
            'PROPERTY_number' => $this->data['number']
        ];

        $res = CIBlockElement::GetList(
            [],
            $arFilter,
            false,
            [],
            [
                'ID',
                'NAME',
                'PREVIEW_PICTURE',
                'ACTIVE_FROM',
                'ACTIVE_TO',
                'ACTIVE',
                'PROPERTY_country',
                'PROPERTY_number',
                'PROPERTY_sprint',
                'PROPERTY_events',

                'PROPERTY_qual_res',
                'PROPERTY_sprint_res',
                'PROPERTY_race_res',

            ]
        )->GetNext();
            $el = [];

            $el["img"] = CFile::GetPath($res["PREVIEW_PICTURE"]);
            $el["country"] = $this->arCountry[$res["PROPERTY_COUNTRY_VALUE"]];

            $el["qual"] = $this->convertData($res["ACTIVE_FROM"]);

            $el["date"] = $el["qual"]["date"];
            $el["active"] = $res["ACTIVE"];
            $el["event"] = $res["PROPERTY_EVENTS_VALUE"];

            $el["race"] = $this->convertData($res["ACTIVE_TO"]);

            $el["racers"] = $this->arRacers;

            if ($res["PROPERTY_SPRINT_VALUE"]) {

                $el["sprint"] = $this->convertData($res["PROPERTY_SPRINT_VALUE"]);
            }

            $el["name"] = $res["NAME"];

            $el["number"] = $res["PROPERTY_NUMBER_VALUE"];

            $this->arResult['info'] = $el;

    }

    protected function convertData($data){
        $date = explode("+", ConvertDateTime($data, "DD.MM+HH:Mi"));

        return [
            "date" => $date[0],
            "time" => $date[1]
        ];
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