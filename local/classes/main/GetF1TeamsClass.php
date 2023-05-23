<?php

use Bitrix\Main\Loader;

class GetF1TeamsClass
{

    protected $arResult = [];

    protected $Ib = ['code' => 'f1teams', 'id' => 9];

    public function __construct()
    {
        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        }

        $this->getResult();
    }

    protected function getResult(){

        $response = \Bitrix\Iblock\ElementTable::getList(
            [
                'select' => ['ID', 'NAME', 'PREVIEW_PICTURE'],
                'filter' => [
                    "IBLOCK_ID" => $this->Ib['id'],
                ]
            ]
        );

        while ($res = $response->fetch()) {
            $res["img"] = CFile::GetPath($res["PREVIEW_PICTURE"]);
            $this->arResult[$res["ID"]] = $res;
        }
    }

    public function result(){
        return $this->arResult;
    }
}