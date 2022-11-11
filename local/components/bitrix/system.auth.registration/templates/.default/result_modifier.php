<?php

use Bitrix\Main\Loader;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED!==true)die();

if (!Loader::includeModule('iblock')) {
    ShowError('Модуль Информационных блоков не установлен');
    return;
}

$row = Bitrix\Main\UserTable::getList([
    "select" => ["ID"],
])->fetchAll();

$arResult["new_user_number"] = count($row) + 1;
