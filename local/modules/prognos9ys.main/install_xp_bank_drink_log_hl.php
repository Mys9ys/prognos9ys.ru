<?php

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../../..');
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Prognos9ys\Main\Service\Game\GameEconomyHlInstaller;

try {
    $result = (new GameEconomyHlInstaller())->upgradeXpBankDrinkLogHl();
    echo 'XP bank drink log HL installed:' . PHP_EOL;
    foreach ($result as $key => $value) {
        echo '  ' . $key . ': ' . $value . PHP_EOL;
    }
} catch (\Throwable $e) {
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
