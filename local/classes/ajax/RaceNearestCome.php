<?php

use Bitrix\Main\Loader;

class RaceNearestCome extends PrognosisGiveInfo
{
    protected $data;
    protected $arCountry;

    protected $arResult;

    protected $arIBs = [
        'f1races' => ['code' => 'f1races', 'id' => 11],
        'prognosf1' => ['code' => 'prognosf1', 'id' => 13],
        'resultf1' => ['code' => 'resultf1', 'id' => 14]
    ];

    protected $arPeriod = [
        'yesterday' => ['period' => 'yesterday', 'name' => 'Вчера', 'visible' => false, 'count' => 0, 'set' => 0],
        'today' => ['period' => 'today', 'name' => 'Сегодня', 'visible' => true, 'count' => 0, 'set' => 0],
        'tomorrow' => ['period' => 'tomorrow', 'name' => 'Завтра', 'visible' => false, 'count' => 0, 'set' => 0],
    ];

    public function __construct($data)
    {
        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        }

        $this->data = $data;

        $this->arCountry = (new GetFootballTeams())->result();

        $this->data['userId'] = (new GetUserIdForToken($this->data['userToken']))->getID();

        $this->getRace();

        foreach ($this->arResult as $period=>$ar){
            $this->arResult[$period]['info'] = $this->arPeriod[$period];
        }

        $this->setResult('ok', '', $this->arResult);

    }

    protected function getRace()
    {
        $arFilter = [
            "IBLOCK_ID" => $this->arIBs['f1races']['id'],
        ];
        $arFilter[">=DATE_ACTIVE_FROM"] = (new DateTime())->modify('-2 day')->format('d.m.Y H:i:s');
        $arFilter["<=DATE_ACTIVE_FROM"] = (new DateTime())->modify('+2 day')->format('d.m.Y H:i:s');

        $response = CIBlockElement::GetList(
            [],
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

            $el["status"] = 'Ожидается';

            if ($res["PROPERTY_STATUS_VALUE"]) {
                $el["status"] = 'Отменена';
            } else {
                if ($el["active"] === 'N') {
                    $el["status"] = 'Завершена';
                }
            }



            $el["fill"] = $this->getPrognosis($res["ID"]);
            $set = 0; // прокидываем информацию о заполнении
            if($el["fill"]) $set = 1;

            if($el["active"] === 'N')
                $el["result"] = $this->getUserResult($res["ID"]);

            $el["name"] = $res["NAME"];

            $el["number"] = $res["PROPERTY_NUMBER_VALUE"];

            $arDataSort = $this->fillSectionArray($res["ACTIVE_FROM"], $set);

            $this->arResult[$arDataSort['period']]['items']['race'][$el["number"]] = $el;
        }

    }

    protected function getPrognosis($raceId)
    {
        $arFilter = [
            "IBLOCK_ID" => $this->arIBs['prognosf1']['id'],
            'PROPERTY_user_id' => $this->data['userId'],
            'PROPERTY_race_id' => $raceId,
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

        return $response['TIMESTAMP_X'];

    }

    protected function getUserResult($raceId)
    {
        $arFilter = [
            "IBLOCK_ID" => $this->arIBs['resultf1']['id'],
            'PROPERTY_race_id' => $raceId,
            'PROPERTY_user_id' => $this->data['userId']
        ];

        $res = CIBlockElement::GetList(
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
        )->GetNext();

        $el = [];
        $el['qual_sum'] = $res['PROPERTY_QUAL_SUM_VALUE'] ?? 0;
        $el['race_sum'] = $res['PROPERTY_RACE_SUM_VALUE'] ?? 0;
        $el['sprint_sum'] = $res['PROPERTY_SPRINT_SUM_VALUE'] ?? 0;
        $el['all'] = $res['PROPERTY_ALL_VALUE'] ?? 0;

        return $el;

    }

    protected function fillSectionArray($date, $set)
    {

        $now = date(\CDatabase::DateFormatToPHP("DD.MM.YYYY"), time());
        $now = date_create($now);

        $dateMatch = date_create(explode(' ', $date)[0]);

        $interval = date_diff($now, $dateMatch);

        $intervalDay = +$interval->format('%R%a');

        if ($intervalDay === 0) {
            $this->arPeriod['today']['count'] += 1;
            $this->arPeriod['today']['set'] += $set;
            $arr = $this->arPeriod['today'];
        }

        if ($intervalDay === 1) {
            $this->arPeriod['tomorrow']['count'] += 1;
            $this->arPeriod['tomorrow']['set'] += $set;
            $arr = $this->arPeriod['tomorrow'];
        }

        if ($intervalDay === -1) {
            $this->arPeriod['yesterday']['count'] += 1;
            $this->arPeriod['yesterday']['set'] += $set;
            $arr = $this->arPeriod['yesterday'];
        }

        $this->checkVisible();

        return $arr;

    }

    protected function checkVisible(){
        $this->visibleReset();

        if($this->arPeriod['today']['count']> 0) {
            $this->arPeriod['today']['visible'] = true;
        } elseif($this->arPeriod['tomorrow']['count']> 0) {
            $this->arPeriod['tomorrow']['visible'] = true;
        } elseif($this->arPeriod['yesterday']['count']> 0) {
            $this->arPeriod['yesterday']['visible'] = true;
        } elseif($this->arPeriod['nearest']['count']> 0) {
            $this->arPeriod['nearest']['visible'] = true;
        }

    }

    protected function visibleReset(){
        foreach ($this->arPeriod as $status=>$period){
            $this->arPeriod[$status]['visible'] = false;
        }
    }

}