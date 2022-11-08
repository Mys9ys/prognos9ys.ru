<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

use Bitrix\Main\Page\Asset;
Asset::getInstance()->addCss($templateFolder . "/additional/style.min.css");
Asset::getInstance()->addJs($templateFolder . "/additional/script.min.js");