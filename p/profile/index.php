<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");

$APPLICATION->IncludeComponent(
    "prognos9ys:profile",
    "",
    array(),
    $component,
    array()
);
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");