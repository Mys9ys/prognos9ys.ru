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

        CIBlockElement::SetPropertyValueCode($res['ID'], 69 ,$res['seen']);

        $this->one = $res;
    }

    protected function setResult($status, $mes, $info = '')
    {
        $this->arResult['status'] = $status;
        $this->arResult['mes'] = $mes;
        $this->arResult['info'] =  $info;
    }

    public function setLike($arr): array{
        CIBlockElement::SetPropertyValueCode($arr['prankId'], 70 ,$arr['likes']);

        return ['status' => 'ok'];
    }

    public function loadPrank($arr){
        $arFields = [
            71 => $arr['userId']
        ];
        $ib = new CIBlockElement;
        $data = [
            "IBLOCK_ID" => 4,
            'DATE_ACTIVE_FROM' => date(\CDatabase::DateFormatToPHP("DD.MM.YYYY HH:MI:SS"), time()),
            "PROPERTY_VALUES"=>$arFields,
            "PREVIEW_TEXT" => $arr['text'],
            "NAME" => "Участник: " .$arr['userId'] . " Добавил : " . date(\CDatabase::DateFormatToPHP("DD.MM.YYYY HH:MI:SS"), time())
        ];

        $ib->Add($data);

        return ['status' => 'ok'];

    }

    public function result(){
        $this->setResult('ok', '', $this->one);
        return $this->arResult;
    }


}