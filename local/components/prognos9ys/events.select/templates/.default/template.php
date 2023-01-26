<?php if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();?>

<div class="events_wrapper">
    <?php foreach ($arResult["events"] as $event):?>
        <div class="event_get_btn <?=$event['e_active']?>" style="<?=$event["PREVIEW_TEXT"]?>">
            <img src="<?=$event["img"]?>" alt="" class="event_get_img" title="<?=$event["NAME"]?>">
        </div>
    <?php endforeach;?>
</div>





