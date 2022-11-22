<?php if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die(); ?>
<?php
//dump($arResult);
?>
<?php if($_SERVER["HTTP_HOST"] === 'prog.work'):?>
    <style>
        body{
            background: #fff;
        }
    </style>
<?php endif;?>
<?php if($arResult["other"]["id"]):?>
<div class="one_match_wrapper">

    <div class="pr_btn_next_block">
        <?php if($arResult["other"]["number"]>1):?>
            <a class="o_btn_next_match" href="/p/match/<?= $arResult["other"]["id"]-1?>/"><i class="fa fa-long-arrow-left" aria-hidden="true"></i> Предыдущий матч </a>
        <?php endif;?>
        <a class="o_btn_next_match" href="/p/match/<?= $arResult["other"]["id"]+1?>/">Следующий матч <i class="fa fa-long-arrow-right" aria-hidden="true"></i></a>
    </div>

    <?php if($arResult["other"]["active"] === "N" && !$USER->IsAdmin()):?>
        <div class="om_table_wrapper">
            <table class="table table-dark table-hover">
                <thead>
                <tr>
                    <th scope="col">#</th>
                    <th scope="col">Счет</th>
                    <th scope="col"><i class="fa fa-trophy" aria-hidden="true"></i></th>
                    <th scope="col">sum</th>
                    <th scope="col">+/-</th>
                    <th scope="col">%</th>
                    <th scope="col"><div class="ot_title oc_yellow"><i class="bi bi-file-fill"></i></div></th>
                    <th scope="col"><div class="ot_title oc_red"><i class="bi bi-file-fill"></i></div></th>
                    <th scope="col"><i class="bi bi-flag"></i></th>
                    <th scope="col">pen</th>
                </tr>
                </thead>
                <tbody>

                <tr>
                    <th>Результат</th>
                    <td><?=$item['nick']?></td>
                    <td><?=$item['score']?></td>
                </tr>
                <tr>
                    <th>Ставка</th>
                    <td><?=$arResult["main"]["home_goals"]?> - <?=$arResult["main"]["guest_goals"]?></td>
                    <td><?= $arResult["main"]["result"]?></td>
                    <td><?= $arResult["main"]["sum"]?></td>
                    <td><?= $arResult["main"]["diff"]?></td>
                    <td><?= $arResult["main"]["domination"] ?> - <?=100- $arResult["main"]["domination"] ?></td>
                    <td><?= $arResult["main"]["yellow"]?></td>
                    <td><?= $arResult["main"]["red"]?></td>
                    <td><?= $arResult["main"]["corner"]?></td>
                    <td><?= $arResult["main"]["penalty"]?></td>
                </tr>

                <tr>
                    <th>Баллы</th>
                    <td><?=$item['nick']?></td>
                    <td><?=$item['score']?></td>
                </tr>

                </tbody>
            </table>
        </div>
    <?php else:?>
        <div class="o_match_info" <?php if($_SERVER["HTTP_HOST"] === 'prog.work') echo 'style="display: none"'?>>
            <div class="om_info_box o_date"><i class="bi bi-calendar3"></i> <?= $arResult["other"]["date"] ?></div>
            <div class="om_info_box o_time"><i class="bi bi-alarm"></i> <?= $arResult["other"]["time"] ?></div>
            <div class="om_info_box o_number">№ <?= $arResult["other"]["number"] ?></div>
            <div class="om_info_box o_group">Группа <?= $arResult["other"]["group"] ?></div>
        </div>
        <div class="o_match_box" >
            <div class="o_team_block">
                <input type="hidden" class="m_pr_value" name="m_stage" value="<?= $arResult["other"]["stage"] ?>">
                <input type="hidden" class="m_pr_value" name="m_group" value="<?= $arResult["other"]["group_id"] ?>">
                <input type="hidden" class="m_pr_value" name="m_team_home" value="<?= $arResult["other"]["home"]["ID"] ?>">
                <input type="hidden" class="m_pr_value" name="m_team_guest" value="<?= $arResult["other"]["guest"]["ID"] ?>">
                <input type="hidden" class="m_pr_value" name="m_id" value="<?= $arResult["other"]["id"] ?>">
                <input type="hidden" class="m_pr_value" name="m_number" value="<?= $arResult["other"]["number"] ?>">
                <input type="hidden" class="m_pr_value" name="m_user" value="<?= CUser::GetID()?>">
                <input type="hidden" class="m_pr_value" name="m_event" value="34">
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
                <div class="ot_title">
                    <div class="ot_title_date">
                        <?php if($arResult["main"]["rewrite"]):?> Изменен: <?=$arResult["main"]["rewrite"]?>
                        <?php endif;?>
                    </div>
                </div>
            </div>
            <div class="o_goals_block">
                <div class="ot_title"><i class="fa fa-futbol-o" aria-hidden="true"></i></div>
                <input type="text" class="og_goal og_goal_home m_pr_value goal_home" name="m_goal_home"
                       data-goal="home" value="<?= $arResult["main"]["home_goals"] ?? 0 ?>">
                <input type="text" class="og_goal og_goal_guest m_pr_value goal_guest" name="m_goal_guest"
                       data-goal="guest" value="<?= $arResult["main"]["guest_goals"] ?? 0 ?>">
                <div class="ot_title"></div>
            </div>
            <div class="o_result_block">
                <div class="ot_title"><i class="fa fa-trophy" aria-hidden="true"></i></div>
                <input type="radio" id="r_p1" name="m_result" class="or_radio or_home" value="п1" <?= $arResult["main"]["result"] === 'п1'? 'checked' : ''?>>
                <label for="r_p1">п1</label>
                <input type="radio" id="r_d" name="m_result" class="or_radio or_draw" value="н" <?= $arResult["main"]["result"] === 'н'? 'checked' : ''?>>
                <label for="r_d">н</label>
                <input type="radio" id="r_p2" name="m_result" class="or_radio or_guest" value="п2" <?= $arResult["main"]["result"] === 'п2'? 'checked' : ''?>>
                <label for="r_p2">п2</label>
                <input type="hidden" name="m_result" class="or_radio or_radio_res m_pr_value" value="<?= $arResult["main"]["result"] ?? 'н'?>">
                <div class="ot_title"></div>
            </div>

            <div class="o_prof_stat o_count_goals_block">
                <div class="ot_title">sum</div>
                <input class="o_prof_input o_sum_i m_pr_value" type="text" value="<?= $arResult["main"]["sum"] ?? 0 ?>" name="m_sum">
                <input class="o_prof_input o_diff_i m_pr_value" type="text" value="<?= $arResult["main"]["diff"] ?? 0?>" name="m_diff">
                <div class="ot_title">+/-</div>
            </div>

            <div class="o_domination_block">
                <div class="ot_title"><i class="bi bi-percent" title="Владение"></i></div>
                <div class="o_domination_box">
                    <input class="o_dom_i m_pr_value o_dom_h dom_home" type="text"
                           value="<?= $arResult["main"]["domination"] ?>" name="m_domination" placeholder="50" maxlength="2">
                    <div class="o_domination_range_box">
                        <input class="o_domination_range domination_range" aria-orientation="vertical" disabled
                               type="range" value="<?= $arResult["main"]["domination"] ?>" max="100" min="0" step="1">
                    </div>
                </div>
                <input class="o_dom_i o_dom_g dom_guest" type="text" value="<?= $arResult["main"]["domination2"] ?>" placeholder="50" disabled>
            </div>



            <div class="o_prof_stat o_cards_block">
                <div class="ot_title oc_yellow"><i class="bi bi-file-fill"></i></div>
                <input class="o_prof_input o_yellow_i c_yellow m_pr_value" type="text" value="<?= $arResult["main"]["yellow"] ?>" name="m_yellow" placeholder="-">
                <input class="o_prof_input o_red_i c_red m_pr_value" type="text" value="<?= $arResult["main"]["red"] ?>" name="m_red" placeholder="-">
                <div class="ot_title oc_red"><i class="bi bi-file-fill"></i></div>
            </div>

            <div class="o_prof_stat o_corners_block">
                <div class="ot_title"><i class="bi bi-flag"></i></div>
                <input class="o_prof_input o_corner_i m_pr_value" type="text" value="<?= $arResult["main"]["corner"] ?>" name="m_corner" placeholder="-">
                <input class="o_prof_input o_penalty_i m_pr_value" type="text" value="<?= $arResult["main"]["penalty"] ?>" name="m_penalty" placeholder="-" maxlength="1">
                <div class="ot_title">pen</div>
            </div>

            <!--        <div class="o_btn_block">-->
            <!--            <div class="o_btn_temp o_btn_rand"><i class="fa fa-random" aria-hidden="true"></i></div>-->
            <!--            <div class="o_btn_temp o_btn_send_prognosis">--><?//= $arResult["main"]["home_goals"] ? 'Изменить': 'Отправить' ?><!--</div>-->
            <!--        </div>-->

        </div>

        <div class="prognosis_window_wrapper">
            <div class="pw_teams_block">
                <div class="pw_teams_title">Команды</div>
                <div class="pw_teams_box">
                    <div class="pw_team_home pw_team">
                        <div class="pw_team_type">Ком 1</div>
                        <div class="pw_team_flag">
                            <img class="pw_team_flag_img" src="<?= $arResult["other"]["home"]["img"] ?>" alt="">
                        </div>
                        <div class="pw_team_name"><?= $arResult["other"]["home"]["NAME"] ?></div>
                    </div>
                    <div class="pw_team_guest pw_team">
                        <div class="pw_team_name"><?= $arResult["other"]["guest"]["NAME"] ?></div>
                        <div class="pw_team_flag">
                            <img class="pw_team_flag_img" src="<?= $arResult["other"]["guest"]["img"] ?>" alt="">
                        </div>
                        <div class="pw_team_type">Ком 2</div>
                    </div>
                </div>
            </div>
            <div class="pw_goals_block">
                <div class="pw_goals_block_title">Счет: </div>
                <div class="pw_goals_popular">
                    <div class="pw_goals_popular_title">Популярный счет: </div>
                    <div class="pw_goals_popular_value">
                        <?php foreach ($arResult["btn"]["goals"]["score"] as $item):?>
                            <div class="pw_goals_popular_score" data-cell="<?=$item["cell"]?>" ><?=$item["name"]?></div>
                        <?php endforeach;?>
                    </div>
                </div>
                <div class="pw_goals_btn_box">
                    <div class="pw_goals_btn_home pw_goals_btn_ink">
                        <?php foreach ($arResult["btn"]["goals"]["inc_home"] as $item):?>
                            <div class="pw_goals_btn" data-cell="<?=$item["cell"]?>" ><?=$item["name"]?></div>
                        <?php endforeach;?>
                    </div>

                    <div class="pw_goals_input_box">
                        <input type="text" class="pw_goals_i goal_home" placeholder="0"
                               value="<?= $arResult["main"]["home_goals"] ?>" disabled>
                        <input type="text" class="pw_goals_i goal_guest" placeholder="0"
                               value="<?= $arResult["main"]["guest_goals"] ?>" disabled>
                    </div>

                    <div class="pw_goals_btn_guest pw_goals_btn_ink">
                        <?php foreach ($arResult["btn"]["goals"]["inc_guest"] as $item):?>
                            <div class="pw_goals_btn" data-cell="<?=$item["cell"]?>" ><?=$item["name"]?></div>
                        <?php endforeach;?>
                    </div>

                </div>
            </div>
            <div class="pw_domination_block">
                <div class="pw_domination_title">Процент владения:</div>
                <div class="pw_domination_range_block">
                    <input type="text" class="pw_dom_i pw_dom_i_home dom_home" placeholder="0"
                           value="<?= $arResult["main"]["domination"] ?>" disabled>
                    <div class="pw_domination_range_box">
                        <input class="pw_domination_range domination_range" aria-orientation="vertical"
                               type="range" value="<?= $arResult["main"]["domination"] ?>" max="100" min="0" step="1">
                    </div>
                    <input type="text" class="pw_dom_i pw_dom_i_guest dom_guest" placeholder="0"
                           value="<?= $arResult["main"]["domination2"] ?>" disabled>
                </div>
                <div class="pw_domination_btn_block">
                    <div class="pw_domination_btn_box pwd_home">
                        <?php foreach ($arResult["btn"]["dom"]["home"] as $item):?>
                            <div class="pw_dom_btn" data-cell="<?=$item["cell"]?>" ><?=$item["name"]?></div>
                        <?php endforeach;?>
                    </div>
                    <div class="pw_domination_btn_box pwd_guest">
                        <?php foreach ($arResult["btn"]["dom"]["guest"] as $item):?>
                            <div class="pw_dom_btn" data-cell="<?=$item["cell"]?>" ><?=$item["name"]?></div>
                        <?php endforeach;?>
                    </div>
                </div>
            </div>
            <div class="pw_card_block">
                <div class="pw_card_title">Карточки:</div>
                <div class="pw_card_box">
                    <div class="pw_card_box_color pw_card_box_yellow">
                        <div class="pw_card_btn_box pwc_btn_box_yellow">
                            <?php foreach ($arResult["btn"]["cards"]["yellow"] as $item):?>
                                <div class="pw_card_btn" data-cell="<?=$item["cell"]?>" ><?=$item["name"]?></div>
                            <?php endforeach;?>
                        </div>
                        <input class="pw_card_input c_yellow" type="text" value="<?= $arResult["main"]["yellow"] ?>" disabled
                               placeholder="-">
                    </div>
                    <div class="pw_card_box_color">
                        <input class="pw_card_input c_red" type="text" value="<?= $arResult["main"]["red"] ?>" disabled
                               placeholder="-">
                        <div class="pw_card_btn_box pwc_btn_box_red">
                            <?php foreach ($arResult["btn"]["cards"]["red"] as $item):?>
                                <div class="pw_card_btn" data-cell="<?=$item["cell"]?>" ><?=$item["name"]?></div>
                            <?php endforeach;?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="pw_other_block">
                <div class="pw_other_box pw_corner_block">
                    <div class="pw_other_title pw_corner_title">Угловые:</div>
                    <div class="pw_other_box_wrapper pw_corner_box">
                        <div class="pw_other_btn_box pw_corner_btn_box">
                            <?php foreach ($arResult["btn"]["corner"] as $item):?>
                                <div class="pw_corner_btn" data-cell="<?=$item["cell"]?>" ><?=$item["name"]?></div>
                            <?php endforeach;?>
                        </div>
                        <input class="pw_other_input o_corner_i" type="text" value="<?= $arResult["main"]["corner"] ?>" disabled
                               placeholder="-">
                    </div>
                </div>
                <div class="pw_other_box pw_penalty_block">
                    <div class="pw_other_title pw_penalty_title">Пенальти:</div>
                    <div class="pw_other_box_wrapper pw_penalty_box">
                        <input class="pw_other_input o_penalty_i" type="text" value="<?= $arResult["main"]["penalty"] ?>" disabled
                               placeholder="-">
                        <div class="pw_other_btn_box pw_penalty_btn_box">
                            <?php foreach ($arResult["btn"]["penalty"] as $item):?>
                                <div class="pw_penalty_btn" data-cell="<?=$item["cell"]?>" ><?=$item["name"]?></div>
                            <?php endforeach;?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="pw_btn_block ">
                <?php if($USER->IsAdmin()):?>
                    <div class="o_btn_temp o_btn_rand o_admin_calc" data-id="<?= $arResult["other"]["id"] ?>">Расчитать </div>
                <?php else:?>
                    <div class="o_btn_temp o_btn_rand">Для красоты</div>
                <?php endif;?>

                <?if($arResult["other"]["active"] === 'Y'):?>
                    <div class="o_btn_temp o_btn_send_prognosis"><?= $arResult["main"]["home_goals"] ? 'Изменить': 'Отправить' ?>
                        <br> прогноз</div>
                <?else:?>
                    <div class="text-info">Изменение не возможно</div>
                <?endif;?>
            </div>

            <?php if($USER->IsAdmin()):?>
                <div class="set_match_result text-justify o_btn_temp mt-2 mb-2">Заполнить официальный результат</div>
            <?php endif;?>

        </div>
    <?php endif;?>



    <div class="pr_btn_next_block">
        <?php if($arResult["other"]["number"]>1):?>
            <a class="o_btn_next_match" href="/p/match/<?= $arResult["other"]["id"]-1?>/"><i class="fa fa-long-arrow-left" aria-hidden="true"></i> Предыдущий матч </a>
        <?php endif;?>
        <a class="o_btn_next_match" href="/p/match/<?= $arResult["other"]["id"]+1?>/">Следующий матч <i class="fa fa-long-arrow-right" aria-hidden="true"></i></a>
    </div>

</div>


<!--<i class="fa fa-keyboard-o" aria-hidden="true"></i>-->


<?php else:?>
    <div class="one_match_wrapper">
        <div class="o_btn_next_match">Тут ни чего нет</div>
    </div>
<?php endif;?>


<div class="prog_send_modal modal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body" style="text-align: center">
                <i class="fa fa-spinner fa-4x fa-spin" aria-hidden="true"></i>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
            </div>
        </div>
    </div>
</div>
