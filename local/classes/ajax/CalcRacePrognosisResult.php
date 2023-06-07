<?php

use Bitrix\Main\Loader;

class CalcRacePrognosisResult
{

    protected $arIbs = [
        'f1races' => ['code' => 'f1races', 'id' => 11],
        'prognosf1' => ['code' => 'prognosf1', 'id' => 13],
        'resultf1' => ['code' => 'resultf1', 'id' => 14]
    ];

    protected $arRaceResult;
    protected $arUSerPrognosis;

    protected $arResult;
    protected $arrResult;
    protected $number;

    protected $arScore = [
        'qual_res' => [
            0 => 3,
            1 => 2,
            2 => 1
        ],
        'sprint_res' => [
            0 => 3,
            1 => 2,
            2 => 1
        ],
        'race_res' => [
            0 => 5,
            1 => 3,
            2 => 1
        ],
        'best_lap' => [
            0 => 5
        ]
    ];

    protected $ibCell = [
        'number' => '92',
        'user_id' => '93',
        'race_id' => '94',
        'events' => '95',
        'qual_res' => '96',
        'qual_sum' => '97',
        'sprint_res' => '98',
        'sprint_sum' => '99',
        'race_res' => '100',
        'race_sum' => '101',
        'best_lap' => '102',
        'all' => '103',
    ];

    protected $data;

    public function __construct($data)
    {
        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        }

        $this->data = $data;
        $this->getRaceResult();
        $this->getRacePrognosis();
        $this->calcScore();

