<?php

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

\Bitrix\Main\Loader::includeModule('prognos9ys.main');

$request = array_merge($_GET, $_POST);

if (!empty($request['event'])) {
    echo json_encode(
        (new \Prognos9ys\Main\Service\Football\FootballRatingService())->getByEvent($request['event']),
        JSON_UNESCAPED_UNICODE
    );
}
