<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Рейтинги прогнозов на матчи Чемпионата мира по футболу в Катаре");
$APPLICATION->SetPageProperty("description","Рейтинг прогнозов на результаты событий футбольных матчей Чемпионата мира по футбола");

$APPLICATION->IncludeComponent(
    "prognos9ys:rating",
    "",
    array(),
    $component,
    array()
);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");