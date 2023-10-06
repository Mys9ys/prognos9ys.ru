<?php

use Bitrix\Main\Loader;

class Prognos9ysMainPageInfo extends PrognosisGiveInfo
{

    protected $arResult = [];

    protected $userToken;

    protected $arPeriod = [
        'yesterday' => ['period' => 'yesterday', 'name' => 'Вчера', 'visible' => false, 'count' => 0, 'set' => 0],
        'today' => ['period' => 'today', 'name' => 'Сегодня', 'visible' => true, 'count' => 0, 'set' => 0],
        'tomorrow' => ['period' => 'tomorrow', 'name' => 'Завтра', 'visible' => false, 'count' => 0, 'set' => 0],
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

        $this->checkVisible();

        foreach ($this->arResult as $period=>$ar){
            $this->arResult[$period]['info'] = $this->arPeriod[$period];
        }




        $this->setResult('ok', '', $this->arResult);
    }

    protected function getNearestMatch(){

        $res = (new FootballNearestCome(['userToken' => $this->userToken]))->result()['result'];

        $this->fillData($res);

    }

    protected function getNearestRace(){
        $res = (new RaceNearestCome(['userToken' => $this->userToken]))->result()['result'];

        $this->fillData($res);

    }

    protected function fillData($res){
        foreach ($this->arPeriod as $period=>$array){
            if($res[$period]['items']){
                foreach ($res[$period]['info'] as $select=>$value){
                    if($value && is_numeric($value)) $this->arPeriod[$period][$select] += $value;
                }

                foreach ($res[$period]['items'] as $event=>$items){
                    if($event === 'football'){
                        foreach ($items as $id=>$match){
                            $this->arResult[$period][$event][$match['event']]['info'] = (new GetPrognosisEvents())->result()['events'][$match['event']];
                            $this->arResult[$period][$event][$match['event']]['items'][] = $match;
                        }
                    } else {
                        $this->arResult[$period][$event]['items'] = $items;
                    }
                }
            }

        }
    }
}