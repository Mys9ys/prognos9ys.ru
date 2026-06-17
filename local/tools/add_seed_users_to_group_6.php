<?php
/**
 * Добавляет seed-пользователей ЧМ (игроки, вратари, тренеры) в группу Bitrix ID=6.
 * Не удаляет существующих участников группы — только INSERT для новых связей.
 *
 * CLI (на сервере):
 *   php local/tools/add_seed_users_to_group_6.php --confirm
 *
 * HTTP (одноразово, после деплоя):
 *   GET /mob_app/ajax/maintenance/add_seed_group/?key=...&confirm=1
 */

declare(strict_types=1);

const SEED_GROUP_ID = 6;
const SEED_GROUP_HTTP_KEY = 'wc2026_seed_group6';

/**
 * @return array{group_id:int,found:int,added:int,already:int,users:array<int,array{login:string,email:string}>}
 */
function add_seed_users_to_group_6(int $groupId = SEED_GROUP_ID): array
{
    global $DB;

    if (!class_exists('CUser') || !isset($DB)) {
        throw new RuntimeException('Bitrix is not loaded.');
    }

    $groupId = (int)$groupId;
    if ($groupId <= 0) {
        throw new InvalidArgumentException('Invalid group id.');
    }

    $existingInGroup = [];
    $rsGroupUsers = CGroup::GetGroupUser($groupId);
    foreach ($rsGroupUsers as $uid) {
        $uid = (int)$uid;
        if ($uid > 0) {
            $existingInGroup[$uid] = true;
        }
    }

    $seedUserIds = [];
    $rsUsers = CUser::GetList(
        $by = 'ID',
        $order = 'ASC',
        $filter = ['EMAIL' => '%@prognos9ys.ru']
    );

    while ($row = $rsUsers->Fetch()) {
        $uid = (int)($row['ID'] ?? 0);
        if ($uid > 0) {
            $seedUserIds[$uid] = [
                'login' => (string)($row['LOGIN'] ?? ''),
                'email' => (string)($row['EMAIL'] ?? ''),
            ];
        }
    }

    $added = 0;
    $already = 0;
    $addedUsers = [];

    foreach ($seedUserIds as $uid => $meta) {
        if (isset($existingInGroup[$uid])) {
            $already++;
            continue;
        }

        $arInsert = $DB->PrepareInsert('b_user_group', ['USER_ID' => $uid]);
        $strSql = "
            INSERT INTO b_user_group (
                GROUP_ID, {$arInsert[0]}
            ) VALUES (
                {$groupId}, {$arInsert[1]}
            )";
        $DB->Query($strSql, false, 'FILE: ' . __FILE__ . ' LINE: ' . __LINE__);
        $added++;
        $addedUsers[$uid] = $meta;
    }

    if ($added > 0) {
        CUser::clearUserGroupCache();
    }

    return [
        'group_id' => $groupId,
        'found' => count($seedUserIds),
        'added' => $added,
        'already' => $already,
        'users' => $addedUsers,
    ];
}

if (PHP_SAPI === 'cli' && realpath($argv[0] ?? '') === __FILE__) {
    $docRoot = dirname(__DIR__, 2);
    $_SERVER['DOCUMENT_ROOT'] = $docRoot;
    $_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $_SERVER['SERVER_NAME'] = $_SERVER['SERVER_NAME'] ?? 'localhost';

    define('NO_KEEP_STATISTIC', true);
    define('NO_AGENT_STATISTIC', true);
    define('NOT_CHECK_PERMISSIONS', true);

    require_once $docRoot . '/bitrix/modules/main/include/prolog_before.php';

    if (!in_array('--confirm', $argv, true)) {
        echo "Dry run: найдутся пользователи с EMAIL *@prognos9ys.ru\n";
        echo "Запуск: php local/tools/add_seed_users_to_group_6.php --confirm\n";
        exit(0);
    }

    $result = add_seed_users_to_group_6(SEED_GROUP_ID);
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
}
