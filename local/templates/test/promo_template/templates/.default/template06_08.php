<? if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();
?>

<div class="promo_template_wrapper_full_screen">
    <div class="promo_template_block">

        <img class="para_promo_background" src="<?=$templateFolder?>/img/parallax/bg.jpg" alt="" data-mobile="<?=$templateFolder?>/img/promo_bg_mob.jpg">

        <ul id="parallax_promo">
            <li class="layer layer_01" data-depth="0.15"><img src="<?= $templateFolder ?>/img/parallax/p_1.png" alt=""></li>
            <li class="layer layer_02" data-depth="0.3"><img src="<?= $templateFolder ?>/img/parallax/p_2.png" alt=""></li>
            <li class="layer layer_03" data-depth="0.45"><img src="<?= $templateFolder ?>/img/parallax/p_3.png" alt=""></li>
        </ul>
        <div class="promo_template_wrapper">
            <div class="promo_template_title_block">
                <div class="promo_template_title_block_logo">
                    <a href="/">
                        <img class="promo_template_title_block_logo_img" src="<?=$templateFolder?>/img/logo.png" alt="" >
                    </a>
                </div>
                <div class="promo_template_title_right_block">
                    <ul class="promo_template_title_block_link">
                        <li class="promo_template_title_block_link_elem promo_slide_move" data-index="1"><span>Условия</span></li>
                        <li class="promo_template_title_block_link_elem promo_slide_move" data-index="2"><span>Призы</span></li>
                        <li class="promo_template_title_block_link_elem promo_slide_move" data-index="3"><span>Победители</span></li>
                        <li class="promo_template_title_block_link_elem promo_slide_move" data-index="4"><span>Вопрос-Ответ</span></li>
                        <li class="promo_template_title_block_link_elem" >
                            <a href="<?=$templateFolder?>/img/rule.pdf" target="_blank">Правила <i class="fa fa-long-arrow-down" aria-hidden="true"></i></a>
                        </li>
                    </ul>
                    <div class="promo_template_reg_btn_box">
                        <div class="promo_template_reg_btn promo_load_modal_btn promo_form_reg_template"><?//promo_load_modal_btn?>
                            Регистрация
                        </div>
                    </div>
                </div>
            </div>
            <!-- Slider main container -->
            <div class="swiper-container promo_template_swiper">
                <!-- Additional required wrapper -->
                <div class="swiper-wrapper">
                    <!-- Slides -->

                    <?//slide 1?>
                    <div class="swiper-slide promo_slide_box promo_slide_main_box">
                        <div class="promo_slide_title_box">
                            <div class="promo_slide_title_text promo_slide_title_main_slide">
                                <?$APPLICATION->IncludeFile($templateFolder."/inc/title.php", Array(), Array("MODE" => "html", "NAME" => "Заголовок"));?>
                            </div>
                        </div>
                        <div class="promo_slide_body promo_slide_body_main_slide">
                            <div class="promo_slide_body_main_box">
                                <?foreach([1, 2, 3] as $step){?>
                                    <div class="promo_slide_main_step_text">
                                        <?$APPLICATION->IncludeFile($templateFolder."/inc/step" . $step . ".php", Array(), Array("SHOW_BORDER" => false, "MODE" => "html", "NAME" => "Шаг " . $step));?>
                                    </div>
                                    <?if($step == 2){?><div class="promo_load_check_btn promo_load_modal_btn">Загрузить</div><?}?>
                                <?}?>
                            </div>
                            <div class="promo_slide_main_img_box">
                                <div class="promo_slide_main_img_wrapper"></div>
                            </div>
                        </div>
                    </div><?//slide 1 END?>

                    <?//slide 2?>
                    <div id="promo_block_1" class="swiper-slide promo_slide_box promo_slide_box_steps">
                        <div class="promo_slide_title_box">
                            <div class="promo_slide_title_text">Условия</div>
                        </div>
                        <div class="promo_slide_body promo_slide_body_steps">
                            <div class="promo_slide_step elem_slide_vis_1 elem_slide_vis">
                                <div class="promo_slide_step_img_box"></div>
                                <img class="promo_slide_step_img" src="<?=$templateFolder?>/img/2-1.png" alt="">
                                <div class="promo_slide_step_text">
                                    <?$APPLICATION->IncludeFile($templateFolder."/inc/step1.php", Array(), Array("MODE" => "php", "NAME" => "Шаг 1"));?>
                                </div>
                            </div>
                            <div class="promo_slide_step_spacer">
                                <img class="promo_slide_step_spacer_img" src="<?=$templateFolder?>/img/step_arrow.png" data-mobile="<?=$templateFolder?>/img/step_arrow_mob.png" alt="">
                            </div>
                            <div class="promo_slide_step elem_slide_vis_1 elem_slide_vis">
                                <div class="promo_slide_step_img_box"></div>
                                <img class="promo_slide_step_img" src="<?=$templateFolder?>/img/2-2.png" alt="">
                                <div class="promo_slide_step_text">
                                    <?$APPLICATION->IncludeFile($templateFolder."/inc/step2.php", Array(), Array("MODE" => "html", "NAME" => "Шаг 2"));?>
                                </div>
                            </div>
                            <div class="promo_slide_step_spacer">
                                <img class="promo_slide_step_spacer_img" src="<?=$templateFolder?>/img/step_arrow.png" data-mobile="<?=$templateFolder?>/img/step_arrow_mob.png" alt="">
                            </div>
                            <div class="promo_slide_step elem_slide_vis_1 elem_slide_vis">
                                <div class="promo_slide_step_img_box"></div>
                                <img class="promo_slide_step_img" src="<?=$templateFolder?>/img/2-3.png" alt="">
                                <div class="promo_slide_step_text">
                                    <?$APPLICATION->IncludeFile($templateFolder."/inc/step3.php", Array(), Array("MODE" => "html", "NAME" => "Шаг 3"));?>
                                </div>
                            </div>
                        </div>
                    </div><?//slide 2 END?>

                    <?//slide 3?>
                    <div id="promo_block_2" class="swiper-slide promo_slide_box promo_slide_box_prizes">
                        <div class="promo_slide_title_box">
                            <div class="promo_slide_title_text">Призы</div>
                            <div class="promo_slide_title_description">Больше чеков- выше шанс на выигрыш</div>
                        </div>
                        <div class="promo_slide_body promo_slide_body_prizes">
                            <?foreach ($arResult['prizes'] as $key=>$prize){?>
                                <div class="promo_slide_prize_box">
                                    <div class="promo_slide_prize_wrapper">
                                        <img class="promo_slide_prize_img" src="<?=$prize['img']?>" alt="">
                                    </div>
                                    <div class="promo_slide_prize_text"><?=$prize['text']?></div>
                                </div>
                            <?}?>
                        </div>
                    </div><?//slide 3 END?>

                    <?//slide 4?>
                    <div id="promo_block_3" class="swiper-slide promo_slide_box promo_slide_box_winners">
                        <div class="promo_slide_title_box">
                            <div class="promo_slide_title_text">Победители</div>
                            <div class="promo_winner_search_box">
