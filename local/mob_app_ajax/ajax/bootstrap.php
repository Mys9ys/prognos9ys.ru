<?php

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept');
header('Content-Type: application/json; charset=utf-8');

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

function mob_app_request_data(): array
{
    $json = json_decode(file_get_contents('php://input'), true);

    if (!is_array($json)) {
        $json = [];
    }

    return array_merge($_GET, $_POST, $json);
}

function mob_app_json_response($result): void
{
    echo json_encode($result);
}
