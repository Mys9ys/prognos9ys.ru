<?php

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../../..');
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Prognos9ys\Main\Service\Game\GameEconomyHlInstaller;
use Prognos9ys\Main\Service\Game\TreasureService;

try {
    $result = (new GameEconomyHlInstaller())->upgradeChestOpeningHl();
    echo 'Chest opening HL installed:' . PHP_EOL;
    foreach ($result as $key => $value) {
        echo '  ' . $key . ': ' . $value . PHP_EOL;
    }

    $migrated = (new TreasureService())->migrateChm2026AchievementChestTypes();
    echo 'Migrated chm2026 achievement chests to wc26_achievement: ' . $migrated . PHP_EOL;
} catch (\Throwable $e) {
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
