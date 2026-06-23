<?php
declare(strict_types=1);

/**
 * Массовая выдача пропущенных наград за ачивки (идемпотентно).
 *
 *   php grant_achievement_rewards.php [userId]
 *   php grant_achievement_rewards.php --match <matchId>
 *
 * Без userId — все пользователи, у кого есть хотя бы один результат ЧМ-2026.
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
use Prognos9ys\Main\Service\Game\GameEventScopeService;

if (!\Bitrix\Main\Loader::includeModule('prognos9ys.main')) {
    echo "prognos9ys.main module not loaded\n";
    exit(1);
}

$service = new AchievementService();
$scope = new GameEventScopeService();

if (($argv[1] ?? '') === '--match') {
    $matchId = (int)($argv[2] ?? 0);
    if ($matchId <= 0) {
        echo "Usage: php grant_achievement_rewards.php --match <matchId>\n";
        exit(1);
    }

    $sync = $service->syncAfterMatch($matchId, true);
    echo "Match {$matchId}: users processed " . count($sync['users'] ?? []) . "\n";
    foreach ($sync['users'] ?? [] as $row) {
        $uid = (int)($row['user_id'] ?? 0);
        $cnt = count($row['granted'] ?? []);
        echo "  user {$uid}: granted {$cnt}\n";
        foreach ($row['granted'] ?? [] as $g) {
            echo "    {$g['code']} @{$g['threshold']} => "
                . json_encode($g['reward'] ?? [], JSON_UNESCAPED_UNICODE) . "\n";
        }
    }
    exit(0);
}

$targetUserId = isset($argv[1]) ? (int)$argv[1] : 0;
$userIds = [];

if ($targetUserId > 0) {
    $userIds = [$targetUserId];
} else {
    $eventId = $scope->getAnchorEventId();
    if ($eventId <= 0 || !\Bitrix\Main\Loader::includeModule('iblock')) {
        echo "WC26 event not configured\n";
        exit(1);
    }

    $rs = \CIBlockElement::GetList(
        [],
        [
            'IBLOCK_ID' => 7,
            'PROPERTY_events' => $eventId,
        ],
        false,
        false,
        ['PROPERTY_user_id']
    );

    while ($row = $rs->GetNext()) {
        $uid = (int)($row['PROPERTY_USER_ID_VALUE'] ?? 0);
        if ($uid > 0) {
            $userIds[$uid] = $uid;
        }
    }

    $userIds = array_values($userIds);
}

$totalGranted = 0;

foreach ($userIds as $userId) {
    $granted = $service->grantMissedRewards($userId);
    if (!$granted) {
        continue;
    }

    $totalGranted += count($granted);
    echo "user {$userId}: " . count($granted) . " achievement reward(s)\n";
    foreach ($granted as $row) {
        echo sprintf(
            "  %s @%d => %s\n",
            $row['code'],
            $row['threshold'],
            json_encode($row['reward'] ?? [], JSON_UNESCAPED_UNICODE)
        );
    }
}

echo "Done. Granted entries: {$totalGranted}\n";
