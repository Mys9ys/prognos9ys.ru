<?php

use Bitrix\Main\Loader;

class SetBotRacePrognosis
{

    protected $arBots;
    protected $emptyEvent;

    protected $data;

    protected $arIbs = [
        'f1races' => ['code' => 'f1races', 'id' => 11],
        'prognosf1' => ['code' => 'prognosf1', 'id' => 13]
    ];

    public function __construct($data)
    {
        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        }

        $this->data = $data;

        $this->arBots = (new GetBotsClass())->result();

        $this->getEmptyRace();

        if($this->emptyEvent) $this->checkEmptyPrognosis();


    }

    protected function getEmptyRace()
    {

        $arFilter = [
            'IBLOCK_ID' => $this->arIbs['f1races']['id'],
            'PROPERTY_number' => $this->data['number']
        ];

        $response = CIBlockElement::GetList(
            ["DATE_ACTIVE_FROM" => "ASC", "created" => "ASC"],
            $arFilter,
            false,
            [],
            [
                "ID",
                "PROPERTY_number",
                "PROPERTY_events",
            ]
        );

        while ($res = $response->GetNext()) {
            $this->emptyEvent[] = $res;
        }

    }

    protected function checkEmptyPrognosis()
    {

        foreach ($this->emptyEvent as $race) {
            foreach ($this->arBots as $botId) {


                $arFilter = [
                    'IBLOCK_ID' => $this->arIbs['prognosf1']['id'],
                    'PROPERTY_user_id' => $botId,
                    'PROPERTY_race_id' => $race['ID']
                ];

                $res = CIBlockElement::GetList(
                    [],
                    $arFilter,
                    false,
                    [],
                    [
                        "ID",
                    ]
                )->GetNext();

                if(!$res){
                    $props = [];
                    $props[83] = $race['PROPERTY_NUMBER_VALUE']; // number
                    $props[84] = $botId; // user_id
                    $props[85] = $race['ID']; // race_id
                    $props[89] = $race['PROPERTY_EVENTS_VALUE']; // events

                    $this->setPrognosis($props);
                }
            }
        }
    }

    protected function setPrognosis($props){

        $gen = (new GenRacePrognosis($this->data['sprint']))->result();

        $props = array_replace($props, $gen);

        $now = date(\CDatabase::DateFormatToPHP("DD.MM.YYYY HH:MI:SS"), time());

        $ib = new CIBlockElement;
        $data = [
            'NAME' => "Участник: " . $props[84] . " Прогноз на гонку: " .$props[83],
            'IBLOCK_ID' => $this->arIbs['prognosf1']['id'],
            'DATE_ACTIVE_FROM' => $now,
            'PROPERTY_VALUES' => $props
        ];

        $result = $ib->Add($data);

    }
}