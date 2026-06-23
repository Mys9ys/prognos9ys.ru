<?php
declare(strict_types=1);

$docRoot = dirname(__DIR__, 2);
$_SERVER['DOCUMENT_ROOT'] = $docRoot;
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['SERVER_NAME'] = $_SERVER['SERVER_NAME'] ?? 'localhost';

define('NO_KEEP_STATISTIC', true);
define('NO_AGENT_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

require_once $docRoot . '/bitrix/modules/main/include/prolog_before.php';

if (!\Bitrix\Main\Loader::includeModule('prognos9ys.main')) {
    echo "module prognos9ys.main not loaded\n";
    exit(1);
}

$userId = (int)($_SERVER['argv'][1] ?? 1);
if ($userId <= 0) {
    echo "Usage: php diag_user_achievements.php [userId]\n";
    exit(1);
}

$service = new \Prognos9ys\Main\Service\Game\AchievementService();
$data = $service->getForUser($userId);

echo "=== Achievements user {$userId} ===\n";
$scope = new \Prognos9ys\Main\Service\Game\GameEventScopeService();
echo 'WC26 event id: ' . $scope->getAnchorEventId() . ' (' . $scope->getEventName($scope->getAnchorEventId()) . ")\n\n";
echo "STATS:\n";
echo json_encode($data['stats'] ?? [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n";

echo str_pad('CODE', 22) . str_pad('TITLE', 22) . str_pad('PROG', 6) . "LEVELS / claim\n";
echo str_repeat('-', 90) . "\n";

foreach ($data['items'] ?? [] as $item) {
    $levels = array_map(static fn($l) => (int)($l['threshold'] ?? 0), $item['levels'] ?? []);
    $levelStr = implode('/', $levels);
    $claim = (int)($item['claimed_threshold'] ?? 0);
    $next = (int)($item['next_claimable_threshold'] ?? 0);
    $max = (int)($item['max_unlocked_threshold'] ?? 0);

    $mark = '';
    if ($next > 0) {
        $reward = $item['next_reward'] ?? [];
        $rewardBits = [];
        if (!empty($reward['rublius'])) {
            $rewardBits[] = 'R' . $reward['rublius'];
        }
        if (!empty($reward['chests'])) {
            $rewardBits[] = 'C' . $reward['chests'];
        }
        if (!empty($reward['pennant'])) {
            $rewardBits[] = 'P:' . $reward['pennant'];
        }
        $rewardStr = $rewardBits ? ' [' . implode('+', $rewardBits) . ']' : '';
        $mark = " *claim@{$next}{$rewardStr}";
    } elseif ($max > 0) {
        $mark = " ok≤{$max}";
    }

    echo str_pad((string)($item['code'] ?? ''), 22)
        . str_pad(mb_substr((string)($item['title'] ?? ''), 0, 20), 22)
        . str_pad((string)($item['progress'] ?? 0), 6)
        . $levelStr
        . " claimed={$claim}"
        . $mark
        . "\n";
}

echo "\nTotal items: " . (int)($data['total_count'] ?? 0) . "\n";
