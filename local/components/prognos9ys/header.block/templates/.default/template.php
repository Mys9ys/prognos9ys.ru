<?php if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();?>

<div class="header_wrapper"
    <?php if($_SERVER["HTTP_HOST"] === 'prog.work') echo 'style="display: none"'?>
>
    <div class="h_main_block">
        <div class="hm_left_block">
            <div class="hm_achieve_block">
                <div class="hm_achieve_box hm_box">
                    <i class="bi bi-award"></i>
                    <i class="bi bi-award-fill"></i>
                </div>
                <div class="hm_rank_box hm_box">Новичок</div>
            </div>
            <div class="hm_btn_block">
                <a href="/p/matches/"><i class="bi bi-menu-up"></i></a>
                <a href="/p/match/"><i class="bi bi-pencil-square"></i></a>
            </div>
        </div>
        <div class="hm_ava_block">
            <img class="hm_ava_img" src="<?=$templateFolder?>/assets/img/ava.jpg" alt="">
        </div>
        <div class="hm_right_block">
            <div class="hm_nick_box hm_box">
                Неизвестный Нострадамус
            </div>
            <div class="hm_btn_block">
                <i class="bi bi-cup"></i>
                <i class="bi bi-door-open"></i>
            </div>
        </div>
    </div>
</div>
