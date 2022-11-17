<?php if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die(); ?>

<div class="footer_block">
    <div class="footer_problem">
        Возникли проблемы по сайту - пиши:
        <div class="footer_btn_block">
            <a class="footer_social_btn" href="https://vk.com/tos9ys" target="_blank"><i class="fa fa-vk" aria-hidden="true"></i></a>
            <a class="footer_social_btn" href="https://t.me/mys9ys" target="_blank"><i class="fa fa-telegram" aria-hidden="true"></i></a>
        </div>
    </div>

    <?php $APPLICATION->IncludeComponent(
        "prognos9ys:tg_channel",
        "",
        array(),
        $component,
        array()
    ); ?>

</div>
