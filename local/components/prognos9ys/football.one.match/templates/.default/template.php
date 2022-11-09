<?php if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die(); ?>
<?php
?>
<div class="one_match_wrapper">
    <div class="o_match_info">
        <div class="om_info_box o_date"><i class="bi bi-calendar3"></i> <?= $arResult["date"] ?></div>
        <div class="om_info_box o_time"><i class="bi bi-alarm"></i> <?= $arResult["time"] ?></div>
        <div class="om_info_box o_number">№ 1</div>
    </div>
    <div class="o_match_box">
        <div class="o_team_block">
            <div class="ot_title"></div>
            <div class="ot_team ot_home">
                <div class="ot_flag">
                    <img class="ot_flag_img" src="<?= $arResult["home"]["img"] ?>" alt="">
                </div>
                <div class="ot_title"><?= $arResult["home"]["NAME"] ?></div>
            </div>
            <div class="ot_team ot_guest">
                <div class="ot_flag">
                    <img class="ot_flag_img" src="<?= $arResult["guest"]["img"] ?>" alt="">
                </div>
                <div class="ot_title"><?= $arResult["guest"]["NAME"] ?></div>
            </div>
        </div>
        <div class="o_goals_block">
            <div class="ot_title"><i class="bi bi-arrow-down-circle"></i></div>
            <input type="text" class="og_goal og_goal_home m_pr_value" value="" placeholder="0">
            <input type="text" class="og_goal og_goal_guest m_pr_value" value="" placeholder="0">
        </div>
        <div class="o_result_block">
            <div class="ot_title"><i class="bi bi-bullseye"></i></div>
            <input type="radio" name="or_result" class="or_radio home">
            <input type="radio" name="or_result" class="or_radio draw">
            <input type="radio" name="or_result" class="or_radio guest">
        </div>
        <div class="o_domination_block">
            <div class="ot_title"><i class="bi bi-percent" title="Владение"></i></div>
            <div class="o_domination_box">
                <input class="o_dom_i" type="text" value="" placeholder="50">
                <div class="o_domination_range_box">
                    <input class="o_domination_range" aria-orientation="vertical"
                           type="range" value="50" max="100" min="0" step="1">
                </div>
                <input class="o_dom_i" type="text" value="" placeholder="50">
            </div>
        </div>
        <div class="o_prof_stat o_corners_block">
            <div class="ot_title"><i class="bi bi-flag"></i></div>
            <input class="o_prof_input o_corner_box" type="text" value="" placeholder="0">
        </div>
        <div class="o_prof_stat o_yellow_c_block">
            <div class="ot_title"><i class="bi bi-file-fill"></i></div>
            <input class="o_prof_input o_yellow_c_box" type="text" value="" placeholder="0">
        </div>
        <div class="o_prof_stat o_red_c_block">
            <div class="ot_title"><i class="bi bi-file-fill"></i></div>
            <input class="o_prof_input o_red_c_box" type="text" value="" placeholder="0">
        </div>
        <div class="o_prof_stat o_penalty_c_block">
            <div class="ot_title"><i class="bi bi-grid-3x2-gap-fill"></i></div>
            <input class="o_prof_input o_penalty_c_box" type="text" value="" placeholder="0">
        </div>
        <div class="o_btn_block">
            <div class="o_btn_temp o_btn_rand"><i class="bi bi-shuffle"></i></div>
            <div class="o_btn_temp o_btn_send">Отправить</div>
        </div>

    </div>
</div>

<i class="bi bi-ui-checks-grid"></i>
<i class="bi bi-table"></i>
<i class="bi bi-keyboard-fill"></i>
<i class="bi bi-keyboard"></i>