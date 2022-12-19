<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED!==true)die();

//$arResult["items"]["qatar"] = [
//    "link" => "/p/matches/",
//    "bcgrnd" => '/assets/img/qatar.png'
//];

//$arResult["items"]["ref"] = [
//    "link" => "/p/ref/",
//    "bcgrnd" => '/assets/img/ref.jpg'
//];

//$arResult["items"]["prize"] = [
//    "link" => "/p/prize/",
//    "bcgrnd" => '/assets/img/prize.jpg'
//];

$arResult["items"]["prize"] =
    [
        "link" => "/",
        "small_title" => 'Участникам, занявшим призовые места с вопросами по выплатам обращаться в соцсети, указанные ниже.',
        "btn_title" => '<i class="bi bi-telegram"></i>',
        "img" => '/assets/img/prize.png',
        "color" => "#8a1538;"
    ];


$arResult["items"]["final"] = [
    "link" => "/",
    "bcgrnd" => '/assets/img/final.jpg'
];


$arResult["items"]["faq"] =
    [
        "link" => "/p/faq/",
        "small_title" => 'Хотите делать прогнозы не вкладывая денег? Просто на интерес! Смотрите инструкцию <i class="fa fa-long-arrow-right" aria-hidden="true"></i>',
        "btn_title" => '<i class="bi bi-patch-question"></i>',
        "img" => '/assets/img/faq.png',
        "color" => "#4c0bce;"
    ];


$arResult["items"]["reg"] =
    [
        "link" => "/auth/",
        "title" => 'Приглашаем Вас зарегистрироваться на нашем сайте',
        "btn_title" => '<i class="fa fa-sign-in" aria-hidden="true"></i>',
        "img" => '/assets/img/reg.png',
        "color" => "#0d6efd;"
    ];