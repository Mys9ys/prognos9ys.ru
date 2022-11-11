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

            <p><? ShowMessage($arResult['ERROR_MESSAGE']); ?></p>

            <form name="form_auth" class="naf_form" method="post" target="_top" action="<?= $arResult["AUTH_URL"] ?>">

                <input type="hidden" name="AUTH_FORM" value="Y"/>
                <input type="hidden" name="TYPE" value="AUTH"/>

                <div class="naf_input_box">
                    <div class="naf_input_title">Ваш e-mail</div>
                    <input class="naf_input form-control" type="text" name="USER_LOGIN" placeholder="Ваш e-mail"
                           value="<?= $arResult["LAST_LOGIN"] ?>"/>
                </div>

                <div class="naf_input_box">
                    <div class="naf_input_title">Пароль</div>
                    <input class="naf_input form-control" type="password" name="USER_PASSWORD" placeholder="Пароль"
                           autocomplete="off"/>
                </div>

                <input type="submit" class="naf_btn btn btn-primary" name="Login" value="Войти"/>

                <a class="naf_btn btn btn-primary" href="?register=yes">К регистрации</a>

            </form>

        </div>
    </div>

<?php endif ?>

