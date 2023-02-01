<?php if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();?>

<?php if($_SERVER["HTTP_HOST"] === 'prog.work'):?>
<style>
    body{
        background: #fff;
    }
</style>
<div class="header_wrapper">
    <div class="h_main_block">
        <div class="hm_left_block">
            <div class="hm_btn_block">
                <div class="header_button header_btn_menu" title="Меню"><i class="fa fa-bars" aria-hidden="true"></i></div>
                <a class="header_button" href="/" title="Главная"><i class="fa fa-home" aria-hidden="true"></i></a>
                <a class="header_button" href="/p/matches/" title="Расписание"><i class="bi bi-menu-up"></i></a>
                <a class="header_button" href="/p/ratings/" title="Рейтинги"><i class="fa fa-list-ol" aria-hidden="true"></i></a>
            </div>
        </div>
        <div class="hm_right_block">
            <div class="hm_btn_block hm_right">
                <a class="header_button" href="/auth/" title="Страница авторизации"><i class="fa fa-sign-in" aria-hidden="true"></i></a>
            </div>
        </div>
    </div>
    <div class="header_menu_block">
        <ul class="dropdown-menu">
            <?php if($arResult) :?>
<!--                <li><a class="dropdown-item" href="/p/match/"><i class="bi bi-pencil-square"></i> Мои прогнозы</a></li>-->
                <li>
                    <div class="dropdown-item menu_events_btn" data-submenu="sub_prognos">
                        <i class="bi bi-pencil-square"></i> Прогнозы <i class="bi bi-caret bi-caret-right-fill"></i>
                    </div>
                </li>
                <div class="menu_events_box sub_prognos">
                    <li><hr class="dropdown-divider"></li>
                    <ul class="">
                        <li><a class="dropdown-item" href="/p/match/">КВН</a></li>
                        <li><a class="dropdown-item" href="/p/kvngame/">КВН</a></li>
                        <li><a class="dropdown-item"  href="/p/f1race/">Ф1</a></li>
                    </ul>
                    <li><hr class="dropdown-divider"></li>
                </div>
                <li><a class="dropdown-item" href="/p/profile/"><i class="bi bi-person-square"></i> Профиль</a></li>
                <li><hr class="dropdown-divider"></li>
            <?php else:?>
                <li><a class="dropdown-item" href="/auth/"><i class="fa fa-sign-in" aria-hidden="true"></i> Регистрация</a></li>
                <li><hr class="dropdown-divider"></li>
            <?php endif;?>

            <li>
                <div class="dropdown-item menu_events_btn" data-submenu="sub_events">
                    <i class="bi bi-menu-up"></i> События <i class="bi bi-caret bi-caret-right-fill"></i>
                </div>
            </li>
            <div class="menu_events_box sub_events">
            <li><hr class="dropdown-divider"></li>
                <ul class="">
                    <li><a class="dropdown-item" href="/p/kvngame/">КВН</a></li>
                    <li><a class="dropdown-item"  href="/p/f1race/">Ф1</a></li>
                </ul>
            <li><hr class="dropdown-divider"></li>
            </div>
            <?php if(CSite::InGroup (array(7))):?>
                <li><a class="dropdown-item" href="/p/admin/"><i class="fa fa-address-book" aria-hidden="true"></i> Админка</a></li>
            <?php endif;?>
            <li><a class="dropdown-item" href="/p/ratings/"><i class="fa fa-list-ol" aria-hidden="true"></i> Рейтинги</a></li>
            <li><a class="dropdown-item" href="/p/faq/"><i class="bi bi-patch-question"></i> Инструкции</a></li>
            <?php if($arResult) :?>
                <li><a class="dropdown-item" href="/p/logout/"><i class="bi bi-door-open"></i> Выйти</a></li>
            <?php endif;?>
        </ul>
    </div>
</div>
<?else :?>

<div class="header_wrapper">
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
                <a class="header_button" href="/" title="Главная"><i class="fa fa-home" aria-hidden="true"></i></a>
                <a class="header_button" href="/p/matches/" title="Расписание"><i class="bi bi-menu-up"></i></a>
                <a class="header_button" href="/p/ratings/" title="Рейтинги"><i class="fa fa-list-ol" aria-hidden="true"></i></a>

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
                <a class="header_button" href="/p/faq/" title="Инструкции"><i class="bi bi-patch-question"></i></a>
                <?php if($arResult) :?>
                <a class="header_button" href="/p/match/<?=$arResult["pr_link"]?>" title="Ваши прогнозы"><i class="bi bi-pencil-square"></i></a>
                <a class="header_button" href="/p/profile/" title="Ваш профиль"><i class="bi bi-person-square"></i></a>
                <a class="header_button" href="/p/logout/" title="Выйти"><i class="bi bi-door-open"></i></a>
                <?php else:?>
                <a class="header_button" href="/auth/" title="Страница авторизации"><i class="fa fa-sign-in" aria-hidden="true"></i></a>
                <?php endif;?>
            </div>

        </div>
    </div>
    <div class="header_menu_block">
        <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="/p/faq/"><i class="bi bi-patch-question"></i> Инструкции</a></li>
            <li><a class="dropdown-item" href="/p/matches/"><i class="bi bi-menu-up"></i> Матчи</a></li>
            <?php if(CSite::InGroup (array(7))):?>
                <li><a class="dropdown-item" href="/p/admin/"><i class="fa fa-address-book" aria-hidden="true"></i> Админка</a></li>
            <?php endif;?>
            <?php if($arResult) :?>
            <li><a class="dropdown-item" href="/p/match/"><i class="bi bi-pencil-square"></i> Мои прогнозы</a></li>
            <li><a class="dropdown-item" href="/p/profile/"><i class="bi bi-person-square"></i> Мой профиль</a></li>
            <?php else:?>
                <li><a class="dropdown-item" href="/auth/"><i class="fa fa-sign-in" aria-hidden="true"></i> Регистрация</a></li>
            <?php endif;?>
            <li><a class="dropdown-item" href="/p/ratings/"><i class="fa fa-list-ol" aria-hidden="true"></i> Рейтинги</a></li>
            <?php if($arResult) :?>
                <li><a class="dropdown-item" href="/p/logout/"><i class="bi bi-door-open"></i> Выйти</a></li>
            <?php endif;?>
        </ul>
    </div>
</div>

<?php endif;?>


<style>

</style>

