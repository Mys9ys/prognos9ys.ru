<?
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

?>
<?php if (!$_SERVER["QUERY_STRING"]): ?>
    <div class="new_auth_form_wrapper">
        <div class="new_auth_form">
            <div class="naf_title">Чтобы продолжить - требуется авторизация</div>
            <div class="btn_reg_box">
                <a class="btn_reg_form" href="?login=yes">Войти</a><a class="btn_reg_form" href="?register=yes">Зарегистрироваться</a>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="new_auth_form_wrapper">
        <div class="new_auth_form">

            <div class="naf_form_err_line"></div>

            <form name="form_auth" class="naf_form" method="post" target="_top" action="<?= $arResult["AUTH_URL"] ?>">

                <input type="hidden" name="AUTH_FORM" value="Y"/>
                <input type="hidden" name="TYPE" value="AUTH"/>

                <div class="naf_input_box">
                    <div class="naf_input_title">Ваш e-mail</div>
                    <div class="naf_input_box_wrapper">
                        <input class="naf_input naf_input_mail" type="text" name="login" placeholder="Ваш e-mail"/>
                        <div class="naf_input_validate_info"></div>
                    </div>
                </div>

                <div class="naf_input_box">
                    <div class="naf_input_title">Пароль</div>
                    <div class="naf_input_box_wrapper">
                        <input class="naf_input naf_input_pass" type="password" name="password" placeholder="Пароль"/>
                        <div class="naf_input_validate_info"></div>
                    </div>
                </div>

                <button class="naf_btn naf_btn_auth btn btn-primary" name="register">
                    <i class="fa fa-spinner fa-spin" aria-hidden="true"></i>
                    <span>Войти</span>
                </button>

                <a class="naf_btn btn btn-primary" href="?register=yes">К регистрации</a>

            </form>

        </div>
    </div>

<?php endif ?>

