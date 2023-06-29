<?php

use Bitrix\Main\Loader;

class Prognos9ysMainPageInfo extends PrognosisGiveInfo
{

    protected $arResult = [];

    protected $userToken;

    protected $arPeriod = [
        'yesterday',
        'today',
        'tomorrow',
    ];

    public function __construct($data)
    {

        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        }

        $this->userToken = $data['userToken'];

        $this->getNearestMatch();

        $this->getNearestRace();

        $this->setResult('ok', '', $this->arResult);
    }

    protected function getNearestMatch(){
//
//        $res = (new FootballHandlerClass(['type' => 'nearest', 'userToken' => $this->userToken]))->getNearest();
//
//        foreach ($this->arPeriod as $period){
//            $this->arResult[$period] = $res[$period];
//        }

    }

    protected function getNearestRace(){
        $res = (new RaceNearestCome(['userToken' => $this->userToken]))->result()['result'];

        foreach ($this->arPeriod as $period){
            $this->arResult[$period] = $res[$period];
        }
    }

}