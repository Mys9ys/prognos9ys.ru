<?php

/**
 * CLI: выдать кубки ЧМ-26 тем, кто уже claimed сезонную награду.
 *
 * Пример:
 *   php local/modules/prognos9ys.main/backfill_season_cups.php 63849
 */

declare(strict_types=1);

use Bitrix\Main\Loader;
use Prognos9ys\Main\Service\Game\GameEconomyConfig;
use Prognos9ys\Main\Service\Game\SeasonAwardService;

$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../../..');
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

if (!Loader::includeModule('prognos9ys.main')) {
    fwrite(STDERR, "Module prognos9ys.main not loaded\n");
    exit(1);
}

$eventId = (int)($argv[1] ?? GameEconomyConfig::ANCHOR_EVENT_ID);
if ($eventId <= 0) {
    fwrite(STDERR, "Usage: php backfill_season_cups.php [eventId]\n");
    exit(1);
}

try {
    $result = (new SeasonAwardService())->backfillCupsForEvent($eventId);
    echo json_encode([
        'status' => 'ok',
        'result' => $result,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