<!--                                <form id="form_search_winner" action="--><?//=POST_FORM_ACTION_URI?><!--" method="post">-->
                                    <input type="text" class="promo_winner_search promo_order_mask" placeholder="Поиск по номеру заказа">
<!--                                </form>-->
                                <img class="promo_winner_search_img" src="<?=$templateFolder?>/img/promo_search.png">
                            </div>
                        </div>

                        <div id="table_winners">
                            <?
                            if($arResult['ajax_form'] == 'Y' && $arResult['type_form'] == 'winner_search') {
                                $GLOBALS['APPLICATION']->RestartBuffer();
                            }
                            ?>
                            <div class="promo_slide_body promo_slide_body_winners">
                                <div class="promo_slide_body_table_wrapper">
                                    <table class="promo_slide_winners_tab">
                                        <thead>
                                            <tr>
                                                <th>Приз</th>
                                                <th>ФИО победителя</th>
                                                <th>Телефон</th>
                                                <th>Номер заказа</th>
                                                <th>Регион</th>
                                            </tr>
                                        </thead>
                                        <tbody class="promo_winners_table_body">
                                            <?foreach ($arResult['winners'] as $key=>$winner){?>
                                                <tr class="promo_winner_item">
                                                    <td><span>Приз</span><?=$winner['prize']?></td>
                                                    <td><span>ФИО победителя</span><?=$winner['fio']?></td>
                                                    <td><span>Телефон</span><?=$winner['phone']?></td>
                                                    <td class="promo_item_order"><span>Номер заказа</span><?=$winner['order']?></td>
                                                    <td><span>Регион</span><?=$winner['region']?></td>
                                                </tr>
                                            <?}?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <?
                            if($arResult['ajax_form'] == 'Y' && $arResult['type_form'] == 'winner_search') {
                                die();
                            }
                            ?>
                        </div>
                    </div><?//slide 4 END?>

                    <?//slide 5?>
                    <div id="promo_block_4" class="swiper-slide promo_slide_box promo_slide_box_faq">
                        <div class="promo_slide_title_box">
                            <div class="promo_slide_title_text">Вопрос-ответ</div>
                        </div>
                        <div class="promo_slide_body promo_slide_body_faq">
                            <ul class="faq_list">
                                <?foreach ($arResult['faq'] as $key=>$faq){?>
                                    <li>
                                        <div class="name"><?=$faq['name']?></div>
                                        <div class="text"><?=$faq['text']?></div>
                                    </li>
                                <?}?>
                            </ul>
                        </div>

                    </div><?//slide 5 END?>

                </div>
                <!-- If we need pagination -->
                <div class="swiper-pagination"></div>


                <!-- If we need navigation buttons -->
                <!--                <div class="swiper-button-prev"></div>-->
                <!--                <div class="swiper-button-next"></div>-->

                <!-- If we need scrollbar -->
                <!--        <div class="swiper-scrollbar"></div>-->
            </div>
            <div class="promo_swiper_nav_box">
                <div class="promo_swiper_nav_left promo_swiper_nav_btn"><</div>
                <div class="promo_swiper_nav_right promo_swiper_nav_btn">></div>
            </div>

            <div class="promo_first_slider_hidden elem_slide_vis_0 elem_slide_vis elem_visible">
                <!--<div class="promo_first_slider_hidden_wrapper"></div>-->
                <?$APPLICATION->IncludeFile($templateFolder."/inc/picture.php", Array(), Array("MODE" => "html", "NAME" => "Фото"));?>
            </div>
            <div class="promo_slider_social_btn elem_slide_vis_0 elem_slide_vis elem_visible">
                <a rel="nofollow" href="https://www.instagram.com/dryclean.ru" target="_blank"><i class="fa fa-instagram" aria-hidden="true"></i></a>
                <a rel="nofollow" href="https://www.facebook.com/diana.dryclean" target="_blank"><i class="fa fa-facebook-square" aria-hidden="true"></i></a>
                <a rel="nofollow" href="https://vk.com/diana.dryclean" target="_blank"><i class="fa fa-vk" aria-hidden="true"></i></a>
                <a rel="nofollow" href="https://ok.ru/group/59117228916885 " target="_blank"><i class="fa fa-odnoklassniki-square" aria-hidden="true"></i></a>
                <a rel="nofollow" href="https://www.youtube.com/channel/UC0cwnpStrHgXUVuZZpQvuvw" target="_blank"><i class="fa fa-youtube-square" aria-hidden="true"></i></a>
            </div>
            <div class="promo_load_check_btn promo_load_check_btn_faq promo_load_callback_btn elem_slide_vis_4 elem_slide_vis">Задать вопрос</div>
        </div>
        <div class="promo_last_slide_footer elem_slide_vis_4 elem_slide_vis">
            <div class="promo_last_slide_footer_wrapper">
                <div class="promo_last_slide_text_temp promo_last_slide_rules">
                    <a class="last_slide_footer_center" href="<?=$templateFolder?>/img/rule.pdf" target="_blank">Правила </a>
                </div>
                <div class="promo_last_slide_text_temp promo_last_slide_agreement">
                    <a class="last_slide_footer_center" href="/agreement/" target="_blank">Пользовательское согашение</a>
                </div>
                <div class="promo_last_slide_text_temp promo_last_slide_callback">
                    <div class="last_slide_footer_center promo_load_callback_btn">Обратная связь</div>
                </div>
                <div class="promo_last_slide_social">
                    <div class="last_slide_footer_center">
                        <a rel="nofollow" href="https://www.instagram.com/dryclean.ru" target="_blank"><i class="fa fa-instagram" aria-hidden="true"></i></a>
                        <a rel="nofollow" href="https://www.facebook.com/diana.dryclean" target="_blank"><i class="fa fa-facebook-square" aria-hidden="true"></i></a>
                        <a rel="nofollow" href="https://vk.com/diana.dryclean" target="_blank"><i class="fa fa-vk" aria-hidden="true"></i></a>
                        <a rel="nofollow" href="https://ok.ru/group/59117228916885 " target="_blank"><i class="fa fa-odnoklassniki-square" aria-hidden="true"></i></a>
                        <a rel="nofollow" href="https://www.youtube.com/channel/UC0cwnpStrHgXUVuZZpQvuvw" target="_blank"><i class="fa fa-youtube-square" aria-hidden="true"></i></a>
                    </div>
                </div>
                <div class="promo_last_slide_info">
                    <div class="last_slide_footer_center">
                        <?$APPLICATION->IncludeFile($templateFolder."/inc/info.php", Array(), Array("MODE" => "html", "NAME" => "информацию в футере"));?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="promo_modal_form_registration promo_modal_form_template"><? /// Mys9ys самописный модал для promo 15.07.2021?>
    <div class="promo_modal_close"><i class="fa fa-times" aria-hidden="true"></i></div>
    <div class="promo_modal_form_registration_title">Регистрация</div>
    <div class="promo_modal_form_registration_body">
        <div class="promo_modal_form_loading">
            <div class="promo_modal_form_loading-img"></div>
        </div>
        <div class="promo_modal_form_registration_body_title">
            Введите регистрационные данные:
        </div>

        <form id="form_registration" action="<?=POST_FORM_ACTION_URI?>" method="post">
            <?
            if($arResult['ajax_form'] == 'Y' && $arResult['type_form'] == 'registration') {
                $GLOBALS['APPLICATION']->RestartBuffer();
            }
            ?>
                <?if(!empty($arResult['ERRORS']['registration']['submit'])) {
                    ?>
                    <div class="promo_form_row">
                        <span class="errors" style="font-size: 16px;"><?=$arResult['ERRORS']['registration']['submit']?></span>
                    </div>
                    <?
                }

                if(empty($arResult['ERRORS']) && $arResult['ajax_form'] == 'Y' && $arResult['type_form'] == 'registration') {
                    ?>
                    <div class="promo_form_row">
                        <span class="success">Ваша заявка принята. С вами свяжутся в ближайшее время.</span>
                    </div>
                    <?
                }?>

                <label class="promo_label_form promo_label_form_fio" for="fio">
                    Имя
                    <input type="text" name="fio" class="promo_form_modal_input input_two" placeholder="Петров Алексей Иванович" value="<?=$arResult['VALUES']['fio']?>">
                    <?if(!empty($arResult['ERRORS']['registration']['fio'])) {
                        ?>
                        <span class="errors"><?=$arResult['ERRORS']['registration']['fio']?></span>
                        <?
                    }?>
                </label>
                <div class="promo_form_row">
                    <label class="promo_label_form promo_label_form_other promo_label_mobile_margin" for="order">
                        № заказа
                        <input type="text" name="order" class="promo_form_modal_input input_one promo_order_mask" placeholder="12345678901" value="<?=$arResult['VALUES']['order']?>">
                        <?if(!empty($arResult['ERRORS']['registration']['order'])) {
                            ?>
                            <span class="errors"><?=$arResult['ERRORS']['registration']['order']?></span>
                            <?
                        }?>
                    </label>
                    <label class="promo_label_form promo_label_form_other" for="phone">
                        Телефон
                        <input type="text" name="phone" class="promo_form_modal_input input_two promo_phone_mask" placeholder="+7(___)-___-__-__" value="<?=$arResult['VALUES']['phone']?>">
                        <?if(!empty($arResult['ERRORS']['registration']['phone'])) {
                            ?>
                            <span class="errors"><?=$arResult['ERRORS']['registration']['phone']?></span>
                            <?
                        }?>
                    </label>
                </div>
                <div class="promo_form_row">
                    <label class="promo_label_form promo_label_form_other" for="mail">
                        E-mail
                        <input type="text" name="email" class="promo_form_modal_input input_two" placeholder="example@example.com" value="<?=$arResult['VALUES']['email']?>">
                        <?if(!empty($arResult['ERRORS']['registration']['email'])) {
                            ?>
                            <span class="errors"><?=$arResult['ERRORS']['registration']['email']?></span>
                            <?
                        }?>
                    </label>
                </div>

                <label class="promo_label_form promo_label_politics" for="politics">
                    <input type="checkbox" id="promo_politics" name="politics" <?if(empty($arResult['VALUES']) || $arResult['VALUES']['politics'] == 'on') {?>checked<?}?>/>
                    <span class="politics_circle">
                        <?if(empty($arResult['VALUES']) || $arResult['VALUES']['politics'] == 'on') {?>
                            <span class="politics_mark"></span>
                        <?}?>
                    </span>я согласен(-на) с <a href="<?=$templateFolder?>/img/rule.pdf" target="_blank">правилами акции</a>,
                    c <a href="/agreement/" target="_blank">пользовательским соглашением</a>
                </label>

                <?if(!empty($arResult['ERRORS']['registration']['politics'])) {?>
                    <div class="promo_form_row" style="margin-top: 5px;">
                        <span class="errors"><?=$arResult['ERRORS']['registration']['politics']?></span>
                    </div>
                    <?
                }?>

                <div class="promo_form_reg_btn promo_form_reg_template">
                    Зарегистрировать
                </div>
            <?
            if($arResult['ajax_form'] == 'Y' && $arResult['type_form'] == 'registration') {
                die();
            }
            ?>
        </form>
    </div>
