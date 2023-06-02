<?php

use Bitrix\Main\Loader;

class GenRacePrognosis
{

    protected $arRacers;

    protected $arRandElems = [];

    protected $arRandTemplate = [];

    protected $arEvents = [
        86 => 10, // qual
        88 => 10, // race
        90 => 1 // best_lap
    ];

    public function __construct($sprint)
    {
        $this->arRacers = (new GetF1RacersClass())->result();

        if($sprint) $this->arEvents[87] = 8; // sprint

        $this->genArrRandTemplate();

        $this->genResult();

    }

    protected function genResult()
    {
        foreach ($this->arEvents as $title=>$count){

            $arr = $this->genRandArray();

            for ($i = 0; $i<$count; $i++){
                $rand = random_int(1, $arr['count']);

                $id = $this->findEl($rand, $arr['rand'], $arr['count']);

                $this->arRandElems[$title][$i] = $id;

                $arr = $this->updateRandArray($arr, $id);

            }

        }

    }

    protected function genArrRandTemplate()
    {
        foreach ($this->arRacers as $id => $item) {
            $this->arRandTemplate[$id] = $item['score'] ? $item['score'] * 20 : 20;
        }
    }

    protected function genRandArray()
    {
        $arr = [];
        foreach ($this->arRandTemplate as $id => $score) {

            $arr['temp'][$id] = $score;

            $arr['count'] += $score;

            $arr['rand'][$arr['count']] = $id;

        }

        return $arr;

    }

    protected function findEl($rand, $arr, $max){
        for ($i=$rand; $i<$max+1; $i++){
            if($arr[$i]) return $arr[$i];
        }
    }

    protected function updateRandArray($arr, $id)
    {
        $res = [];
        unset($arr['temp'][$id]);

        foreach ($arr['temp'] as $id=>$score){

            $res['temp'][$id] = $score;

            $res['count'] += $score;

            $res['rand'][$res['count']] = $id;

        }

        return $res;
    }

    public function result(){
        foreach ($this->arRandElems as $id=>$arr){
            $this->arRandElems[$id] = json_encode($arr);
        }
        return $this->arRandElems;
    }

}