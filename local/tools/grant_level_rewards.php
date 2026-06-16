<?php
declare(strict_types=1);

/**
 * Разовая выдача пропущенных наград за уровни (деньги + сундучки).
 * Идемпотентно: повторный запуск безопасен.
 *
 *   php grant_level_rewards.php [userId]
 *
 * Без userId — обработать всех пользователей с прогрессом > 0.
 */

$docRoot = dirname(__DIR__, 2);
$_SERVER['DOCUMENT_ROOT'] = $docRoot;
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['SERVER_NAME'] = $_SERVER['SERVER_NAME'] ?? 'localhost';

define('NO_KEEP_STATISTIC', true);
define('NO_AGENT_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

require_once $docRoot . '/bitrix/modules/main/include/prolog_before.php';

use Prognos9ys\Main\Model\Repository\GameEconomyRepository;
use Prognos9ys\Main\Service\Game\LevelUpRewardService;

if (!\Bitrix\Main\Loader::includeModule('prognos9ys.main')) {
    echo "prognos9ys.main module not loaded\n";
    exit(1);
}

$targetUserId = isset($argv[1]) ? (int)$argv[1] : 0;
$repository = new GameEconomyRepository();
$service = new LevelUpRewardService($repository);

$userIds = [];

if ($targetUserId > 0) {
    $userIds = [$targetUserId];
} else {
    $dataClass = $repository->getUserProgressDataClass();
    $rs = $dataClass::getList([
        'filter' => ['>UF_XP' => 0],
        'select' => ['UF_USER_ID', 'UF_XP'],
    ]);

    while ($row = $rs->fetch()) {
        $userIds[] = (int)$row['UF_USER_ID'];
    }

    $userIds = array_values(array_unique($userIds));
}

$totalGranted = 0;

foreach ($userIds as $userId) {
    $granted = $service->grantMissedRewards($userId);

    if (!$granted) {
        continue;
    }

    $totalGranted += count($granted);
    echo "user {$userId}: " . count($granted) . " level reward(s)\n";

    foreach ($granted as $reward) {
        echo "  lvl {$reward['level']}: +{$reward['prognobaks']} prognobaks, +{$reward['rublius']} rublius, chests {$reward['chests']}\n";
    }
}

echo "Done. Granted entries: {$totalGranted}\n";
