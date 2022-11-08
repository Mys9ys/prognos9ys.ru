<? if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die(); ?>

<?php
//Sebekon\Tools::getInstance()->debug($arParams);
?>

<div class="action_element_block ">
    <? if ($arParams["title"]) { ?>
        <div class="action_element_title">Акции и предложения</div><? } ?>
    <!-- Slider main container -->
    <div class="swiper-container action_slider_swiper">
        <!-- Additional required wrapper -->
        <div class="swiper-wrapper action_slider_wrapper">
            <!-- Slides -->
            <? foreach ($arResult as $elem) { ?>
                <div class="swiper-slide action_slider_slide">
                    <a class="action_img_block" href="<?= $elem["url"] ?>">
                        <img class="action_img_class"
                             width="500"
                             height="317"
                             src="<?=$templateFolder?>/additional/template500.svg"
                             alt=""
                             data-desc="<?=$elem["desc_img"] ?>"
                             data-mob="<?=$elem["mob_img"]?>">
                    </a>
                    <div class="diana_click_btn">
                        <img src="<?=$templateFolder?>/img/diana_click_fill.png" alt="">
                    </div>
                </div>
            <?}?>
        </div>
        <div class="swiper-button-next">
            <i class="fa fa-arrow-right" aria-hidden="true"></i>
        </div>
        <div class="swiper-button-prev">
            <i class="fa fa-arrow-left" aria-hidden="true"></i>
        </div>
    </div>
</div>

