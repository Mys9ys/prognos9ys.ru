<?php
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

if($_REQUEST["event"]){
    $user = new CUser;
    $fields = array(
        "UF_EVENT" => $_REQUEST["event"],
    );
    $user->Update($_REQUEST["id"], $fields);
}
