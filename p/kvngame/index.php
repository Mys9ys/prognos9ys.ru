<?php
define("NEED_AUTH", true);
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");

$arrUrl = explode('/', trim($_SERVER['REQUEST_URI'], "/"));

if($arrUrl[2]){
    $APPLICATION->IncludeComponent(
        "prognos9ys:kvn.game",
        "",
        ["id" => $arrUrl[2]],
        $component,
        array()
    );
} else {
    $APPLICATION->IncludeComponent(
        "prognos9ys:kvn.event",
        "",
        [],
        $component,
        array()
    );
}
?>

<?php

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");