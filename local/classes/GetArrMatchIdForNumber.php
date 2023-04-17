<?php

use Bitrix\Main\Loader;

class GetArrMatchIdForNumber
{
    protected $matchesIb;

    protected $eventId;

    protected $arResult = [];

    public function __construct($eventId)
    {
        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        }
        $this->eventId = $eventId;

        $this->matchesIb = \CIBlock::GetList([], ['CODE' => 'matches'], false)->Fetch()['ID'] ?: 2; // установочные данные матча
        $this->createArray();

    }

    protected function createArray(){
        $arFilter = [
            'IBLOCK_ID' => $this->matchesIb,
            'PROPERTY_EVENTS' => $this->eventId
        ];

        $response = CIBlockElement::GetList(
            ["DATE_ACTIVE_FROM" => "ASC", "created" => "ASC"],
            $arFilter,
            false,
            [],
            [
                "ID",
                "PROPERTY_number",
            ]
        );

        while ($res = $response->GetNext()) {
            $this->arResult[$res["ID"]] = $res["PROPERTY_NUMBER_VALUE"];
        }
    }

    public function getResult(){
        return $this->arResult;
    }
}