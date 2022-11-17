<?php
define("NEED_AUTH", true);
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");

$arrUrl = explode('/', trim($_SERVER['REQUEST_URI'], "/"));

$APPLICATION->IncludeComponent(
    "prognos9ys:football.one.match",
    "",
    ["id" => $arrUrl[2]],
    $component,
    array()
);
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");