        $this->setResultIb();

    }

    protected function getRaceResult()
    {

        $arFilter = [
            'IBLOCK_ID' => $this->arIbs['f1races']['id'],
            'ID' => $this->data['race_id']
        ];

        $res = CIBlockElement::GetList(
            [],
            $arFilter,
            false,
            [],
            [
                "PROPERTY_number",
                "PROPERTY_events",
                "PROPERTY_sprint",
                "PROPERTY_qual_res",
                "PROPERTY_sprint_res",
                "PROPERTY_race_res",
                "PROPERTY_best_lap",
            ]
        )->GetNext();

        $el = [];

        $el['sprint_res'] = $res['PROPERTY_SPRINT_VALUE'] ? array_flip(json_decode($res['~PROPERTY_SPRINT_RES_VALUE']['TEXT'])) : '';
        $el['qual_res'] = $res['PROPERTY_QUAL_RES_VALUE'] ? array_flip(json_decode($res['~PROPERTY_QUAL_RES_VALUE']['TEXT'])) : '';
        $el['race_res'] = $res['PROPERTY_RACE_RES_VALUE'] ? array_flip(json_decode($res['~PROPERTY_RACE_RES_VALUE']['TEXT'])) : '';
        $el['best_lap'] = $res['PROPERTY_BEST_LAP_VALUE'] ? json_decode($res['~PROPERTY_BEST_LAP_VALUE']) : '';

        $this->number = $res['PROPERTY_NUMBER_VALUE'];

        $this->arRaceResult = $el;
    }

    protected function getRacePrognosis()
    {
        $arFilter = [
            'IBLOCK_ID' => $this->arIbs['prognosf1']['id'],
            'PROPERTY_race_id' => $this->data['race_id'],
        ];

        $response = CIBlockElement::GetList(
            ["DATE_ACTIVE_FROM" => "ASC", "created" => "ASC"],
            $arFilter,
            false,
            [],
            [
                "PROPERTY_user_id",
                "PROPERTY_race_id",
                "PROPERTY_number",
                "PROPERTY_events",
                "PROPERTY_qual_res",
                "PROPERTY_sprint_res",
                "PROPERTY_race_res",
                "PROPERTY_best_lap",
            ]
        );

        while ($res = $response->GetNext()) {

            $el = [];
            $info = [];

            $info['user_id'] = $res['PROPERTY_USER_ID_VALUE'];
            $info['number'] = $res['PROPERTY_NUMBER_VALUE'];
            $info['events'] = $res['PROPERTY_EVENTS_VALUE'];
            $info['race_id'] = $this->data['race_id'];

            $el['sprint_res'] = $res['PROPERTY_SPRINT_RES_VALUE'] ? array_flip(json_decode($res['~PROPERTY_SPRINT_RES_VALUE']['TEXT'])) : '';
            $el['qual_res'] = $res['PROPERTY_QUAL_RES_VALUE'] ? array_flip(json_decode($res['~PROPERTY_QUAL_RES_VALUE']['TEXT'])) : '';
            $el['race_res'] = $res['PROPERTY_RACE_RES_VALUE'] ? array_flip(json_decode($res['~PROPERTY_RACE_RES_VALUE']['TEXT'])) : '';
            $el['best_lap'] = $res['PROPERTY_BEST_LAP_VALUE'] ? json_decode($res['~PROPERTY_BEST_LAP_VALUE']) : '';

            $this->arUSerPrognosis[$info['user_id']]['info'] = $info;
            $this->arUSerPrognosis[$info['user_id']]['data'] = $el;

        }
    }

    protected function calcScore()
    {

        foreach ($this->arUSerPrognosis as $id => $item) {
            $this->arResult[$id]['info'] = $item['info'];
            foreach ($item['data'] as $title => $arUserProg) {
                if ($arUserProg) {
                    foreach ($arUserProg as $racer => $place) {

                        if ($this->arRaceResult[$title][$racer] || $this->arRaceResult[$title][$racer]>-1) {
                            $diff = $this->arRaceResult[$title][$racer] - $place;

                            $score = $this->arScore[$title][abs($diff)];

                            if ($title === 'best_lap') {
                                if($score){
                                    $this->arResult[$id]['data'][$title][$place] = $score;
                                } else {
                                    $this->arResult[$id]['data'][$title][] = 0;
                                }
                            }

                            if ($title !== 'best_lap') {
                                $this->arResult[$id]['data'][$title][] = $score ?? 0.5;
                                $this->arResult[$id]['data'][str_replace('res', 'sum', $title)] += $this->arResult[$id]['data'][$title][$place];
                            }

                            $this->arResult[$id]['data']['all'] += $this->arResult[$id]['data'][$title][$place];
                        } else {
                            $this->arResult[$id]['data'][$title][$place] = 0;
                        }
                    }
                }
            }
        }
    }

    protected function setResultIb()
    {

        foreach ($this->arResult as $user_id => $item) {
            $old_id = $this->checkOld($user_id);

            if ($old_id) {
                $this->updateData($item);
            } else {
                $this->addData($item);
            }

        }

        $score = new SetRacersScore(['number' => $this->number, 'racers' => $this->arRaceResult['race_res']]);
        if($this->arRaceResult['sprint_res']) $score = new SetRacersScore(['number' => $this->number . '_s', 'racers' => $this->arRaceResult['sprint_res']]);

        $this->setResult('ok', '');

    }
    protected function checkOld($user_id)
    {
        $arFilter = [
            'IBLOCK_ID' => $this->arIbs['resultf1']['id'],
            'PROPERTY_race_id' => $this->data['race_id'],
            'PROPERTY_user_id' => $user_id,
        ];

        $response = CIBlockElement::GetList(
            [],
            $arFilter,
            false,
            [],
            [
                "ID"
            ]
        )->GetNext();

        return $response['ID'];
    }


    protected function addData($item)
    {

        foreach ($item['info'] as $name => $value) {
            $props[$this->ibCell[$name]] = $value;
        }

        foreach ($item['data'] as $name => $value) {
            $props[$this->ibCell[$name]] = json_encode($value);
        }

        $now = date(\CDatabase::DateFormatToPHP("DD.MM.YYYY HH:MI:SS"), time());

        $ib = new CIBlockElement;
        $data = [
            "NAME" => "Участник: " . $item['info']['user_id'] . " Результаты на : " . $item['info']['number'],
            "IBLOCK_ID" => $this->arIbs['resultf1']['id'],
            'DATE_ACTIVE_FROM' => $now,
            "PROPERTY_VALUES" => $props
        ];

        $result = $ib->Add($data);
    }

    protected function updateData($item)
    {
        $props = [];
        foreach ($item['info'] as $name => $value) {
            $props[$this->ibCell[$name]] = $value;
        }

        foreach ($item['data'] as $name => $value) {
            $props[$this->ibCell[$name]] = json_encode($value);
        }

        CIBlockElement::SetPropertyValuesEx($item['race_id'], $this->arIbs['f1races']['id'], $props);
    }

    protected function setResult($status, $mes, $info = '')
    {
        $this->arrResult['status'] = $status;
        $this->arrResult['mes'] = $mes;
    }

    public function result()
    {
        return $this->arrResult;
    }
}