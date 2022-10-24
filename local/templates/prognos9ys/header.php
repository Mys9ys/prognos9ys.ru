<?
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die(); ?>
<!DOCTYPE html>
<html>
<head>
    <title><? $APPLICATION->ShowTitle(); ?></title>
    <link rel="shortcut icon" type="image/x-icon" href="/favicon.ico"/>
    <?
    use Bitrix\Main\Page\Asset;

    $assetManager = Asset::getInstance();

    //Стили
    $assetManager->addCss( "/vendor/twbs/bootstrap/dist/css/bootstrap.min.css");

    //Скрипты
    $assetManager->addJs( "/vendor/twbs/bootstrap/dist/js/bootstrap.min.js");
    ?>

    <? $APPLICATION->ShowHead(); ?>

</head>
<body>
<div id="panel">
    <? $APPLICATION->ShowPanel(); ?>
</div>
<!--<div class="container">-->
<!--    <h1>rew</h1>-->
<!--</div>-->


<?php $APPLICATION->IncludeComponent(
    "prognos9ys:header.block",
    "",
    array(),
    $component,
    array()
); ?>







	
						