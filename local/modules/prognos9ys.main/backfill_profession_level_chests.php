<?php

$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../../..');
$DOCUMENT_ROOT = $_SERVER['DOCUMENT_ROOT'];
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

if (!\Bitrix\Main\Loader::includeModule('prognos9ys.main')) {
    fwrite(STDERR, "Module prognos9ys.main is not installed\n");
    exit(1);
}

$limit = 0;
foreach ($argv ?? [] as $arg) {
    if (strpos($arg, '--limit=') === 0) {
        $limit = (int)substr($arg, 8);
    }
}

$service = new \Prognos9ys\Main\Service\Game\ProfessionLevelRewardService();
$result = $service->backfillLevelChestsForAll($limit);

echo "Users scanned: " . (int)($result['users_scanned'] ?? 0) . PHP_EOL;
echo "Users with grants: " . (int)($result['granted_rows'] ?? 0) . PHP_EOL;
echo "Total chests granted: " . (int)($result['granted_chests'] ?? 0) . PHP_EOL;

foreach (($result['details'] ?? []) as $row) {
    $userId = (int)($row['user_id'] ?? 0);
    $granted = (int)($row['granted_chests'] ?? 0);
    $lines = (array)($row['lines'] ?? []);
    echo "user #{$userId}: +{$granted} chest(s)" . PHP_EOL;
    foreach ($lines as $line) {
        echo "  - " . $line . PHP_EOL;
    }
}

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php';
