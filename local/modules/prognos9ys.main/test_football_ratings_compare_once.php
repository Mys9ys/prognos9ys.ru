<?php

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
define('BX_CRONTAB', true);

if ($argc < 2) {
    fwrite(STDERR, "Usage: php test_football_ratings_compare_once.php <eventId>\n");
    exit(2);
}

$eventId = (int)$argv[1];

$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../../..');
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

\Bitrix\Main\Loader::includeModule('prognos9ys.main');

$legacy = new \CreateFootballRatings(['event' => $eventId]);
$legacyPayload = $legacy->result();
$legacyRatings = $legacyPayload['result'] ?? $legacyPayload['ratings'] ?? [];

$newPayload = (new \Prognos9ys\Main\Service\Rating\FootballRatingCalculator())->calculate($eventId);
$newRatings = $newPayload['ratings'] ?? [];

$legacyJson = json_encode($legacyRatings, JSON_UNESCAPED_UNICODE);
$newJson = json_encode($newRatings, JSON_UNESCAPED_UNICODE);

echo 'event ' . $eventId . PHP_EOL;
echo 'legacy categories: ' . implode(', ', array_keys((array)$legacyRatings)) . PHP_EOL;
echo 'new categories: ' . implode(', ', array_keys((array)$newRatings)) . PHP_EOL;

if ($legacyJson === $newJson) {
    echo 'RESULT: OK' . PHP_EOL;
    exit(0);
}

echo 'RESULT: DIFF' . PHP_EOL;
echo 'legacy size: ' . strlen($legacyJson) . ', new size: ' . strlen($newJson) . PHP_EOL;
exit(1);