</div>


<div class="promo_modal_form_callback promo_modal_form_template">
    <div class="promo_modal_close"><i class="fa fa-times" aria-hidden="true"></i></div>
    <div class="promo_modal_form_registration_title">Обратная связь</div>
    <div class="promo_modal_form_registration_body">
        <div class="promo_modal_form_loading">
            <div class="promo_modal_form_loading-img"></div>
        </div>
        <div class="promo_modal_form_registration_body_title">
            ОСТАЛИСЬ ВОПРОСЫ? <br> НАПИШИТЕ НАМ, наши специалисты обязательно с вами свяжутся
        </div>
        <form id="form_feedback" action="<?=POST_FORM_ACTION_URI?>" method="post">
            <?
            if($arResult['ajax_form'] == 'Y' && $arResult['type_form'] == 'feedback') {
                $GLOBALS['APPLICATION']->RestartBuffer();
            }
            ?>
                <?if(!empty($arResult['ERRORS']['feedback']['submit'])) {
                    ?>
                    <div class="promo_form_row">
                        <span class="errors"><?=$arResult['ERRORS']['feedback']['submit']?></span>
                    </div>
                    <?
                }

                if(empty($arResult['ERRORS']) && $arResult['ajax_form'] == 'Y' && $arResult['type_form'] == 'feedback') {
                    ?>
                    <div class="promo_form_row">
                        <span class="success">Ваша заявка принята. С вами свяжутся в ближайшее время.</span>
                    </div>
                    <?
                }?>

                <div class="promo_form_row">
                    <label class="promo_label_form promo_label_form_other promo_label_form_btn_fio" for="fio">
                        Имя
                        <input type="text" name="fio" class="promo_form_modal_input input_one" placeholder="Петров Алексей Иванович" value="<?=$arResult['VALUES']['fio']?>">
                        <?if(!empty($arResult['ERRORS']['feedback']['fio'])) {
                            ?>
                            <span class="errors"><?=$arResult['ERRORS']['feedback']['fio']?></span>
                            <?
                        }?>
                    </label>
                    <label class="promo_label_form promo_label_form_other promo_label_form_btn_phone" for="phone" >
                        Телефон
                        <input type="text" name="phone" class="promo_form_modal_input input_two promo_phone_mask" placeholder="+7(___)-___-__-__" value="<?=$arResult['VALUES']['phone']?>">
                        <?if(!empty($arResult['ERRORS']['feedback']['phone'])) {
                            ?>
                            <span class="errors"><?=$arResult['ERRORS']['feedback']['phone']?></span>
                            <?
                        }?>
                    </label>
                </div>
                <div class="promo_form_row">
                    <label class="promo_label_form promo_label_form_textarea" for="question">
                        Доп. информация
                        <textarea class="promo_form_modal_input promo_form_modal_textarea input_two" name="question"><?=$arResult['VALUES']['question']?></textarea>
                    </label>
                </div>
                <div class="promo_form_reg_btn promo_form_reg_template">
                    Отправить
                </div>
            <?
            if($arResult['ajax_form'] == 'Y' && $arResult['type_form'] == 'feedback') {
                die();
            }
            ?>
        </form>
    </div>
</div>
<div id="overlay_promo_modal"></div>

<style>
    .header-mobile{
        display: none!important;
    }
    .errors{
        color: #ff1307;
        display: inline-block;
        padding-top: 5px;
        font-size: 12px;
    }
    .success{
        color: green;
    }
    .promo_modal_form_loading {
        position: fixed;
        left: 0;
        right: 0;
        top: 0;
        bottom: 0;
        z-index: 99999999;
        display: none;
        background: hsla(0,0%,100%,.5);
    }
    .promo_modal_form_loading-img {
        background: url(/local/templates/dryclean_new/images/ajax-loader.gif) no-repeat center center;
        height: 100px;
        margin-top: 30%;
    }
</style>

