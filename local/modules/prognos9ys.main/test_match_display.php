<?php

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

$eventId = 34;
$number = 11;
$userId = 1;

$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../../..');
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

$token = \Bitrix\Main\UserTable::getRow([
    'filter' => ['=ID' => $userId],
    'select' => ['UF_TOKEN'],
])['UF_TOKEN'] ?? '';

$handler = new FootballMatchLoadInfo([
    'eventId' => $eventId,
    'number' => $number,
    'userToken' => $token,
]);

$result = $handler->result()['result'] ?? [];

echo json_encode([
    'match_result' => $result['match_result'] ?? null,
    'prognosis' => $result['prognosis'] ?? null,
    'prog_result' => $result['prog_result'] ?? null,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
