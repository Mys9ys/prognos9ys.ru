<?
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/header.php');
$APPLICATION->SetTitle('Портал прогнозов на спортивные и игровые командные события');
?>
<?php

$APPLICATION->IncludeComponent(
    "prognos9ys:news.block",
    "",
    array(),
    $component,
    array()
);
?>

<?
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/footer.php');
?>