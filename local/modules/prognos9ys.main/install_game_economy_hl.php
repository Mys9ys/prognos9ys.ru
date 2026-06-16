<?php

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../../..');
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Prognos9ys\Main\Service\Game\ExperienceService;
use Prognos9ys\Main\Service\Game\GameEconomyConfig;
use Prognos9ys\Main\Service\Game\GameEconomyHlInstaller;
use Prognos9ys\Main\Service\Game\GameEventScopeService;

$syncPending = in_array('--sync-pending', $argv ?? [], true);

try {
    $result = (new GameEconomyHlInstaller())->install();
    echo 'HL blocks installed:' . PHP_EOL;
    foreach ($result as $key => $value) {
        echo '  ' . $key . ': ' . $value . PHP_EOL;
    }

    if ($syncPending) {
        $scope = new GameEventScopeService();
        echo 'Anchor event (ЧМ-2026): ' . $scope->getAnchorEventId() . PHP_EOL;
        echo 'Eligible events: ' . implode(', ', $scope->getEligibleEventIds()) . PHP_EOL;

        if (GameEconomyConfig::isTestMatchNumberLimitEnabled()) {
            echo 'TEST MODE: only match #' . GameEconomyConfig::TEST_ONLY_MATCH_NUMBER . PHP_EOL;
        }

        $count = (new ExperienceService())->syncAllFinishedMatches();
        echo 'Pending XP synced for finished matches (ЧМ-2026+ only): ' . $count . PHP_EOL;
    } else {
        echo PHP_EOL . 'Tip: run with --sync-pending to sync pending XP for ЧМ-2026 and later events only.' . PHP_EOL;
    }
} catch (\Throwable $e) {
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
