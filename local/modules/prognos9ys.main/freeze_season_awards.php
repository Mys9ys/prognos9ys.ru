<?php

/**
 * CLI: freeze сезонных наград по финальному рейтингу.
 *
 * Пример:
 *   php local/modules/prognos9ys.main/freeze_season_awards.php 63849
 *   php local/modules/prognos9ys.main/freeze_season_awards.php 63849 --force
 *   php local/modules/prognos9ys.main/freeze_season_awards.php 63849 --append
 *
 * --force: удаляет pending-записи события и пишет заново (если есть claimed — отказ).
 * --append: дописывает только новые номинации (не трогает уже замороженные).
 */

declare(strict_types=1);

use Bitrix\Main\Loader;
use Prognos9ys\Main\Service\Game\GameEconomyConfig;
use Prognos9ys\Main\Service\Game\SeasonAwardService;

@ini_set('memory_limit', '1024M');
@set_time_limit(0);

$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../../..');
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

if (!Loader::includeModule('prognos9ys.main')) {
    fwrite(STDERR, "Module prognos9ys.main not loaded\n");
    exit(1);
}

$eventId = 0;
$force = false;
$append = false;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--force') {
        $force = true;
        continue;
    }
    if ($arg === '--append') {
        $append = true;
        continue;
    }
    if (ctype_digit((string)$arg)) {
        $eventId = (int)$arg;
    }
}

if ($eventId <= 0) {
    $eventId = GameEconomyConfig::ANCHOR_EVENT_ID;
}

if ($eventId <= 0) {
    fwrite(STDERR, "Usage: php freeze_season_awards.php [eventId] [--force|--append]\n");
    fwrite(STDERR, "Example: php freeze_season_awards.php 63849 --append\n");
    exit(1);
}

try {
    $result = (new SeasonAwardService())->freezeEvent($eventId, $force, $append);
    echo json_encode([
        'status' => 'ok',
        'result' => $result,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
