<?
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die(); ?>
<!DOCTYPE html>
<html>
<head>
    <? $APPLICATION->ShowHead(); ?>
    <title><? $APPLICATION->ShowTitle(); ?></title>
    <link rel="shortcut icon" type="image/x-icon" href="/favicon.ico"/>
</head>
<body>
<div id="panel">
    <? $APPLICATION->ShowPanel(); ?>
</div>

<?php $APPLICATION->IncludeComponent(
    "prognos9ys:header.user",
    "",
    $component,
    false
);
?>

<div class="container">
    <p><?
        echo '<pre>';
        $userId = CUser::GetID();
        $arUser = CUser::GetByID($userId)->GetNext();

        //            var_dump(CUser::GetList());
        echo '</pre>';

        ?>
        pre
    </p>
<!--    <img src="--><?//= CFile::GetPath($arUser['PERSONAL_PHOTO']) ?><!--" alt="">-->
</div>


	
						