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
        $eventsMap = (new GetPrognosisEvents())->result()['events'] ?? [];

        foreach ($this->arPeriod as $period=>$array){
            if($res[$period]['items']){
                foreach ($res[$period]['info'] as $select=>$value){
                    if($value && is_numeric($value)) $this->arPeriod[$period][$select] += $value;
                }

                foreach ($res[$period]['items'] as $event=>$items){
                    if($event === 'football'){
                        foreach ($items as $id=>$match){
                            $eventId = $match['event'];
                            $this->arResult[$period][$event][$eventId]['info'] = $eventsMap[$eventId] ?? null;
                            $this->arResult[$period][$event][$eventId]['items'][] = $match;
                        }
                    } else {
                        $this->arResult[$period][$event]['items'] = $items;
                    }
                }
            }

        }
    }

    public function result()
    {
        $response = parent::result();

        if (!$this->userToken) {
            return $response;
        }

        $userId = (int)((new GetUserIdForToken($this->userToken))->getId() ?? 0);

        if ($userId > 0 && Loader::includeModule('prognos9ys.main')) {
            $response['game'] = (new \Prognos9ys\Main\Service\Game\GameProfileService())
                ->getSummary($userId, false);
        }

        return $response;
    }
}