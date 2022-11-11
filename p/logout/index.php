<?php
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/header.php');

$USER->Logout();
LocalRedirect('/');
