<?php if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die(); ?>

<div class="main_banner_wrapper">
    <?php foreach ($arResult["items"] as $item): ?>

        <?php if ($item["bcgrnd"]): ?>
        <a class="mb_full_banner_wrapper" href="<?= $item["link"] ?>">
            <img src="<?= $templateFolder ?><?= $item["bcgrnd"] ?>" alt="">
        </a>

        <?php else: ?>
            <a class="mb_banner_wrapper" href="<?= $item["link"] ?>" style="background: <?= $item["color"] ?>">
                <div class="mb_banner_img">
                    <img src="<?= $templateFolder ?><?= $item["img"] ?>" alt="">
                </div>
                <div class="mb_banner_title"><?= $item["title"] ?></div>
                <div class="mb_btn_box">
                    <div class="mb_banner_btn"><?= $item["btn_title"] ?></div>
                </div>
            </a>
        <?php endif; ?>
    <?php endforeach; ?>
</div>
