<?
define("NEED_AUTH", true);
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/header.php');
$APPLICATION->SetTitle('Главная');
?>
   <?php

$APPLICATION->IncludeComponent(
    "prognos9ys:main.banner",
    "",
    array(),
    $component,
    array()
);?>
<?
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/footer.php');
?>