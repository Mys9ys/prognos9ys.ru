<?php

class GetUserRank
{
    protected $arRanks = [
        0 => ['name' => 'Заглянувший', 'class' => 'blur'],
        1 => ['name' => 'Новичок', 'class' => 'blur'],
        2 => ['name' => 'Тестирующий', 'class' => 'blur'],
        5 => ['name' => 'Посетитель', 'class' => 'blur'],
        10 => ['name' => 'Познающий', 'class' => 'blur'],
        12 => ['name' => 'Сдюживший', 'class' => 'blur'],
        13 => ['name' => 'Счастливчик', 'class' => 'blur'],
        14 => ['name' => 'Познающий', 'class' => 'blur'],
        18 => ['name' => 'Совершеннолетний', 'class' => 'blur'],
        19 => ['name' => 'Познающий', 'class' => 'blur'],
        21 => ['name' => 'Картежник', 'class' => 'blur'],
        22 => ['name' => 'Познающий', 'class' => 'blur'],
        25 => ['name' => 'Увлекшийся', 'class' => 'blur'],
        30 => ['name' => 'Частый гость', 'class' => 'blur'],
        31 => ['name' => 'Чемпион Евро', 'class' => 'blur'],
        32 => ['name' => 'Частый гость', 'class' => 'blur'],
        33 => ['name' => 'Богатырь', 'class' => 'blur'],
        34 => ['name' => 'Частый гость', 'class' => 'blur'],
        50 => ['name' => 'Болельщик', 'class' => 'blur'],
        64 => ['name' => 'Чемпион Мира', 'class' => 'blur'],
        65 => ['name' => 'Болельщик', 'class' => 'blur'],
        69 => ['name' => 'Неприличный', 'class' => 'blur'],
        70 => ['name' => 'Болельщик', 'class' => 'blur'],
        90 => ['name' => 'Дедушка', 'class' => 'blur'],
        91 => ['name' => 'Болельщик', 'class' => 'blur'],
        100 => ['name' => 'Заядлый', 'class' => 'blur'],
        200 => ['name' => 'Познавший', 'class' => 'blur'],
        500 => ['name' => 'Залипший', 'class' => 'blur'],
        1000 => ['name' => 'Завсегдатай', 'class' => 'blur'],
        1001 => ['name' => 'Сказочный', 'class' => 'blur'],

        //Заинтересовавшийся
        //
        //
    ];

    protected $arResult;
    protected $arRes;


    protected $arPrognosisIbs = [
        'prognosis' => ['id' => 6, 'code' => 'football', '' => '', 'select' => 'PROPERTY_MATCH_ID_VALUE'],
        'prognosf1' => ['id' => 13, 'code' => 'race', ],
    ];

    public function __construct($userId)
    {
        foreach ($this->arPrognosisIbs as $codeIb=>$ib){
            $this->getCountPrognosis($ib,$userId);
        }

        $this->checkRankFunc();

        if($this->arRes['rank']) {
            $this->setResult('ok', '', $this->arRes);
        }
    }

    protected function getCountPrognosis($ib, $userId)
    {
        $arFilter= [
            "IBLOCK_ID" => $ib['id'],
            "PROPERTY_USER_ID" => $userId,
        ];

        $arSelect = [
            'ID',
            'PROPERTY_all',
        ];

        $response = CIBlockElement::GetList(
            [],
            $arFilter,
            false,
            [],
            $arSelect,
        );
        while ($res = $response->GetNext()) {
            $arItems[$res['ID']] = $res['PROPERTY_ALL_VALUE'];
            $this->arRes[$ib['code']]['count'] += 1;
            $this->arRes[$ib['code']]['score'] += 1;
            $this->arRes['score'] += 1;
            $this->arRes['count'] += 1;
        }

    }

    protected function checkRankFunc(){
        $this->arRes['rank'] = $this->arRanks[18];
//        for($i=$this->arRes['count']; $i>1; $i--){
//            if($this->arRanks[$i]) {
//                $this->arRes['rank'] = $this->arRanks[$i];
//                break;
//            }
//        }
    }

    protected function setResult($status, $mes, $info = '')
    {
        $this->arResult['info'] = $info;
        $this->arResult['status'] = $status;
        $this->arResult['mes'] = $mes;
    }

    public function result()
    {
        return $this->arResult;
    }
}