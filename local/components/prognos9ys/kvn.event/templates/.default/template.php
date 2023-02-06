<?php if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die(); ?>

<div class="kvn_event_wrapper">
    <div class="kvn_game_box">
        <div class="kvn_game_title_block">
            <div class="kgtb_date kgtb_cell">
                <i class="bi bi-calendar4-event"></i> 14.02
            </div>
            <div class="kgtb_time kgtb_cell">
                <i class="bi bi-alarm"></i> 18:30
            </div>
            <div class="kgtb_number kgtb_cell">
                # 1
            </div>
            <div class="kgtb_name kgtb_cell">
                <i class="bi bi-joystick"></i> Первая 1/8
            </div>
            <div class="kgtb_score kgtb_cell">
                <i class="fa fa-sticky-note" aria-hidden="true"></i> 9.5
            </div>
        </div>
        <div class="accordion kg_description_block" id="accordionExample">
            <div class="accordion-item">
                <h6 class="accordion-header" id="headingOne">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                            data-bs-target="#collapseOne"
                            aria-expanded="false" aria-controls="collapseOne">Подробнее
                    </button>
                </h6>
                <div id="collapseOne" class="accordion-collapse collapse" aria-labelledby="headingOne"
                     data-bs-parent="#accordionExample">
                    <div class="accordion-body">
                        <table class="table table-striped-columns kvn_table">
                            <thead>
                            <tr>
                                <th scope="col">Teams</th>
                                <th scope="col">stage 1</th>
                                <th scope="col">stage 2</th>
                                <th scope="col">stage 3</th>
                                <th scope="col">result</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php $teams = [
                                '1 team',
                                '2 team',
                                '3 team',
                                '4 team',
                                '5 team',
                            ];
                            $s1 = $s2 = $s3 = $r = array_fill(0, 5, 0);
                            $count = 0;
                            foreach ($teams as $team):
                                ?>
                                <tr>
                                    <td><?= $team ?></td>
                                    <td><?= $s1[$count] ?></td>
                                    <td><?= $s2[$count] ?></td>
                                    <td><?= $s3[$count] ?></td>
                                    <td><?= $r[$count] ?></td>
                                </tr>
                                <?php $count++;
                            endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="kgtb_btn_box">
            <div class="kgtb_status">не заполнено</div>
            <div class="kgtb_write">Заполнить</div>
        </div>
    </div>
