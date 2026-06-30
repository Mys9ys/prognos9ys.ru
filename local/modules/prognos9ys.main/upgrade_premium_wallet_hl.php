<?php

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../../..');
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Prognos9ys\Main\Model\Repository\GameEconomyRepository;
use Prognos9ys\Main\Service\Game\GameEconomyHlInstaller;

try {
    (new GameEconomyHlInstaller())->upgradeWalletPremiumHl();
    $repository = new GameEconomyRepository();
    $repository->ensurePremiumWalletSchema();

    echo 'OK: UF_PREMIUM_UNTIL on prognos9ys_user_wallet' . PHP_EOL;
} catch (\Throwable $e) {
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
