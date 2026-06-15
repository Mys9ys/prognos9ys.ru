<?php

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
define('BX_CRONTAB', true);

$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../../..');

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

\Bitrix\Main\Loader::includeModule('prognos9ys.main');
\Bitrix\Main\Loader::includeModule('iblock');

$primaryEventId = 63849;
$fallbackEventId = 11744;

echo '=== Football ratings compare ===' . PHP_EOL;

$resultIblockId = (int)(\CIBlock::GetList([], ['CODE' => 'result'], false)->Fetch()['ID'] ?: 7);
$primaryCount = countResultsForEvent($resultIblockId, $primaryEventId);

echo 'event ' . $primaryEventId . ' (ЧМ-2026): ' . $primaryCount . ' result rows' . PHP_EOL;

$eventsToCompare = [$primaryEventId];

if ($primaryCount === 0) {
    echo 'WARNING: no results for ЧМ-2026 in this DB — parity check on event ' . $fallbackEventId . PHP_EOL;
    $eventsToCompare[] = $fallbackEventId;
}

$failed = false;

foreach ($eventsToCompare as $eventId) {
    echo PHP_EOL . '--- event ' . $eventId . ' ---' . PHP_EOL;

    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/test_football_ratings_compare_once.php') . ' ' . (int)$eventId;
    passthru($cmd, $exitCode);

    if ($exitCode !== 0) {
        $failed = true;
    }
}

exit($failed ? 1 : 0);

function countResultsForEvent(int $iblockId, int $eventId): int
{
    return (int)\CIBlockElement::GetList(
        [],
        ['IBLOCK_ID' => $iblockId, 'PROPERTY_events' => $eventId],
        []
    );
}
