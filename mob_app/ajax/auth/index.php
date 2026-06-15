<?php

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept');

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

$_REQUEST['date'] = date(\CDatabase::DateFormatToPHP('DD.MM.YYYY HH:MI:SS'), time());

$arFile = '';

if ($_FILES['file']) {
    $fileId = CFile::SaveFile($_FILES['file'], '/main');
    $arFile = CFile::MakeFileArray($fileId);
}

if ($_REQUEST) {
    $auth = new Prognos9ysAuthClass($_REQUEST);
    $response = $auth->result();

    if ($response['status'] === 'ok' && $_FILES['file']) {
        $auth->setAvaImg($arFile, $_REQUEST['token']);
    }

    echo json_encode($response);
}
