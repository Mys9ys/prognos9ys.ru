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


<?
//var_dump($arResult);
var_dump($arResult["SIGNED_DATA"]);
ShowMessage($arParams["~AUTH_RESULT"]);
?>
<div class="new_auth_form_wrapper">
    <div class="new_auth_form">
        <form method="post" class="naf_form" action="<?= $arResult["AUTH_URL"] ?>" name="regform">

            <div class="naf_input_box">
                    <div class="naf_input_title">Мы будем называть вас:</div>
                    <input class="naf_input form-control" type="text" name="USER_NAME"
                           value="Нострадамус № <?= $arResult["new_user_number"] ?>" placeholder="Ваше имя/ник"/>
                    <div class="naf_input_title">Но вы можете это исправить...</div>
            </div>

            <div class="naf_input_box">
                <div class="naf_input_title">Ваш e-mail</div>
                <input class="naf_input form-control" type="text" name="USER_LOGIN" placeholder="Ваш e-mail"
                       />
            </div>

            <div class="naf_input_box">
                <div class="naf_input_title">Пароль</div>
                <input class="naf_input form-control naf_pass" type="password" name="USER_PASSWORD" placeholder="Пароль"
                       autocomplete="off" />
            </div>

            <input type="submit" class="naf_btn btn btn-primary" name="Register" value="Зарегистрироваться"/>
        </form>

    </div>
</div>


<script type="text/javascript">
    console.log(document.regform.USER_PASSWORD.valueOf())
    // document.regform.USER_CONFIRM_PASSWORD.v=document.regform.USER_PASSWORD.val();
</script>
