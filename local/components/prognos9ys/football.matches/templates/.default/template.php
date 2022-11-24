<?php if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();?>
<div class="matches_wrapper">
    <div class="matches_title">
        <div class="matches_logo">
            <img class="matches_logo_img" src="<?=$templateFolder?>/assets/img/logo.png" alt="">
        </div>
        <div class="matches_text">
            Расписание матчей Чемпионата мира по футболу
        </div>
    </div>
    <?php $day = '';?>

    <div class="accordion" id="accordionExample">
        <div class="accordion-item">
            <h6 class="accordion-header" id="headingOne">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                        data-bs-target="#collapseOne"
                        aria-expanded="false" aria-controls="collapseOne">
                   Прошедшие
                </button>
            </h6>
            <div id="collapseOne" class="accordion-collapse collapse" aria-labelledby="headingOne"
                 data-bs-parent="#accordionExample">
                <div class="accordion-body">
                    <?foreach ($arResult["teams"] as $id=>$item):?>
                    <?php if($item["active"] === 'Y') continue;?>
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
                        <div class="m_match_wrapper">
                            <div class="m_match_box">
                                <div class="m_number">#<?=$item["number"]?></div>
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
                            </div>
                            <div class="match_all_info_block">
                                <div class="match_user_info">
                                    <?php if($item["write"]):?>
                                        <span class="text-success">Вы заполнили: <?=$item["write"]?></span>
                                    <?php else:?>
                                        <span class="text-info">Не заполнено</span>
                                    <?php endif;?>
                                </div>
                                <a class="match_write_btn" href="/p/match/<?=$id?>/">
                                    <?php if($item["active"] === 'Y'):?>
                                        <?php if($item["write"]):?>
                                            Изменить <i class="bi bi-pencil-square"></i>
                                        <?php else:?>
                                            Заполнить <i class="bi bi-pencil-square"></i>
                                        <?php endif;?>
                                    <?php else:?>
                                        Проверить </i>
                                    <?php endif;?>
                                </a>
                            </div>
                        </div>
                    <?php endforeach;?>
                </div>
            </div>
        </div>
        <div class="accordion-item">
            <h6 class="accordion-header" id="headingTwo">
                <button class="accordion-button" type="button" data-bs-toggle="collapse"
                        data-bs-target="#collapseTwo" aria-expanded="true" aria-controls="collapseTwo">
                    Текущие
                </button>
            </h6>
            <div id="collapseTwo" class="accordion-collapse collapse show" aria-labelledby="headingTwo"
                 data-bs-parent="#accordionExample">
                <div class="accordion-body">
                    <?foreach ($arResult["teams"] as $id=>$item):?>
                        <?php if($item["active"] === 'N') continue;?>
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
                        <div class="m_match_wrapper">
                            <div class="m_match_box">
                                <div class="m_number">#<?=$item["number"]?></div>
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
                            </div>
                            <div class="match_all_info_block">
                                <div class="match_user_info">
                                    <?php if($item["write"]):?>
                                        <span class="text-success">Вы заполнили: <?=$item["write"]?></span>
                                    <?php else:?>
                                        <span class="text-info">Не заполнено</span>
                                    <?php endif;?>
                                </div>
                                <a class="match_write_btn" href="/p/match/<?=$id?>/">
                                    <?php if($item["active"] === 'Y'):?>
                                        <?php if($item["write"]):?>
                                            Изменить <i class="bi bi-pencil-square"></i>
                                        <?php else:?>
                                            Заполнить <i class="bi bi-pencil-square"></i>
                                        <?php endif;?>
                                    <?php else:?>
                                        Проверить </i>
                                    <?php endif;?>
                                </a>
                            </div>
                        </div>
                    <?php endforeach;?>
                </div>
            </div>
        </div>
        <div class="accordion-item">
            <h6 class="accordion-header" id="headingThree">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                        data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                    Будущие
                </button>
            </h6>
            <div id="collapseThree" class="accordion-collapse collapse" aria-labelledby="headingThree"
                 data-bs-parent="#accordionExample">
                <div class="accordion-body">
                    <?foreach ($arResult["future"] as $id=>$item):?>
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
                        <div class="m_match_wrapper">
                            <div class="m_match_box">
                                <div class="m_number"><?=$item["number"]?></div>
                                <div class="m_time m_template_box"><i class="bi bi-alarm"></i> <?=$item["time"]?></div>
                                <div class="m_team_block m_template_box">
                                    <div class="m_home_team_box m_team_box">
                                        <div class="m_team_title"><?=$item["home"]?></div>
                                    </div>
                                    <div class="m_separate">-</div>
                                    <div class="m_guest_team_box m_team_box">
                                        <div class="m_team_title"><?=$item["guest"]?></div>
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
                                <div class="match_write_btn" href="/p/match/<?=$id?>/">
                                    Ожидайте
                                </div>
                            </div>
                        </div>
                    <?php endforeach;?>
                </div>
            </div>
        </div>
    </div>
</div>



