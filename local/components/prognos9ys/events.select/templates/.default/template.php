<?php if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();?>

<div class="events_wrapper">
    <h4>Доступные события для прогнозов</h4>
    <br>
    <?php foreach ($arResult["events"] as $event):?>
<!--        <div class="event_get_btn --><?//=$event['e_active']?><!--" style="--><?//=$event["PREVIEW_TEXT"]?><!--" data-user="--><?//=$event["user"]?><!--" data-event="--><?//=$event["ID"]?><!--">-->
<!--            <img src="--><?//=$event["img"]?><!--" alt="" class="event_get_img" title="--><?//=$event["NAME"]?><!--">-->
<!--        </div>-->

        <a class="event_get_btn" style="<?=$event["PREVIEW_TEXT"]?>" href="/p/<?=$event["link"]?>/">
            <img src="<?=$event["img"]?>" alt="" class="event_get_img" title="<?=$event["NAME"]?>">
        </a>
    <?php endforeach;?>
</div>





