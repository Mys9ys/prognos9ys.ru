<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>

<?php if(CSite::InGroup (array(7))):?>
 <?php
    $APPLICATION->IncludeComponent(
        "prognos9ys:admin",
        "",
        array(),
        $component,
        array()
    );?>

<?php else:?>
<div class="pr_admin_block">
    <div class="pr_admin_block_title">Тут пусто</div>
</div>
<?php endif;?>

<style>
    .pr_admin_block{
        width: 400px;
        max-width: 98%;
        margin: 0 auto;
        color: #fff;
        background: #253133;
        border-radius: 5px;
        padding: 5px;
        font-size: 14px;
    }
    .pr_admin_block_title{
        box-shadow: inset 0 2px 10px 1px rgba(0, 0, 0, .3), inset 0 0 0 60px rgba(0, 0, 0, .3), 0 1px rgba(255, 255, 255, .08);
        padding: 3px;
        color: #228B22;
        border-radius: 3px;
        font-size:12px;
    }
</style>
