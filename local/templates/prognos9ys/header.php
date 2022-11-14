<?
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die(); ?>
<!DOCTYPE html>
<html>
<head>
    <title><? $APPLICATION->ShowTitle(); ?></title>
    <link rel="shortcut icon" type="image/x-icon" href="/favicon.ico"/>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <?
    use Bitrix\Main\Page\Asset;

    $assetManager = Asset::getInstance();

    //Стили
    $assetManager->addCss( "/vendor/twbs/bootstrap/dist/css/bootstrap.min.css");
    $assetManager->addCss( "/vendor/twbs/bootstrap-icons/font/bootstrap-icons.css");
    $assetManager->addCss( "/local/templates/prognos9ys/assets/font-awesome-4.7.0/css/font-awesome.min.css");

    //Скрипты
    $assetManager->addJs( "/vendor/twbs/bootstrap/dist/js/bootstrap.min.js");
    $assetManager->addJs( "/vendor/components/jquery/jquery.min.js");
    ?>

    <? $APPLICATION->ShowHead(); ?>

    <!-- Yandex.Metrika counter -->
    <script type="text/javascript" >
        (function(m,e,t,r,i,k,a){m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};
            m[i].l=1*new Date();
            for (var j = 0; j < document.scripts.length; j++) {if (document.scripts[j].src === r) { return; }}
            k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)})
        (window, document, "script", "https://mc.yandex.ru/metrika/tag.js", "ym");

        ym(91163752, "init", {
            clickmap:true,
            trackLinks:true,
            accurateTrackBounce:true
        });
    </script>
    <noscript><div><img src="https://mc.yandex.ru/watch/91163752" style="position:absolute; left:-9999px;" alt="" /></div></noscript>
    <!-- /Yandex.Metrika counter -->

</head>
<body>
<div id="panel">
    <? $APPLICATION->ShowPanel(); ?>
</div>

<?php $APPLICATION->IncludeComponent(
    "prognos9ys:header.block",
    "",
    array(),
    $component,
    array()
); ?>







	
						