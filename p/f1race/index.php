<?php
define("NEED_AUTH", true);
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");

$arrUrl = explode('/', trim($_SERVER['REQUEST_URI'], "/"));

if($arrUrl[2]){
    $APPLICATION->IncludeComponent(
        "prognos9ys:f1.race",
        "",
        ["id" => $arrUrl[2]],
        $component,
        []
    );
} else {
    $APPLICATION->IncludeComponent(
        "prognos9ys:f1.event",
        "",
        [],
        $component,
        []
    );
}

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");