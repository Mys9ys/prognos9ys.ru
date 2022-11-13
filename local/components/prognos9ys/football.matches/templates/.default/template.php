<?php if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();?>
<div class="matches_wrapper">
    <?php $day = '';
    foreach ($arResult["teams"] as $id=>$item):?>
    <?php
    if($day !== $item["date"]):?>
        <div class="day_line_block">
            <div class="day_line_box">
                <div class="day_date m_template_box"><i class="bi bi-calendar4-event"></i> <?=$item["date"]?></div>
            </div>
        </div>
    <?endif;
        $day = $item["date"];
    ?>

    <div class="m_match_box">
        <div class="m_time m_template_box"><i class="bi bi-alarm"></i> <?=$item["time"]?></div>
        <div class="m_team_block m_template_box">
            <div class="m_home_team_box m_team_box">
                <div class="m_team_flag">
                    <img class="mt_flag_img" src="<?=$item["home"]["img"]?>" alt="">
                </div>
                <div class="m_team_title"><?=$item["home"]["NAME"]?></div>
            </div>
            <div class="m_separate">-</div>
            <div class="m_guest_team_box m_team_box">
                <div class="m_team_flag">
                    <img class="mt_flag_img" src="<?=$item["guest"]["img"]?>" alt="">
                </div>
                <div class="m_team_title"><?=$item["guest"]["NAME"]?></div>
            </div>
        </div>
        <div class="m_goals_box m_template_box">
            <div class="m_goals mg_home"><?=$item["home"]["goals"]?></div>
            <div class="m_separate">-</div>
            <div class="m_goals mg_guest"><?=$item["guest"]["goals"]?></div>
        </div>
        <div class="m_info_box m_template_box">
            <a class="m_btn_prognosis" href="/p/match/<?=$id?>/"><i class="bi bi-pencil-square"></i></a>
        </div>
    </div>
    <?php endforeach;?>
</div>

