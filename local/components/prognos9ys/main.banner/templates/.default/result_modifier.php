<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED!==true)die();

$arResult["items"]["faq"] =
    [
        "link" => "/p/faq/",
        "title" => 'Поможем во всем разобраться <i class="fa fa-long-arrow-right" aria-hidden="true"></i>',
        "btn_title" => '<i class="bi bi-patch-question"></i>',
        "img" => '/assets/img/faq.png',
        "color" => "#4c0bce;"
    ];

$arResult["items"]["qatar"] = [
    "link" => "/p/matches/",
    "bcgrnd" => '/assets/img/qatar.png'
];

$arResult["items"]["reg"] =
    [
        "link" => "/auth/",
        "title" => 'Приглашаем Вас зарегистрироваться на нашем сайте',
        "btn_title" => '<i class="fa fa-sign-in" aria-hidden="true"></i>',
        "img" => '/assets/img/reg.png',
        "color" => "#0d6efd;"
    ];