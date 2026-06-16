<?php
declare(strict_types=1);

/**
 * Пересчёт прогнозов по матчам события в диапазоне номеров.
 *
 * Сначала вручную внесите результат матча в админке (или через FootballSetResult),
 * затем запустите:
 *
 *   php recalc_matches_range.php [eventId] [fromNumber] [toNumber]
 *
 * Пример (ЧМ-2026, матчи 2–10):
 *   php recalc_matches_range.php 63849 2 10
 */

$docRoot = dirname(__DIR__, 2);
$_SERVER['DOCUMENT_ROOT'] = $docRoot;
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['SERVER_NAME'] = $_SERVER['SERVER_NAME'] ?? 'localhost';

define('NO_KEEP_STATISTIC', true);
define('NO_AGENT_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

require_once $docRoot . '/bitrix/modules/main/include/prolog_before.php';

use Prognos9ys\Main\Service\Game\GameEconomyConfig;

if (!\Bitrix\Main\Loader::includeModule('iblock')) {
    echo "iblock module not loaded\n";
    exit(1);
}

$eventId = (int)($argv[1] ?? 63849);
$fromNumber = (int)($argv[2] ?? 2);
$toNumber = (int)($argv[3] ?? 10);

if ($eventId <= 0 || $fromNumber <= 0 || $toNumber < $fromNumber) {
    echo "Usage: php recalc_matches_range.php [eventId] [fromNumber] [toNumber]\n";
    echo "Example: php recalc_matches_range.php 63849 2 10\n";
    exit(1);
}

echo "Event: {$eventId}, matches #{$fromNumber}–#{$toNumber}\n";

if (GameEconomyConfig::isTestMatchNumberLimitEnabled()) {
    echo 'Economy test scope: #'
        . GameEconomyConfig::getTestMatchNumberMin()
        . '–'
        . GameEconomyConfig::getTestMatchNumberMax()
        . PHP_EOL;
}

$matchesIb = (int)(\CIBlock::GetList([], ['CODE' => 'matches'], false)->Fetch()['ID'] ?? 2);

$ok = 0;
$skipped = 0;
$failed = 0;

for ($number = $fromNumber; $number <= $toNumber; $number++) {
    $row = \CIBlockElement::GetList(
        ['ID' => 'ASC'],
        [
            'IBLOCK_ID' => $matchesIb,
            'PROPERTY_events' => $eventId,
            'PROPERTY_number' => $number,
        ],
        false,
        ['nTopCount' => 1],
        [
            'ID',
            'NAME',
            'ACTIVE',
            'PROPERTY_result',
            'PROPERTY_goal_home',
            'PROPERTY_goal_guest',
        ]
    )->GetNext();

    if (!$row) {
        echo "[#{$number}] NOT FOUND\n";
        $failed++;
        continue;
    }

    $matchId = (int)$row['ID'];
    $hasResult = trim((string)($row['PROPERTY_RESULT_VALUE'] ?? '')) !== '';

    if (!$hasResult) {
        echo "[#{$number}] id={$matchId} SKIP — результат не внесён ({$row['NAME']})\n";
        $skipped++;
        continue;
    }

    $score = ($row['PROPERTY_GOAL_HOME_VALUE'] ?? '?') . ':' . ($row['PROPERTY_GOAL_GUEST_VALUE'] ?? '?');
    echo "[#{$number}] id={$matchId} recalc… {$score} — {$row['NAME']}\n";

    try {
        new \CalcFootballPrognosisResult(['matchId' => $matchId]);
        echo "         OK\n";
        $ok++;
    } catch (\Throwable $e) {
        echo '         ERROR: ' . $e->getMessage() . "\n";
        $failed++;
    }
}

echo PHP_EOL . "Done: recalculated={$ok}, skipped(no result)={$skipped}, failed={$failed}\n";
