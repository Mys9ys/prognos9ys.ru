<?php

use Bitrix\Main\Loader;

class RaceOneHandler
{
    protected $arCountry;
    protected $arRacers;
    protected $arIBs = [
        'f1races' => ['code' => 'f1races', 'id' => 11],
        'prognosf1' => ['code' => 'prognosf1', 'id' => 13],
        'resultf1' => ['code' => 'resultf1', 'id' => 14]
    ];

    protected $arResult;

    protected $arGetValue = [
        'prognosis' => 'prognosf1',
        'result_score' => 'resultf1',
    ];

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

        $this->data['userId'] = (new GetUserIdForToken($_REQUEST['userToken']))->getID();

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
                'PROPERTY_status',

                'PROPERTY_qual_res',
                'PROPERTY_sprint_res',
                'PROPERTY_race_res',
                'PROPERTY_best_lap',

            ]
        )->GetNext();
        $el = [];

        $el["img"] = CFile::GetPath($res["PREVIEW_PICTURE"]);
        $el["country"] = $this->arCountry[$res["PROPERTY_COUNTRY_VALUE"]];

        $el["qual"] = $this->convertData($res["ACTIVE_FROM"]);

        $el["date"] = $el["qual"]["date"];
        $el["active"] = $res["ACTIVE"];
        $el["id"] = $res["ID"];

        $el["event"] = $res["PROPERTY_EVENTS_VALUE"];

        $el["race"] = $this->convertData($res["ACTIVE_TO"]);

        $el["racers"] = $this->arRacers;

        foreach ($this->arGetValue as $title=>$code){
            $el[$title] = $this->getIbProps($code, $el["id"]);
        }

        if ($res["PROPERTY_QUAL_RES_VALUE"]){
            $el["result_race"]["qual_res"]= json_decode($res["~PROPERTY_QUAL_RES_VALUE"]["TEXT"]) ?? [];
            $el["result_race"]["race_res"]= json_decode($res["~PROPERTY_RACE_RES_VALUE"]["TEXT"]) ?? [];
            $el["result_race"]["sprint_res"]= json_decode($res["~PROPERTY_SPRINT_RES_VALUE"]["TEXT"]) ?? [];
            $el["result_race"]["best_lap"]= json_decode($res["~PROPERTY_BEST_LAP_VALUE"]) ?? [];
        }

        if ($res["PROPERTY_SPRINT_VALUE"]) {

            $el["sprint"] = $this->convertData($res["PROPERTY_SPRINT_VALUE"]);
        }

        $el["name"] = $res["NAME"];

        $el["number"] = $res["PROPERTY_NUMBER_VALUE"];

        $this->arResult['info'] = $el;

    }

    protected function getIbProps($code, $raceId){
        $arFilter = [
            "IBLOCK_ID" => $this->arIBs[$code]['id'],
            'PROPERTY_EVENTS' => $this->data['events'],
            'PROPERTY_user_id' => $this->data['userId'],
            'PROPERTY_race_id' => $raceId
        ];

        $res = CIBlockElement::GetList(
            [],
            $arFilter,
            false,
            [],
            [
                'TIMESTAMP_X',
                'PROPERTY_qual_res',
                'PROPERTY_race_res',
                'PROPERTY_sprint_res',
                'PROPERTY_best_lap',
            ]
        )->GetNext();

            $el = [];

            $el['qual_res'] = json_decode($res['~PROPERTY_QUAL_RES_VALUE']['TEXT']) ?? [];
            $el['race_res'] = json_decode($res['~PROPERTY_RACE_RES_VALUE']['TEXT']) ?? [];
            $el['sprint_res'] = json_decode($res['~PROPERTY_SPRINT_RES_VALUE']['TEXT']) ?? [];
            $el['best_lap'] = json_decode($res['~PROPERTY_BEST_LAP_VALUE']) ?? [];

            return $el;

    }

    protected function convertData($data)
    {
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