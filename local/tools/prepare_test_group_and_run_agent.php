<?php
/**
 * Test helper:
 * 1) Put all active users into Bitrix user group ID=6 ("тестовые")
 * 2) Run the existing football bot prognosis generator (SetBotPrognosis)
 *
 * Intended for one-off manual testing.
 */

declare(strict_types=1);

$docRoot = dirname(__DIR__, 2);
$_SERVER['DOCUMENT_ROOT'] = $docRoot;
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['SERVER_NAME'] = $_SERVER['SERVER_NAME'] ?? 'localhost';

// Reduce background noise during one-off CLI run.
define('NO_KEEP_STATISTIC', true);
define('NO_AGENT_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

require_once $docRoot . '/bitrix/modules/main/include/prolog_before.php';

set_time_limit(0);

if (!class_exists('CUser') || !class_exists('CGroup')) {
    echo "Bitrix core classes are not loaded.\n";
    exit(1);
}

$GROUP_ID = 6;

$existing = CGroup::GetGroupUser($GROUP_ID);
$existingMap = [];
foreach ($existing as $uid) {
    $uid = (int)$uid;
    if ($uid > 0) {
        $existingMap[$uid] = true;
    }
}

$activeUserIds = [];
$rsUsers = CUser::GetList(
    $by = 'ID',
    $order = 'ASC',
    $filter = ['ACTIVE' => 'Y']
);

while ($row = $rsUsers->Fetch()) {
    $uid = (int)($row['ID'] ?? 0);
    if ($uid > 0) {
        $activeUserIds[$uid] = true;
    }
}

$allUserIds = array_unique(array_merge(array_keys($existingMap), array_keys($activeUserIds)));
sort($allUserIds);

echo "Setting group {$GROUP_ID} users. Total: " . count($allUserIds) . "\n";

// Важно: CGroup::Update() требует полей таблицы `b_group` (а мы меняем только состав пользователей),
// из-за чего возможен SQL-ошибочный "UPDATE b_group SET ..." без изменяемых полей.
// Поэтому обновляем связь пользователей с группой напрямую через b_user_group.
global $DB;
if (!isset($DB)) {
    echo "DB connection is not available.\n";
    exit(1);
}

$DB->Query("DELETE FROM b_user_group WHERE GROUP_ID=" . (int)$GROUP_ID, true);

foreach ($allUserIds as $uid) {
    $uid = (int)$uid;
    if ($uid <= 0) {
        continue;
    }

    // PrepareInsert формирует список полей/значений в безопасном виде.
    $arInsert = $DB->PrepareInsert('b_user_group', ['USER_ID' => $uid]);
    $strSql = "
        INSERT INTO b_user_group (
            GROUP_ID, {$arInsert[0]}
        ) VALUES (
            {$GROUP_ID}, {$arInsert[1]}
        )";
    $DB->Query($strSql, false, "FILE: " . __FILE__ . "<br> LINE: " . __LINE__);
}

CUser::clearUserGroupCache();

// Run the existing logic (what AgentFootballBotSetPrognosis() does).
if (!class_exists('SetBotPrognosis')) {
    echo "SetBotPrognosis class is not available.\n";
    exit(1);
}

echo "Running SetBotPrognosis (football bot prognoses)...\n";
new SetBotPrognosis();
echo "Done.\n";