</div>
<br>
<br>
<br>
<br>
<br>
<br>
<br>
<br>
<br>
<br>
<br>
<br>
<br>
<br>
<br>
<br>
<br>
<br>
<br>
<br>
<br>
<br>
<br>
<br>
<br>
<br>
<?php
$fillCl = new FillKVNGameList();
if ($arResult['event_active']):
    $event = $arResult['event_active'] ?>
    <div class="matches_wrapper">
        <div class="matches_title" style="<?= $event["PREVIEW_TEXT"] ?>">
            <div class="matches_logo" style="<?= $event["PREVIEW_TEXT"] ?>">
                <img class="matches_logo_img" src="<?= $event["img"] ?>" alt="">
            </div>
            <div class="matches_text">
                <?= $event["DETAIL_TEXT"] ?>
            </div>
        </div>
        <?php $day = ''; ?>

        <div class="accordion" id="accordionExample">
            <div class="accordion-item">
                <h6 class="accordion-header" id="headingOne">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                            data-bs-target="#collapseOne"
                            aria-expanded="false" aria-controls="collapseOne">
                        Прошедшие <span class="badge_pr badge"><?= $arResult["not_active_count"] ?></span>
                    </button>
                </h6>
                <div id="collapseOne" class="accordion-collapse collapse" aria-labelledby="headingOne"
                     data-bs-parent="#accordionExample">
                    <div class="accordion-body">
                        <? foreach ($arResult["teams"] as $id => $item):?>
                            <?php if ($item["active"] === 'Y') continue; ?>
                            <?php
                            if ($day !== $item["date"]):?>
                                <div class="day_line_block">
                                    <div class="day_line_box">
                                        <div class="day_date m_template_box"><i
                                                    class="bi bi-calendar4-event"></i> <?= $item["date"] ?></div>
                                    </div>
                                </div>
                            <?endif;
                            $day = $item["date"];
                            ?>
                            <div class="m_match_wrapper">
                                <div class="m_match_box">
                                    <div class="m_number">#<?= $item["number"] ?></div>
                                    <div class="m_time m_template_box"><i class="bi bi-alarm"></i> <?= $item["time"] ?>
                                    </div>
                                    <div class="m_team_block m_template_box">
                                        <div class="m_home_team_box m_team_box">
                                            <div class="m_team_flag">
                                                <img class="mt_flag_img" src="<?= $item["home"]["img"] ?>" alt="">
                                            </div>
                                            <div class="m_team_title"><?= $item["home"]["NAME"] ?></div>
                                        </div>
                                        <div class="m_separate">-</div>
                                        <div class="m_guest_team_box m_team_box">
                                            <div class="m_team_flag">
                                                <img class="mt_flag_img" src="<?= $item["guest"]["img"] ?>" alt="">
                                            </div>
                                            <div class="m_team_title"><?= $item["guest"]["NAME"] ?></div>
                                        </div>
                                    </div>
                                    <div class="m_goals_box m_template_box">
                                        <div class="m_goals mg_home"><?= $item["home"]["goals"] ?></div>
                                        <div class="m_separate">-</div>
                                        <div class="m_goals mg_guest"><?= $item["guest"]["goals"] ?></div>
                                    </div>
                                </div>
                                <div class="match_all_info_block">
                                    <div class="match_user_info">
                                        <?php if ($item["write"]): ?>
                                            <span class="text-success">Вы заполнили: <?= $item["write"] ?></span>
                                        <?php else: ?>
                                            <span class="text-info">Не заполнено</span>
                                        <?php endif; ?>
                                    </div>
                                    <a class="match_write_btn" href="/p/kvngame/<?= $item["number"] ?>/">
                                        <?php if ($item["active"] === 'Y'): ?>
                                            <?php if ($item["write"]): ?>
                                                Изменить <i class="bi bi-pencil-square"></i>
                                            <?php else: ?>
                                                Заполнить <i class="bi bi-pencil-square"></i>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            Проверить </i>
                                        <?php endif; ?>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="accordion-item">
                <h6 class="accordion-header" id="headingTwo">
                    <button class="accordion-button" type="button" data-bs-toggle="collapse"
                            data-bs-target="#collapseTwo" aria-expanded="true" aria-controls="collapseTwo">
                        Текущие <span class="badge_pr badge"><?= $arResult["active_count"] ?></span>
                    </button>
                </h6>
                <div id="collapseTwo" class="accordion-collapse collapse show" aria-labelledby="headingTwo"
                     data-bs-parent="#accordionExample">
                    <div class="accordion-body">
                        <? foreach ($arResult["teams"] as $id => $item):?>
                            <?php if ($item["active"] === 'N') continue; ?>
                            <?=
                            $fillCl->fillDataBox($item["date"]);
                            ?>
                            <div class="m_match_wrapper">
                                <div class="m_match_box">
                                    <div class="m_number">#<?= $item["number"] ?></div>
                                    <div class="m_time m_template_box"><i class="bi bi-alarm"></i> <?= $item["time"] ?>
                                    </div>
                                    <div class="m_team_block m_template_box">
                                        <div class="m_home_team_box m_team_box">
                                            <div class="m_team_flag">
                                                <img class="mt_flag_img" src="<?= $item["home"]["img"] ?>" alt="">
                                            </div>
                                            <div class="m_team_title"><?= $item["home"]["NAME"] ?></div>
                                        </div>
                                        <div class="m_separate">-</div>
                                        <div class="m_guest_team_box m_team_box">
                                            <div class="m_team_flag">
                                                <img class="mt_flag_img" src="<?= $item["guest"]["img"] ?>" alt="">
                                            </div>
                                            <div class="m_team_title"><?= $item["guest"]["NAME"] ?></div>
                                        </div>
                                    </div>
                                    <div class="m_goals_box m_template_box">
                                        <div class="m_goals mg_home"><?= $item["home"]["goals"] ?></div>
                                        <div class="m_separate">-</div>
                                        <div class="m_goals mg_guest"><?= $item["guest"]["goals"] ?></div>
                                    </div>
                                </div>
                                <div class="match_all_info_block">
                                    <div class="match_user_info">
                                        <?php if ($item["write"]): ?>
                                            <span class="text-success">Вы заполнили: <?= $item["write"] ?></span>
                                        <?php else: ?>
                                            <span class="text-info">Не заполнено</span>
                                        <?php endif; ?>
                                    </div>
                                    <a class="match_write_btn" href="/p/kvngame/<?= $item["number"] ?>/">
                                        <?php if ($item["active"] === 'Y'): ?>
                                            <?php if ($item["write"]): ?>
                                                Изменить <i class="bi bi-pencil-square"></i>
                                            <?php else: ?>
                                                Заполнить <i class="bi bi-pencil-square"></i>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            Проверить </i>
                                        <?php endif; ?>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="accordion-item">
                <h6 class="accordion-header" id="headingThree">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                            data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                        Будущие <span class="badge_pr badge"><?= $arResult["future_count"] ?></span>
                    </button>
                </h6>
                <div id="collapseThree" class="accordion-collapse collapse" aria-labelledby="headingThree"
                     data-bs-parent="#accordionExample">
                    <div class="accordion-body">
                        <?php
                        foreach ($arResult["future"] as $id => $item):?>
                            <?php
                            if ($day !== $item["date"]):?>
                                <div class="day_line_block">
                                    <div class="day_line_box">
                                        <div class="day_date m_template_box"><i
                                                    class="bi bi-calendar4-event"></i> <?= $item["date"] ?></div>
                                    </div>
                                </div>
                            <?endif;
                            $day = $item["date"];
                            ?>
                            <div class="m_match_wrapper">
                                <div class="m_match_box">
                                    <div class="m_number"><?= $item["number"] ?></div>
                                    <div class="m_time m_template_box"><i class="bi bi-alarm"></i> <?= $item["time"] ?>
                                    </div>
                                    <div class="m_team_block m_template_box">
                                        <div class="m_home_team_box m_team_box">
                                            <div class="m_team_title"><?= $item["home"] ?></div>
                                        </div>
                                        <div class="m_separate">-</div>
                                        <div class="m_guest_team_box m_team_box">
                                            <div class="m_team_title"><?= $item["guest"] ?></div>
                                        </div>
                                    </div>
                                    <div class="m_goals_box m_template_box">
                                        <div class="m_goals mg_home">0</div>
                                        <div class="m_separate">-</div>
                                        <div class="m_goals mg_guest">0</div>
                                    </div>
                                </div>
                                <div class="match_all_info_block">
                                    <div class="match_user_info">
                                        Ожидается заполнение
                                    </div>
                                    <div class="match_write_btn" href="/p/kvngame/<?= $id ?>/">
                                        Ожидайте
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php

class FillKVNGameList
{

    public function fillDataBox($data)
    {

        return '
            <div class="day_line_block">
                <div class="day_line_box">
                    <div class="day_date m_template_box"><i class="bi bi-calendar4-event"></i> ' . $data . '</div>
                </div>
            </div>
        ';

    }

}

?>




