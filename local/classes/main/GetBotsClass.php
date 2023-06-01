<?php

use Bitrix\Main\Loader;

class GetBotsClass
{
    protected $arBots;

    public function __construct()
    {
        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        }

        $this->getBots();
    }

    protected function getBots(){
        $this->arBots = CGroup::GetGroupUser(6);
    }

    public function result(){
        return $this->arBots;
    }
}