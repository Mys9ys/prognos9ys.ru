<?php

use Bitrix\Main\Loader;

class Prognos9ysMainPageInfo {


    protected $arResult = [];

    protected $userToken;

    public function __construct($data)
    {

        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        }

        $this->userToken = $data['userToken'];

        $this->getNearestMatch();

        $this->setResult('ok', '');
    }

    protected function getNearestMatch(){

        $res = new FootballHandlerClass(['type' => 'nearest', 'userToken' => $this->userToken]);

        $this->arResult['nearest'] = $res->getNearest();
    }


    protected function setResult($status, $mes, $info = '')
    {
        $this->arResult['status'] = $status;
        $this->arResult['mes'] = $mes;
    }

    public function result()
    {
        return $this->arResult;
    }
}