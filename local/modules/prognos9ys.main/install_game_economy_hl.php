<?php

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../../..');
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Prognos9ys\Main\Model\Repository\GameEconomyRepository;
use Prognos9ys\Main\Service\Game\ExperienceService;
use Prognos9ys\Main\Service\Game\GameEconomyConfig;
use Prognos9ys\Main\Service\Game\GameEconomyHlInstaller;
use Prognos9ys\Main\Service\Game\GameEventScopeService;
use Prognos9ys\Main\Service\Game\RegistrationBonusService;

$syncPending = in_array('--sync-pending', $argv ?? [], true);
$grantExistingUsers = in_array('--grant-existing-users', $argv ?? [], true);

try {
    $installer = new GameEconomyHlInstaller();
    $result = $installer->install();
    $installer->upgradeWalletPremiumHl();
    $installer->upgradePremiumWorkQueueHl();
    $installer->upgradeWalletEquipmentHl();
    $installer->upgradeScreenVisitLogHl();
    GameEconomyRepository::resetWalletDataClassCache();
    echo 'HL blocks installed:' . PHP_EOL;
    foreach ($result as $key => $value) {
        echo '  ' . $key . ': ' . $value . PHP_EOL;
    }

    if ($syncPending) {
        $scope = new GameEventScopeService();
        echo 'Anchor event (ЧМ-2026): ' . $scope->getAnchorEventId() . PHP_EOL;
        echo 'Eligible events: ' . implode(', ', $scope->getEligibleEventIds()) . PHP_EOL;

        if (GameEconomyConfig::isTestMatchNumberLimitEnabled()) {
            echo 'TEST MODE: matches #'
                . GameEconomyConfig::getTestMatchNumberMin()
                . '–'
                . GameEconomyConfig::getTestMatchNumberMax()
                . PHP_EOL;
        }

        $count = (new ExperienceService())->syncAllFinishedMatches();
        echo 'Pending XP synced for finished matches (ЧМ-2026+ only): ' . $count . PHP_EOL;
    }

    if ($grantExistingUsers) {
        $stats = RegistrationBonusService::grantForExistingUsers();
        echo 'Starter pack for existing users:' . PHP_EOL;
        echo '  processed: ' . $stats['processed'] . PHP_EOL;
        echo '  granted: ' . $stats['granted'] . PHP_EOL;
        echo '  failed: ' . $stats['failed'] . PHP_EOL;
    }

    if (!$syncPending && !$grantExistingUsers) {
        echo PHP_EOL
            . 'Tip: run with --sync-pending to sync pending XP for ЧМ-2026 and later events only.'
            . PHP_EOL;
        echo 'Tip: run with --grant-existing-users to grant starter pack for active users without wallet.' . PHP_EOL;
    }
} catch (\Throwable $e) {
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
