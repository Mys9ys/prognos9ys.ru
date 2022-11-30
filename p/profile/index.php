<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");

$arrUrl = explode('/', trim($_SERVER['REQUEST_URI'], "/"));

if(count($arrUrl) === 2){
    $APPLICATION->IncludeComponent(
        "prognos9ys:profile",
        "",
        array(),
        $component,
        array()
    );
}
if(count($arrUrl) === 3){
    $APPLICATION->IncludeComponent(
        "prognos9ys:profile.users",
        "",
        ["id" => $arrUrl[2]],
        $component,
        array()
    );
}



require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");