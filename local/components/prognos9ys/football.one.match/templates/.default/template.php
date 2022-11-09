<?php if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();?>

<div class="container">
    <div class="football_match_block" style="display: none">
        <div class="fmb_match_info">
            <div class="fmbmi fmb_date">20.11</div>
            <div class="fmbmi fmb_time">20:00</div>
            <div class="fmbmi fmb_number">1</div>
            <div class="fmbmi fmb_type">Групповой</div>
        </div>
        <div class="fmb_standard_block">
            <div class="fmb_flag_box">
                <div class="fmb_flag"><i class="bi bi-flag-fill"></i></div>
                <div class="fmb_flag"><i class="bi bi-flag-fill"></i></div>
            </div>

            <div class="fmb_title_box">
                <div class="fmb_title">fsdvr23</div>
                <div class="fmb_title">vdfas32</div>
            </div>
            <div class="fmb_goals_box">
                <div class="fmb_goals_wrap">
                    <input type="text" class="fmbt_goals home">
                    <div class="fmb_goals_btn_wrap">
                        <div class="fmbg_btn_math_box">
                            <div class="fmbg_btn_mod fmbg_btn_math fmbg_plus">+</div>
                            <div class="fmbg_btn_mod fmbg_btn_math fmbg_minus">-</div>
                            <div class="fmbg_btn_mod fmbg_btn_modal"><i class="bi bi-card-list"></i></div>
                        </div>

                    </div>
                </div>

                <input type="text" class="fmbt_goals guest">
            </div>

            <div class="fmb_result_box">
                <label for="" class="fmb_result_label">
                    <span>П1</span>
                    <input type="radio" name="fmb_result" class="fmb_result home">
                </label>
                <label for="" class="fmb_result_label">
                    <span>Н</span>
                    <input type="radio" name="fmb_result" class="fmb_result draw">
                </label>
                <label for="" class="fmb_result_label">
                    <span>П2</span>
                    <input type="radio" name="fmb_result" class="fmb_result guest">
                </label>
            </div>


<!--            <div class="fmb_team home">-->
<!--                <div class="fmbt fmbt_flag"><i class="bi bi-flag-fill"></i></div>-->
<!--                <div class="fmbt fmbt_title">fssfsfs</div>-->
<!--                <input type="text" class="fmbt fmbt_goals">-->
<!--                <input type="checkbox" class="fmbt result">-->
<!--            </div>-->
<!--            <div class="fmb_draw_line">-->
<!--                <div class="fmbd_empty"></div>-->
<!--                <div class="fmbd_dash">-</div>-->
<!--                <input type="checkbox" class="fmbd result">-->
<!--            </div>-->
<!--            <div class="fmb_team guest">-->
<!--                <div class="fmbt fmbt_flag"><i class="bi bi-flag-fill"></i></div>-->
<!--                <div class="fmbt fmbt_title">fsgfsdfs</div>-->
<!--                <input type="text" class="fmbt fmbt_goals">-->
<!--                <input type="checkbox" class="fmbt result">-->
<!--            </div>-->
        </div>
    </div>



</div>

<div class="one_match_wrapper">
    <div class="o_match_info">
        <div class="om_info_box o_date"><i class="bi bi-calendar3"></i> 20.11</div>
        <div class="om_info_box o_time"><i class="bi bi-alarm"></i> 20:00</div>
        <div class="om_info_box o_number">№ 1</div>
    </div>
    <div class="o_match_box">

        <div class="o_team_block">
            <div class="ot_title"></div>
            <div class="ot_team ot_home">
                <div class="ot_flag">
                    <img class="ot_flag_img" src="<?=$templateFolder?>/assets/img/8603222.png" alt="">
                </div>
                <div class="ot_title">Эквадор</div>
            </div>
            <div class="ot_team ot_guest">
                <div class="ot_flag"></div>
                <div class="ot_title"></div>
            </div>
        </div>
        <div class="o_goals_block">
            <div class="ot_title"><i class="bi bi-arrow-down-circle"></i></div>
            <input type="text" class="og_goal" value="" placeholder="0">
            <input type="text" class="og_goal" value="" placeholder="0">
        </div>
        <div class="o_result_block">
            <div class="ot_title"><i class="bi bi-bullseye"></i></div>
            <input type="radio" name="fmb_result" class="or_radio home">
            <input type="radio" name="fmb_result" class="or_radio draw">
            <input type="radio" name="fmb_result" class="or_radio guest">
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
    </div>
</div>

