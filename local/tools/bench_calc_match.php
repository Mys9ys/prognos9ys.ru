<?php

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

$matchId = (int)($argv[1] ?? 63920);
$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../..');
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

require $_SERVER['DOCUMENT_ROOT'] . '/local/classes/ajax/CalcFootballPrognosisResult.php';

$started = microtime(true);
$result = (new CalcFootballPrognosisResult(['matchId' => $matchId]))->result();
$elapsed = round(microtime(true) - $started, 2);

echo 'match=' . $matchId . ' elapsed=' . $elapsed . 's status=' . ($result['status'] ?? '?') . PHP_EOL;
if (!empty($result['settlement_log']['summary'])) {
    echo json_encode($result['settlement_log']['summary'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
}
