<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");

$APPLICATION->IncludeComponent(
    "prognos9ys:football.one.match",
    "",
    array(),
    $component,
    array()
);
