<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");

$APPLICATION->IncludeComponent(
    "prognos9ys:events.select",
    "",
    array(),
    $component,
    array()
);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");
