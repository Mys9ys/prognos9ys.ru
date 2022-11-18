<?php

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");


dump(CUser::IsAdmin());
$APPLICATION->IncludeComponent(
    "prognos9ys:admin",
    "",
    array(),
    $component,
    array()
);