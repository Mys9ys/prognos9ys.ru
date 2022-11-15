<?php if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();?>

<div class="header_wrapper"
    <?php if($_SERVER["HTTP_HOST"] === 'prog.work') echo 'style="display: none"'?>
>
    <div class="h_main_block">
        <div class="hm_left_block">
            <?php if($arResult) :?>
            <div class="hm_achieve_block">
                <div class="hm_achieve_box hm_box">
                    <i class="bi bi-award"></i>
                    <i class="bi bi-award-fill"></i>
                </div>
            </div>
            <?php endif;?>
            <div class="hm_btn_block">
                <div class="header_button header_btn_menu" title="Меню"><i class="fa fa-bars" aria-hidden="true"></i></div>
                <a class="header_button" href="/"><i class="fa fa-home" aria-hidden="true"></i></a>
                <a class="header_button" href="/p/matches/"><i class="bi bi-menu-up"></i></a>
                <a class="header_button" href="/p/ratings/"><i class="fa fa-list-ol" aria-hidden="true"></i></a>

            </div>
        </div>
        <?php if($arResult) :?>
        <div class="hm_ava_block">
            <img class="hm_ava_img" src="<?=$arResult["img"]?>" alt="">
        </div>
        <?php endif;?>
        <div class="hm_right_block">
            <?php if($arResult) :?>
            <div class="hm_nick_box hm_box">
                <?= $arResult["name"]?>
            </div>
            <?php endif;?>

            <div class="hm_btn_block hm_right">
                <a class="header_button" href="/p/faq/"><i class="bi bi-patch-question"></i></a>
                <?php if($arResult) :?>
                <a class="header_button" href="/p/match/"><i class="bi bi-pencil-square"></i></a>
                <a class="header_button" href="/p/profile/"><i class="bi bi-person-square"></i></a>
                <a class="header_button" href="/p/logout/"><i class="bi bi-door-open"></i></a>
                <?php else:?>
                <a class="header_button" href="/auth/"><i class="fa fa-sign-in" aria-hidden="true"></i></a>
                <?php endif;?>
            </div>

        </div>
    </div>
    <div class="header_menu_block">
        <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="/p/faq/"><i class="bi bi-patch-question"></i> Инструкции</a></li>
            <li><a class="dropdown-item" href="/p/matches/"><i class="bi bi-menu-up"></i> Матчи</a></li>
            <?php if($arResult) :?>
            <li><a class="dropdown-item" href="/p/match/"><i class="bi bi-pencil-square"></i> Мои прогнозы</a></li>
            <li><a class="dropdown-item" href="/p/profile/"><i class="bi bi-person-square"></i> Мой профиль</a></li>
            <?php else:?>
                <li><a class="dropdown-item" href="/auth/"><i class="bi bi-person-square"></i> Регистрация</a></li>
            <?php endif;?>
            <li><a class="dropdown-item" href="/p/ratings/"><i class="fa fa-list-ol" aria-hidden="true"></i> Рейтинги</a></li>
            <?php if($arResult) :?>
                <li><a class="dropdown-item" href="/p/logout/"><i class="bi bi-door-open"></i> Выйти</a></li>
            <?php endif;?>
        </ul>
    </div>

</div>
