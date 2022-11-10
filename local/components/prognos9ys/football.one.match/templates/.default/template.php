<?php if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die(); ?>
<?php
//dump($arResult);
?>
<div class="one_match_wrapper">
    <div class="o_match_info">
        <div class="om_info_box o_date"><i class="bi bi-calendar3"></i> <?= $arResult["other"]["date"] ?></div>
        <div class="om_info_box o_time"><i class="bi bi-alarm"></i> <?= $arResult["other"]["time"] ?></div>
        <div class="om_info_box o_number">№ <?= $arResult["other"]["number"] ?></div>
        <div class="om_info_box o_group">Группа <?= $arResult["other"]["group"] ?></div>
    </div>
    <div class="o_match_box">
        <div class="o_team_block">
            <input type="hidden" class="m_pr_value" name="m_id" value="<?= $arResult["other"]["id"] ?>">
            <input type="hidden" class="m_pr_value" name="m_number" value="<?= $arResult["other"]["number"] ?>">
            <input type="hidden" class="m_pr_value" name="m_user" value="<?= CUser::GetID()?>">
            <div class="ot_title"></div>
            <div class="ot_team ot_home">
                <div class="ot_flag">
                    <img class="ot_flag_img" src="<?= $arResult["other"]["home"]["img"] ?>" alt="">
                </div>
                <div class="ot_title"><?= $arResult["other"]["home"]["NAME"] ?></div>
            </div>
            <div class="ot_team ot_guest">
                <div class="ot_flag">
                    <img class="ot_flag_img" src="<?= $arResult["other"]["guest"]["img"] ?>" alt="">
                </div>
                <div class="ot_title"><?= $arResult["other"]["guest"]["NAME"] ?></div>
            </div>
            <div class="ot_title"></div>
        </div>
        <div class="o_goals_block">
            <div class="ot_title"><i class="bi bi-arrow-down-circle"></i></div>
            <input type="text" class="og_goal og_goal_home m_pr_value" name="m_goal_home"
                   data-goal="home" value="<?= $arResult["main"]["home_goals"] ?>" placeholder="0">
            <input type="text" class="og_goal og_goal_guest m_pr_value" name="m_goal_guest"
                   data-goal="guest" value="<?= $arResult["main"]["guest_goals"] ?>" placeholder="0">
            <div class="ot_title"></div>
        </div>
        <div class="o_result_block">
            <div class="ot_title"><i class="bi bi-bullseye"></i></div>
            <input type="radio" name="m_result" class="or_radio or_home" value="п1" <?= $arResult["main"]["result"] === 'п1'? 'checked' : ''?>>
            <input type="radio" name="m_result" class="or_radio or_draw" value="н" <?= $arResult["main"]["result"] === 'н'? 'checked' : ''?>>
            <input type="radio" name="m_result" class="or_radio or_guest" value="п2" <?= $arResult["main"]["result"] === 'п2'? 'checked' : ''?>>
            <input type="hidden" name="m_result" class="or_radio m_pr_value" value="<?= $arResult["main"]["result"] ?>">
            <div class="ot_title"></div>
        </div>
        <div class="o_domination_block">
            <div class="ot_title"><i class="bi bi-percent" title="Владение"></i></div>
            <div class="o_domination_box">
                <input class="o_dom_i m_pr_value o_dom_h" type="text" value="<?= $arResult["main"]["domination"] ?>" name="m_domination" placeholder="50">
                <div class="o_domination_range_box">
                    <input class="o_domination_range" aria-orientation="vertical"
                           type="range" value="<?= $arResult["main"]["domination"] ?>" max="100" min="0" step="1">
                </div>
            </div>
            <input class="o_dom_i o_dom_g" type="text" value="<?= $arResult["main"]["domination2"] ?>" placeholder="50" disabled>
        </div>

        <div class="o_prof_stat o_count_goals_block">
            <div class="ot_title">sum</div>
            <input class="o_prof_input o_sum_i m_pr_value" type="text" value="<?= $arResult["main"]["sum"] ?>" name="m_sum"  placeholder="0">
            <input class="o_prof_input o_diff_i m_pr_value" type="text" value="<?= $arResult["main"]["diff"] ?>" name="m_diff" placeholder="0">
            <div class="ot_title">+/-</div>
        </div>

        <div class="o_prof_stat o_cards_block">
            <div class="ot_title oc_yellow"><i class="bi bi-file-fill"></i></div>
            <input class="o_prof_input o_yellow_i m_pr_value" type="text" value="<?= $arResult["main"]["yellow"] ?>" name="m_yellow"  placeholder="0">
            <input class="o_prof_input o_red_i m_pr_value" type="text" value="<?= $arResult["main"]["red"] ?>" name="m_red" placeholder="0">
            <div class="ot_title oc_red"><i class="bi bi-file-fill"></i></div>
        </div>

        <div class="o_prof_stat o_corners_block">
            <div class="ot_title"><i class="bi bi-flag"></i></div>
            <input class="o_prof_input o_corner_i m_pr_value" type="text" value="<?= $arResult["main"]["corner"] ?>" name="m_corner" placeholder="0">
            <input class="o_prof_input o_offside_i m_pr_value" type="text" value="<?= $arResult["main"]["offside"] ?>" name="m_offside" placeholder="0">
            <div class="ot_title">off</div>
        </div>

        <div class="o_prof_stat o_penalty_c_block">
            <div class="ot_title">pen</div>
            <input class="o_prof_input o_penalty_i m_pr_value" type="text" value="<?= $arResult["main"]["penalty"] ?>" name="m_penalty" placeholder="0">
        </div>

        <div class="o_btn_block">
            <div class="o_btn_temp o_btn_rand"><i class="bi bi-shuffle"></i></div>
            <div class="o_btn_temp o_btn_send_prognosis"><?= $arResult["main"]["home_goals"] ? 'Изменить': 'Отправить' ?></div>
        </div>

    </div>
    <?php if($arResult["other"]["number"]>1):?>
        <a class="btn_next_match" href="/p/match/<?= $arResult["other"]["id"]-1?>/">Предыдущий матч</a>
    <?php endif;?>
    <a class="btn_next_match" href="/p/match/<?= $arResult["other"]["id"]+1?>/">Следующий матч</a>
</div>



<i class="bi bi-ui-checks-grid"></i>
<i class="bi bi-table"></i>
<i class="bi bi-keyboard-fill"></i>
<i class="bi bi-keyboard"></i>

