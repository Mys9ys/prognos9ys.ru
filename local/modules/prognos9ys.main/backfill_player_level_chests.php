<?php

$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../../..');
$DOCUMENT_ROOT = $_SERVER['DOCUMENT_ROOT'];
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

if (!\Bitrix\Main\Loader::includeModule('prognos9ys.main')) {
    fwrite(STDERR, "Module prognos9ys.main is not installed\n");
    exit(1);
}

use Prognos9ys\Main\Model\Repository\GameEconomyRepository;
use Prognos9ys\Main\Service\Game\LevelUpRewardService;

$limit = 0;
foreach ($argv ?? [] as $arg) {
    if (strpos($arg, '--limit=') === 0) {
        $limit = (int)substr($arg, 8);
    }
}

$repository = new GameEconomyRepository();
$service = new LevelUpRewardService($repository);
$userIds = $repository->getDistinctWalletUserIds();
if ($limit > 0) {
    $userIds = array_slice($userIds, 0, $limit);
}

$usersWithGrants = 0;
$totalChests = 0;

foreach ($userIds as $userId) {
    $userId = (int)$userId;
    if ($userId <= 0) {
        continue;
    }

    $granted = $service->grantMissedLevelChests($userId);
    if ($granted <= 0) {
        continue;
    }

    $usersWithGrants++;
    $totalChests += $granted;
    echo "user #{$userId}: +{$granted} level chest(s)" . PHP_EOL;
}

echo "Users scanned: " . count($userIds) . PHP_EOL;
echo "Users with grants: {$usersWithGrants}" . PHP_EOL;
echo "Total level chests granted: {$totalChests}" . PHP_EOL;

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php';
