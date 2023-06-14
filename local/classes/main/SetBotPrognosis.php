<?php

use Bitrix\Main\Loader;

class SetBotPrognosis
{
    protected $matchesIb;
    protected $prognosisIb;

    protected $arBots = [];
    protected $arAciveMatches = [];

    protected $arEmptyPrognosis = [];

    public function __construct()
    {
        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        }

        $this->matchesIb = \CIBlock::GetList([], ['CODE' => 'matches'], false)->Fetch()['ID'] ?: 2;
        $this->prognosisIb = \CIBlock::GetList([], ['CODE' => 'prognosis'], false)->Fetch()['ID'] ?: 6;

        $this->getBotArray();
        $this->getActiveMatch();

        if(count($this->arBots)>0 || count($this->arAciveMatches)>0) {
            $this->getEmptyBotPrognosis();
        }

    }

    protected function getBotArray(){
        $this->arBots = CGroup::GetGroupUser(6);
    }

    protected function getActiveMatch(){
        $arFilter["IBLOCK_ID"] = $this->matchesIb;
        $arFilter["ACTIVE"] = 'Y';
        $now = new DateTime();
        $arFilter[">=DATE_ACTIVE_FROM"] = $now->modify('-1 day')->format('d.m.Y H:i:s');
        $arFilter["<=DATE_ACTIVE_FROM"] = $now->modify('+2 day')->format('d.m.Y H:i:s');

        $response = CIBlockElement::GetList(
            ["DATE_ACTIVE_FROM" => "ASC", "created" => "ASC"],
            $arFilter,
            false,
            [],
            [
                "ID",
                "NAME",
                "PROPERTY_number",
                "PROPERTY_events",
                "PROPERTY_stage",
            ]
        );

        while ($res = $response->GetNext()) {
            $this->arAciveMatches[] = $res;
        }
    }

    protected function getEmptyBotPrognosis(){

        foreach ($this->arBots as $botID){
            foreach ($this->arAciveMatches as $botPrognos){

                $arFilter["IBLOCK_ID"] = $this->prognosisIb;
                $arFilter["PROPERTY_MATCH_ID"] = $botPrognos['ID'];
                $arFilter["PROPERTY_USER_ID"] = $botID;

                $res = CIBlockElement::GetList(
                    [],
                    $arFilter,
                    false,
                    [],
                    [
                        "ID",
                    ]
                )->GetNext();

                if(!$res) {
                    $this->arEmptyPrognosis[$botID.'_'.$botPrognos['ID']] = [
                        17 => $botPrognos['ID'],
                        30 => $botPrognos['PROPERTY_NUMBER_VALUE'],
                        31 => $botID,
                        52 => $botPrognos['PROPERTY_EVENTS_VALUE'],
                        'stage' => $botPrognos['PROPERTY_STAGE_VALUE'], // Плей-офф
                    ];
                    $this->setBotPrognosis($this->arEmptyPrognosis[$botID.'_'.$botPrognos['ID']]);
                }

            }
        }
    }

    protected function setBotPrognosis($arr){
        $playOff = false;
        if($arr['stage'] === 'Плей-офф') $playOff = true;
        unset($arr['stage']);

        $response = new GenValuesBotFootball($playOff);

        $props = $response->getArFields();

        $props = array_replace($props, $arr);

        $now = date(\CDatabase::DateFormatToPHP("DD.MM.YYYY HH:MI:SS"), time());

        $ib = new CIBlockElement;
        $data = [
            "NAME" => "Участник: " . $props[31] . " Прогноз на матч: " .$props[17] . "  номер " . $props[30],
            "IBLOCK_ID" => $this->prognosisIb,
            'DATE_ACTIVE_FROM' => $now,
            "PROPERTY_VALUES" => $props
        ];

        $result = $ib->Add($data);

    }
}