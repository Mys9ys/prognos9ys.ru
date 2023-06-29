<?php

class PrognosisGiveInfo
{
    protected $arGive = [
        'status' => '',
        'mes' => ''
    ];

    protected $arPeriod = [
        'yesterday' => ['period' => 'yesterday', 'name' => 'Вчера', 'visible' => false, 'count' => 0, 'set' => 0],
        'today' => ['period' => 'today', 'name' => 'Сегодня', 'visible' => true, 'count' => 0, 'set' => 0],
        'tomorrow' => ['period' => 'tomorrow', 'name' => 'Завтра', 'visible' => false, 'count' => 0, 'set' => 0],
    ];

    protected function convertData($data){
        $date = explode("+", ConvertDateTime($data, "DD.MM+HH:Mi"));

        return [
            "date" => $date[0],
            "time" => $date[1]
        ];
    }

    protected function setRatio($ibIds, $matchId)
    {

        $arFilter = [
            'IBLOCK_ID' => $ibIds,
            'PROPERTY_MATCH_ID' => $matchId,
        ];

        $arRatio = [
            'plus' => 0,
            'equal' => 0,
            'minus' => 0,
            'count' => 0
        ];

        $response = CIBlockElement::GetList(
            [],
            $arFilter,
            false,
            [],
            [
                "PROPERTY_diff",
            ]
        );

        while ($res = $response->GetNext()) {

            if ($res['PROPERTY_DIFF_VALUE'] > 0) $arRatio['plus'] += 1;
            if ($res['PROPERTY_DIFF_VALUE'] == 0) $arRatio['equal'] += 1;
            if ($res['PROPERTY_DIFF_VALUE'] < 0) $arRatio['minus'] += 1;

            $arRatio['count'] += 1;

        }

        $arRatioScore = [
            0 => ['name' => 'п1', 'count' => number_format(($arRatio['count'] + 1) / ($arRatio['plus'] + 1), 2)],
            1 => ['name' => 'н', 'count' => number_format(($arRatio['count'] + 1) / ($arRatio['equal'] + 1), 2)],
            2 => ['name' => 'п2', 'count' => number_format(($arRatio['count'] + 1) / ($arRatio['minus'] + 1), 2)],
            3 => ['name' => 'Σ', 'count' => $arRatio['count']]
        ];

        return $arRatioScore;

    }
    protected function fillSectionArray($date, $set)
    {

        $now = date(\CDatabase::DateFormatToPHP("DD.MM.YYYY"), time());
        $now = date_create($now);

        $dateMatch = date_create(explode(' ', $date)[0]);

        $interval = date_diff($now, $dateMatch);

        $intervalDay = +$interval->format('%R%a');

        if ($intervalDay === 0) {
            $this->arPeriod['today']['count'] += 1;
            $this->arPeriod['today']['set'] += $set;
            $arr = $this->arPeriod['today'];
        }

        if ($intervalDay === 1) {
            $this->arPeriod['tomorrow']['count'] += 1;
            $this->arPeriod['tomorrow']['set'] += $set;
            $arr = $this->arPeriod['tomorrow'];
        }

        if ($intervalDay === -1) {
            $this->arPeriod['yesterday']['count'] += 1;
            $this->arPeriod['yesterday']['set'] += $set;
            $arr = $this->arPeriod['yesterday'];
        }

        $this->checkVisible();

        return $arr;

    }

    protected function checkVisible(){
        $this->visibleReset();

        if($this->arPeriod['today']['count']> 0) {
            $this->arPeriod['today']['visible'] = true;
        } elseif($this->arPeriod['tomorrow']['count']> 0) {
            $this->arPeriod['tomorrow']['visible'] = true;
        } elseif($this->arPeriod['yesterday']['count']> 0) {
            $this->arPeriod['yesterday']['visible'] = true;
        } elseif($this->arPeriod['nearest']['count']> 0) {
            $this->arPeriod['nearest']['visible'] = true;
        }

    }

    protected function visibleReset(){
        foreach ($this->arPeriod as $status=>$period){
            $this->arPeriod[$status]['visible'] = false;
        }
    }


    protected function setResult($status, $mes, $data = '')
    {
        $this->arGive['status'] = $status;
        $this->arGive['mes'] = $mes;
        if ($data) $this->arGive['result'] = $data;
    }

    public function result()
    {
        return $this->arGive;
    }

}