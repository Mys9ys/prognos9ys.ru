<?php if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();?>

<div class="main_banner_wrapper">
    <?php foreach ($arResult["items"] as $item):?>
    <a class="mb_banner_wrapper" href="<?=$item["link"]?>" style="background: <?=$item["color"]?>">
        <div class="mb_banner_img">
            <img src="<?=$templateFolder?><?=$item["img"]?>" alt="">
        </div>
        <div class="mb_banner_title"><?=$item["title"]?></div>
        <div class="mb_btn_box">
            <div class="mb_banner_btn"><?=$item["btn_title"]?></div>
        </div>
    </a>
    <?php endforeach;?>
</div>
