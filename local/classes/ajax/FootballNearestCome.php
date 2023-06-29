<?php

use Bitrix\Main\Loader;

class FootballNearestCome extends PrognosisGiveInfo
{
    protected $data;

    protected $arTeams;

    protected $arResult;

    protected $arIBs = [
        'matches' => ['code' => 'matches', 'id' => 2],
        'prognosis' => ['code' => 'prognosis', 'id' => 6],
        'result' => ['code' => 'result', 'id' => 7],
    ];

    public function __construct($data)
    {
        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        }

        $this->data = $data;

        $this->arTeams = (new GetFootballTeams())->result();

        $this->data['userId'] = (new GetUserIdForToken($this->data['userToken']))->getID();

        $this->getMatch();

        foreach ($this->arResult as $period=>$ar){
            $this->arResult[$period]['info'] = $this->arPeriod[$period];
        }

        $this->setResult('ok', '', $this->arResult);

    }

    protected function getMatch(){
        $arFilter = [
            "IBLOCK_ID" => $this->arIBs['matches']['id'],
        ];
        $arFilter[">=DATE_ACTIVE_FROM"] = (new DateTime())->modify('-2 day')->format('d.m.Y H:i:s');
        $arFilter["<=DATE_ACTIVE_FROM"] = (new DateTime())->modify('+2 day')->format('d.m.Y H:i:s');

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
                "PROPERTY_group",
                "PROPERTY_stage",
                "PROPERTY_number",
                "PROPERTY_events",
            ]
        );

        while ($res = $response->GetNext()) {

            $el = [];

            $convert = $this->convertData($res["DATE_ACTIVE_FROM"]);

            $el["date"] = $convert['date'];
            $el["time"] = $convert['time'];

            $el["active"] = $res["ACTIVE"];
            $el["number"] = $res["PROPERTY_NUMBER_VALUE"];
            $el["event"] = $res["PROPERTY_EVENTS_VALUE"];

            $el["teams"]["home"] = $this->arTeams[$res["PROPERTY_HOME_VALUE"]];
            $el["teams"]["guest"] = $this->arTeams[$res["PROPERTY_GUEST_VALUE"]];

            $el["send_info"]["send_time"] = $this->getPrognosis($res["ID"]);
            $set = 0;
            if($el["send_info"]["send_time"]) $set = 1;

            $el["send_info"]["score_result"] = $this->getUserResult($res["ID"]);

            $el["ratio"] = $this->setRatio($this->arIBs['prognosis']['id'], $res['ID']);

            $arDataSort = $this->fillSectionArray($res["DATE_ACTIVE_FROM"], $set);

            $this->arResult[$arDataSort['period']]['items']['football'][$el["number"]] = $el;

        }
    }

    protected function getPrognosis($id)
    {
        $arFilter = [
            "IBLOCK_ID" => $this->arIBs['prognosis']['id'],
            'PROPERTY_user_id' => $this->data['userId'],
            'PROPERTY_match_id' => $id,
        ];

        $response = CIBlockElement::GetList(
            [],
            $arFilter,
            false,
            [],
            [
                'TIMESTAMP_X',
            ]
        )->GetNext();

        $convert = $this->convertData($response['TIMESTAMP_X']);

        return $convert['date'] . ' ' . $convert['time'];

    }

    protected function getUserResult($id)
    {
        $arFilter = [
            "IBLOCK_ID" => $this->arIBs['result']['id'],
            'PROPERTY_match_id' => $id,
            'PROPERTY_user_id' => $this->data['userId']
        ];

        $res = CIBlockElement::GetList(
            [],
            $arFilter,
            false,
            [],
            [
                'PROPERTY_all',
            ]
        )->GetNext();

        return $res['PROPERTY_ALL_VALUE'] ?? 0;
    }




}