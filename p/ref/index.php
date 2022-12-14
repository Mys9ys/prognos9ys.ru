<?php
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php');
$APPLICATION->SetTitle("Условия реферальной программы");
?>

<div class="ref_wrapper">
    <img src="img/big.jpg" alt="">
    <h6>Условия:</h6>
    <p>
        Реферальная программа по привлечению активных участников проекта подразумевает рассылку реферальной ссылки от
        лица участника. Ее можно скопировать в своем профиле.
        Регистрирующийся по ссылке участник должен сделать минимум 5 прогнозов – тогда он засчитывается как «активный участник».
    </p>

    <p class="text">
        Окончание действия реферальной программы – 30 ноября.
    </p>
    <p>По ходу соревнования будет составлен рейтинг по числу активных приглашённых участников.</p>
    <h6>
        Призовыми являются 5 первых места:
    </h6>
    <ul>
        <li><b class="text-success">1 место – 3 000 рублей</b> <i class="text-danger">(минимум привлеченных - 20 активных участников)</i></li>
        <li>2 место – 2 000 рублей <i class="text-danger">(минимум привлеченных - 15 активных участников)</i></li>
        <li>3 место – 1 000 рублей <i class="text-danger">(минимум привлеченных - 8 активных участников)</i></li>
        <li>4-5 места – 500 рублей <i class="text-danger">(минимум привлеченных - 5 активных участников)</i></li>
    </ul>
</div>

<style>
    .ref_wrapper {
        width: 400px;
        max-width: 98%;
        margin: 0 auto;
        color: #fff;
        background: #253133;
        border-radius: 5px;
        padding: 5px;
        font-size: 14px;
    }

    .ref_wrapper img {
        max-width: 98%;
        margin: 0 auto;

    }
</style>

<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");