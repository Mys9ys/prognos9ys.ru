<?php
define("NEED_AUTH", true);
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");

$request = \Bitrix\Main\Application::getInstance()->getContext()->getRequest();
$uri = new \Bitrix\Main\Web\Uri($request->getRequestUri());

$arrUrl = explode('/', trim($uri->getPath(), "/"));

if($arrUrl[2]){
    $APPLICATION->IncludeComponent(
        "prognos9ys:kvn.game",
        "",
        ["id" => $arrUrl[2]],
        $component,
        []
    );
} else {
    $APPLICATION->IncludeComponent(
        "prognos9ys:kvn.event",
        "",
        [],
        $component,
        []
    );
}
?>

<?php

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");