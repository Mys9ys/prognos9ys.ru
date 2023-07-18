<?php

use Bitrix\Main\Loader;

class ChampionshipRaceTable extends PrognosisGiveInfo
{
    protected $data;

    protected $eventId = '12162';

    public function __construct($data)
    {

        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        }

        $this->data = $data;

    }
}