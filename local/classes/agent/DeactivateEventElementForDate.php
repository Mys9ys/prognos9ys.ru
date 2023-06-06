<?php

use Bitrix\Main\Loader;

class DeactivateEventElementForDate
{
    protected $arIbs = [
        'f1races' => ['code' => 'f1races', 'id' => 11],
        'matches' => ['code' => 'matches', 'id' => 2],
    ];

    public function __construct()
    {
        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        }

        $res = new ChangeActiveItem();
        foreach ($this->arIbs as $ib) {
            $res->inActiveElement($ib['id']);
        }
    }
}