<?php

use Bitrix\Main\Loader;

class GetPrognosisEvents
{
    protected $eventsIb = 1;

    protected $arEvents = [];

    public function __construct()
    {
        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        }

        $this->getEvents();
    }

    protected function getEvents(){
        $arFilter = [
            'IBLOCK_ID' => $this->eventsIb,
        ];

        $response = CIBlockElement::GetList(
            [],
            $arFilter,
            false,
            [],
            ["ID","NAME","PREVIEW_PICTURE", "DETAIL_TEXT", "ACTIVE", "PREVIEW_TEXT", "EXTERNAL_ID", "PROPERTY_e_type"]
        );
        while ($res=$response->GetNext()){

            $res["img"] = CFile::GetPath($res["PREVIEW_PICTURE"]);
            $res['code'] = $res["PROPERTY_E_TYPE_VALUE"];

            $this->arEvents[$res["ID"]] = $res;

        }
    }

    public function result(){
        return [
            "status" => 'ok',
            "events" =>$this->arEvents
        ];
    }
}