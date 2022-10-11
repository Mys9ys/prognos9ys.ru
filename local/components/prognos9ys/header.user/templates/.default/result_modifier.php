<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED!==true)die();

$dbUser = \Bitrix\Main\UserTable::getList(array(
    'select' => array('ID', 'NAME', 'PERSONAL_PHOTO', 'PERSONAL_WWW'),
    'filter' => array('ID' => $USER->GetID())
));
if ($arUser = $dbUser->fetch()){
    $arResult = $arUser;
}

var_dump($arResult);