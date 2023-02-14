<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");

$APPLICATION->IncludeComponent(
    "prognos9ys:football.matches",
    "",
    array(),
    $component,
    array()
);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");