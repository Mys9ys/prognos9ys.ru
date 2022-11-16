<?
/**
 * Bitrix Framework
 * @package bitrix
 * @subpackage main
 * @copyright 2001-2014 Bitrix
 */

/**
 * Bitrix vars
 * @global CMain $APPLICATION
 * @var array $arParams
 * @var array $arResult
 * @var CBitrixComponentTemplate $this
 */

if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die(); ?>

<div class="new_auth_form_wrapper">
    <div class="new_auth_form">
        <form method="post" class="naf_form" action="<?= $arResult["AUTH_URL"] ?>" name="regform">
            <div class="naf_form_err_line"></div>

            <div class="naf_input_box">
                <div class="naf_input_title">Мы будем называть вас:</div>
                <div class="naf_input_box_wrapper">
                    <input class="naf_input naf_input_nik" type="text" name="name"
                           value="Нострадамус № <?= $arResult["new_user_number"] ?>" placeholder="Ваше имя/ник"/>
                    <div class="naf_input_validate_info"></div>
                </div>
                <div class="naf_input_title">Но вы можете это исправить...</div>
            </div>

            <div class="naf_input_box">
                <div class="naf_input_title">Ваш e-mail</div>
                <div class="naf_input_box_wrapper">
                    <input class="naf_input naf_input_mail" type="text" name="login" placeholder="Ваш e-mail"/>
                    <div class="naf_input_validate_info"></div>
                </div>
            </div>

            <div class="naf_input_box">
                <div class="naf_input_title">Пароль (не менее 6 символов)</div>
                <div class="naf_input_box_wrapper">
                    <input class="naf_input naf_input_pass" type="password" name="password" placeholder="Пароль"/>
                    <div class="naf_input_validate_info"></div>
                </div>
            </div>

            <?php if($_REQUEST["ref"]):?>
                <input class="naf_ref" type="hidden" name="ref" value="<?=$_REQUEST["ref"]?>"/>
            <?php endif;?>


            <button class="naf_btn naf_btn_send btn btn-primary" name="register">
                <i class="fa fa-spinner fa-spin" aria-hidden="true"></i>
                <span>Зарегистрироваться</span>
            </button>

            <a class="naf_btn btn btn-primary" href="?login=yes">К авторизации</a>
        </form>

    </div>
</div>
