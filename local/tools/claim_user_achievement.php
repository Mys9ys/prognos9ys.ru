<?php
declare(strict_types=1);

/**
 * Тест/ручная выдача награды за ачивку.
 *
 *   php claim_user_achievement.php <userId> <code>
 *   php claim_user_achievement.php <userId> --all
 *   php claim_user_achievement.php <userId> --dry-run
 *
 * Примеры:
 *   php claim_user_achievement.php 1 welcome
 *   php claim_user_achievement.php 1 --all
 */

$docRoot = dirname(__DIR__, 2);
$_SERVER['DOCUMENT_ROOT'] = $docRoot;
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['SERVER_NAME'] = $_SERVER['SERVER_NAME'] ?? 'localhost';

define('NO_KEEP_STATISTIC', true);
define('NO_AGENT_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

require_once $docRoot . '/bitrix/modules/main/include/prolog_before.php';

use Prognos9ys\Main\Service\Game\AchievementService;

if (!\Bitrix\Main\Loader::includeModule('prognos9ys.main')) {
    echo "prognos9ys.main module not loaded\n";
    exit(1);
}

$userId = (int)($argv[1] ?? 0);
$codeArg = (string)($argv[2] ?? '');

if ($userId <= 0) {
    echo "Usage: php claim_user_achievement.php <userId> <code|--all|--dry-run>\n";
    exit(1);
}

$service = new AchievementService();
$dryRun = in_array('--dry-run', $argv, true) || $codeArg === '--dry-run';
$claimAll = in_array('--all', $argv, true) || $codeArg === '--all';

if ($dryRun) {
    $claimable = $service->getClaimableItems($userId);
    echo "=== Claimable for user {$userId} (dry-run) ===\n";
    if (!$claimable) {
        echo "Nothing to claim.\n";
        exit(0);
    }

    foreach ($claimable as $item) {
        echo sprintf(
            "  %s @%d: %s\n",
            $item['code'],
            $item['threshold'],
            json_encode($item['reward'] ?? [], JSON_UNESCAPED_UNICODE)
        );
    }
    exit(0);
}

if ($claimAll) {
    $granted = $service->claimAllAvailable($userId);
    echo "=== Claimed all for user {$userId}: " . count($granted) . " ===\n";
    foreach ($granted as $row) {
        echo sprintf(
            "  %s @%d => %s\n",
            $row['code'],
            $row['threshold'],
            json_encode($row['reward'] ?? [], JSON_UNESCAPED_UNICODE)
        );
    }
    exit(0);
}

if ($codeArg === '') {
    echo "Specify achievement code or --all / --dry-run\n";
    exit(1);
}

try {
    $claimed = $service->claimNext($userId, $codeArg);
} catch (\Throwable $exception) {
    echo 'ERROR: ' . $exception->getMessage() . "\n";
    exit(1);
}

if (!$claimed) {
    echo "Nothing claimed.\n";
    exit(0);
}

echo sprintf(
    "OK user=%d %s @%d => %s\n",
    $userId,
    $claimed['code'],
    $claimed['threshold'],
    json_encode($claimed['reward'] ?? [], JSON_UNESCAPED_UNICODE)
);
