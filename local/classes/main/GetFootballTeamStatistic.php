<?php

class GetFootballTeamStatistic
{

    protected $arIbs = [
        'matches' => 'ElementMatchesTable' ,
    ];

    protected $m_id=12150;

    protected $guestArr;
    protected $homeArr;

    protected $teams;

    protected $resultTemp = [
       'home' => [
           'п1' => 1,
           'п2' => 2,
           'н' => 3,
       ],
        'guest' => [
            'п1' => 2,
            'п2' => 1,
            'н' => 3,
        ]
    ];

    public function __construct($match_id)
    {

        if($match_id) $this->m_id = $match_id;

        $this->teams = (new GetFootballTeams())->result();

        var_dump($match_id);

        $this->loadMatchStatistic();

    }

    protected function loadMatchStatistic(){
        $elements = \Bitrix\Iblock\Elements\ElementMatchesTable::getList([
            'order' => ['ACTIVE_FROM' => 'ASC'],
            'select' => [
                'ID',
                'NAME',
                'ACTIVE_FROM',
                'home_' => 'home',
                'guest_' => 'guest',
                'events_' => 'events',
                'result_' => 'result',
                'goal_home_' => 'goal_home',
                'goal_guest_' => 'goal_guest',
                'domination_' => 'domination',
                'corner_' => 'corner',
                'yellow_' => 'yellow',
                'red_' => 'red',
                'penalty_' => 'penalty',
            ],
            'limit' => 15,
            'filter' => ['=ACTIVE' => 'N',
                ["LOGIC" => "OR",
                    ["home_IBLOCK_GENERIC_VALUE" => $this->m_id],
                    ["guest_IBLOCK_GENERIC_VALUE" => $this->m_id]]
            ],
        ])->fetchAll();

        echo '<pre>';
        var_dump($elements[0]);
        echo '</pre>';

       foreach ($elements as $element){
            if($element['home_IBLOCK_GENERIC_VALUE'] == $this->m_id){
                $this->homeHandler($element);
            } else {
                $this->guestHandler($element);
            }
       }
    }

    protected function homeHandler($el){
        $res = [];
        $res['result'] = $this->resultTemp['home'][$el['result_VALUE']];
        $res['name'] = $this->teams[$el['home_IBLOCK_GENERIC_VALUE']]['name'] . ' - ' . $this->teams[$el['guest_IBLOCK_GENERIC_VALUE']]['name'];
        $res['plus'] = $el['goal_home_VALUE'];
        $res['minus'] = $el['goal_guest_VALUE'];
        $res['date'] = explode("+", ConvertDateTime($el["ACTIVE_FROM"], "DD.MM+HH:Mi"))[0];
        $res['domination'] = $el['domination_VALUE'];

        $res['corner'] = $el['corner_VALUE'];
        $res['yellow'] = $el['yellow_VALUE'];
        $res['red'] = $el['red_VALUE'];
        $res['penalty'] = $el['penalty_VALUE'];

        echo '<pre>';
        var_dump($res);
        echo '</pre>';

    }

    protected function guestHandler($el){
        $res = [];
        $res['result'] = $this->resultTemp['guest'][$el['result_VALUE']];
        $res['name'] = $this->teams[$el['home_IBLOCK_GENERIC_VALUE']]['name'] . ' - ' . $this->teams[$el['guest_IBLOCK_GENERIC_VALUE']]['name'];
        $res['minus'] = $el['goal_home_VALUE'];
        $res['plus'] = $el['goal_guest_VALUE'];
        $res['date'] = explode("+", ConvertDateTime($el["ACTIVE_FROM"], "DD.MM+HH:Mi"))[0];
        $res['domination'] = 100 - $el['domination_VALUE'];

        $res['corner'] = $el['corner_VALUE'];
        $res['yellow'] = $el['yellow_VALUE'];
        $res['red'] = $el['red_VALUE'];
        $res['penalty'] = $el['penalty_VALUE'];

        echo '<pre>';
        var_dump($res);
        echo '</pre>';
    }


}