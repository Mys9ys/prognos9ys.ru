<?php

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../../..');
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

\Bitrix\Main\Loader::includeModule('prognos9ys.main');

$userId = 1;
$token = \Bitrix\Main\UserTable::getRow([
    'filter' => ['=ID' => $userId],
    'select' => ['UF_TOKEN'],
])['UF_TOKEN'] ?? '';

echo 'token: ' . substr($token, 0, 12) . '...' . PHP_EOL;

$matchService = new \Prognos9ys\Main\Service\Football\FootballMatchService();
$prognosisService = new \Prognos9ys\Main\Service\Football\FootballPrognosisService();

$testMatch = null;

foreach ([34, 63849] as $eventId) {
    for ($number = 1; $number <= 20; $number++) {
        $matchResponse = $matchService->getMatch((string)$eventId, (string)$number, $token);
        if (($matchResponse['status'] ?? '') !== 'ok') {
            continue;
        }

        $match = $matchResponse['result'];
        if (($match['active'] ?? '') === 'Y') {
            $testMatch = $match;
            echo "Active match: event={$eventId} number={$number} id={$match['id']}" . PHP_EOL;
            break 2;
        }
    }
}

if (!$testMatch) {
    echo "No active match found, using event 34 number 1 if exists" . PHP_EOL;
    $matchResponse = $matchService->getMatch('34', '1', $token);
    $testMatch = $matchResponse['result'] ?? null;
}

if (!$testMatch) {
    echo "No match for test" . PHP_EOL;
    exit(1);
}

$fields = [
    30 => $testMatch['number'] ?? 1,
    17 => $testMatch['id'],
    15 => 2,
    16 => 1,
    18 => 'п1',
    19 => 1,
    28 => 3,
    32 => 55,
    21 => 2,
    22 => 0,
    20 => 5,
    23 => 0,
    52 => $testMatch['event_id'] ?? 34,
    45 => '',
    46 => '',
    29 => '',
];

echo 'Sending prognosis for match id=' . $fields[17] . PHP_EOL;

$legacy = (new FootballSendPrognosis([
    'userToken' => $token,
    'fields' => $fields,
]))->result();
echo 'Legacy: ' . json_encode($legacy, JSON_UNESCAPED_UNICODE) . PHP_EOL;

$service = $prognosisService->send($token, $fields);
echo 'Service: ' . json_encode($service, JSON_UNESCAPED_UNICODE) . PHP_EOL;

$controller = new \Prognos9ys\Main\Controller\FootballController();
\Prognos9ys\Main\Service\Auth\TokenAuthService::setCurrentUserId($userId);
$action = $controller->sendPrognosisAction($fields, $token);
echo 'Controller: ' . json_encode($action, JSON_UNESCAPED_UNICODE) . PHP_EOL;
