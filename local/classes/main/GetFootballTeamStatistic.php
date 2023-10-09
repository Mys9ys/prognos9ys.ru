<?php

class GetFootballTeamStatistic
{

    protected $arIBs = [
        'matches' => ['code' => 'matches', 'id' => 2],
    ];

    protected $m_id;

    public function __construct($match_id)
    {

        if($match_id) $this->m_id = $match_id;


    }

}