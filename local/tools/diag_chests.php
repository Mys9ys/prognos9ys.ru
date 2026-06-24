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

$userId = (int)($_SERVER['argv'][1] ?? 0);
$login = trim((string)($_SERVER['argv'][2] ?? ''));

if ($userId <= 0 && $login !== '') {
    $userRow = \Bitrix\Main\UserTable::getList([
        'filter' => ['=LOGIN' => $login],
        'select' => ['ID', 'LOGIN'],
        'limit' => 1,
    ])->fetch();
    if (!$userRow) {
        echo "User not found: {$login}\n";
        exit(1);
    }
    $userId = (int)$userRow['ID'];
    echo "User {$login} => id {$userId}\n";
}

if ($userId <= 0) {
    echo "Usage: php diag_chests.php <userId>\n";
    echo "   or: php diag_chests.php 0 <login>\n";
    exit(1);
}

$repo = new \Prognos9ys\Main\Model\Repository\GameEconomyRepository();
$treasure = new \Prognos9ys\Main\Service\Game\TreasureService($repo);

echo "\n=== Before migrate ===\n";
printBreakdown($repo, $userId);

$migrated = $treasure->migrateChm2026ChestsForUser($userId);
echo "\nMigrated rows for user: {$migrated}\n";

echo "\n=== After migrate / summary ===\n";
$summary = $treasure->getTreasureSummary($userId);
echo json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";

echo "\n=== Chest rows ===\n";
$dataClass = $repo->getTreasureChestDataClass();
$response = $dataClass::getList([
    'filter' => ['=UF_USER_ID' => $userId],
    'select' => ['ID', 'UF_MATCH_ID', 'UF_TYPE', 'UF_STATUS', 'UF_COUNT', 'UF_EVENT_ID'],
    'order' => ['UF_TYPE' => 'ASC', 'UF_MATCH_ID' => 'ASC'],
]);

while ($row = $response->fetch()) {
    $matchId = (int)($row['UF_MATCH_ID'] ?? 0);
    $hint = \Prognos9ys\Main\Service\Game\TreasureService::describeSyntheticMatchId($matchId) ?? '?';
    echo sprintf(
        "#%d match_id=%d (%s) type=%s status=%s count=%d event=%d\n",
        (int)$row['ID'],
        $matchId,
        $hint,
        (string)($row['UF_TYPE'] ?? ''),
        (string)($row['UF_STATUS'] ?? ''),
        (int)($row['UF_COUNT'] ?? 0),
        (int)($row['UF_EVENT_ID'] ?? 0)
    );
}

$claimMap = $repo->getAchievementClaimMapForUser($userId);
echo "\n=== Achievement claims ===\n";
echo json_encode($claimMap, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";

function printBreakdown(\Prognos9ys\Main\Model\Repository\GameEconomyRepository $repo, int $userId): void
{
    $breakdown = $repo->getTreasureChestBreakdownForUser($userId);
    echo json_encode($breakdown, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
}
