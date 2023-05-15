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
            ]
        )->GetNext();

        $res['likes'] = rand(5, 35);

        $this->one = $res;
    }

    protected function setResult($status, $mes, $info = '')
    {
        $this->arResult['status'] = $status;
        $this->arResult['mes'] = $mes;
    }

    public function result(){
        $this->setResult('ok', '', $this->one);
        return $this->arResult;
    }


}