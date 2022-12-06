<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Расписание матчей ЧМ22 в Катаре");
$APPLICATION->SetPageProperty("description","Прогнозы на футбольные матчи без финансовых вливаний - расписание матчей");
$APPLICATION->IncludeComponent(
    "prognos9ys:football.matches",
    "",
    array(),
    $component,
    array()
);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");