<?php

use Bitrix\Main\Loader;

class SetRacersScore
{

    protected $arPositionScore = [
        25,
        18,
        15,
        12,
        10,
        8,
        6,
        4,
        2,
        1
    ];

    protected $arSprintScore = [
        8,
        7,
        6,
        6,
        5,
        4,
        3,
        2,
        1,
    ];

    protected $arIbs = [
        'racers' => ['code' => 'racers', 'id' => 8]
    ];

    protected $data;

    public function __construct($data)
    {
        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        }

        $this->data = $data;
        $this->getRacersRiseScore();
    }

    protected function getRacersRiseScore(){
        $arFilter = [
            'IBLOCK_ID' => $this->arIbs['racers']['id'],
            'ACTIVE' => 'Y'
        ];

        $arFlip = $this->data['racers'];

        $response = CIBlockElement::GetList(
            ["DATE_ACTIVE_FROM" => "ASC", "created" => "ASC"],
            $arFilter,
            false,
            [],
            [
                "ID",
                "PROPERTY_races_score",
            ]
        );

        while ($res = $response->GetNext()) {

            $arRaces = json_decode($res['~PROPERTY_RACES_SCORE_VALUE']['TEXT'], true);

            if($arFlip[$res['ID']]>-1){
                if(strpos('_s', $this->data['number']))
                $arRaces[$this->data['number']] = $this->arPositionScore[$arFlip[$res['ID']]];
            } else {
                $arRaces[$this->data['number']] = 0;
            }

//            $arRaces = [];

            $sum = array_sum($arRaces);

            $props = [
                74 => $sum,
                104 => json_encode($arRaces)
            ];

            CIBlockElement::SetPropertyValuesEx($res['ID'], $this->arIbs['racers']['id'], $props);

        }

    }

}