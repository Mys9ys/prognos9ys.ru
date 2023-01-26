<?php
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();

use Bitrix\Main\Page\Asset;

// Для подключения css
Asset::getInstance()->addCss($templateFolder . "/assets/style.min.css");

// Для подключения скриптов
Asset::getInstance()->addJs($templateFolder . "/assets/script.js");