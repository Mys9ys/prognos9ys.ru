<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

$path = $templateFolder . "/";;

use Bitrix\Main\Page\Asset;
Asset::getInstance()->addCss($path . "style.min.css");
Asset::getInstance()->addCss($path . "style_correct.css");

Asset::getInstance()->addJs( "/vendors/swiper/swiper-bundle.min.js");
Asset::getInstance()->addCss( "/vendors/swiper/swiper-bundle.min.css");

Asset::getInstance()->addJs( "/vendors/parallax-js/dist/parallax.min.js");
