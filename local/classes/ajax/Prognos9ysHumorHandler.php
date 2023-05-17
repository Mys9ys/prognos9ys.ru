<?php

use Bitrix\Main\Loader;

class Prognos9ysHumorHandler
{

    protected $arResult =[];
    protected $one = [];

    public function __construct()
    {

        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        }

        $this->getRandomPrank();
    }

    protected function getRandomPrank(){

        $arFilter['IBLOCK_ID'] = 4;

        $res = CIBlockElement::GetList(
            ["RAND" => "ASC"],
            $arFilter,
            false,
            ["nTopCount" => 1],
            [
                "ID",
                "PREVIEW_TEXT",
                "PROPERTY_seen",
                "PROPERTY_likes",
                "PROPERTY_author",
            ]
        )->GetNext();

        $res['seen'] = $res['PROPERTY_SEEN_VALUE'] ?? 0;
        $res['seen']++;
        $res['likes'] = $res['PROPERTY_LIKES_VALUE'];
        $res['author'] = $res['PROPERTY_AUTHOR_VALUE'];

        $setResult = CIBlockElement::SetPropertyValueCode($res['ID'], 69 ,$res['seen']);

        $this->one = $res;
    }

    protected function setResult($status, $mes, $info = '')
    {
        $this->arResult['status'] = $status;
        $this->arResult['mes'] = $mes;
        $this->arResult['info'] =  $info;
    }

    public function result(){
        $this->setResult('ok', '', $this->one);
        return $this->arResult;
    }


}