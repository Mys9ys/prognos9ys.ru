<?php if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die(); ?>

<div class="profile_wrapper">
 <h2><?=$arResult["name"]?></h2>
    <div class="ref_wrapper">
        <div class="ref_box">
            <div>Приглашение:</div>
        </div>
        <div class="ref_box">
            <?php if($arResult['ref_nik']):?>
                Вас пригласил: <?=$arResult['ref_nik']?>
            <?php else:?>
                Вы сами зашли
            <?php endif;?>
        </div>
        <div class="ref_box">
            <div>
                Вы тоже можете пригласить друзей и побороться за приз - 3000 руб.
            </div>
            <div>
                Ваша реферальная ссылка:
            </div>
            <input class="ref_link_box" type="text" value="<?=$arResult['ref_link']?>">
        </div>

        <div class="ref_box">
            <span>Приглашено: <?=$arResult["you_ref"]["count"] ?: 0?></span>
            <span>Из них активны: <?=$arResult["you_ref"]["active"] ?: 0?></span>
        </div>
    </div>


</div>
