<?php

namespace Prognos9ys\Main\Service\Game;

use Bitrix\Main\UserTable;

class SeedUserGroupService
{
    public const TEST_GROUP_ID = 6;

    public static function onUserRegistered(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        try {
            $row = UserTable::getList([
                'select' => ['ID', 'EMAIL', 'LOGIN'],
                'filter' => ['=ID' => $userId],
                'limit' => 1,
            ])->fetch();

            if (!$row || !self::isSeedAccount($row)) {
                return;
            }

            self::addUserToTestGroup($userId);
        } catch (\Throwable $exception) {
            // Не блокируем регистрацию.
        }
    }

    /**
     * @param array<string, mixed> $userRow
     */
    public static function isSeedAccount(array $userRow): bool
    {
        $email = strtolower(trim((string)($userRow['EMAIL'] ?? '')));
        $login = strtolower(trim((string)($userRow['LOGIN'] ?? '')));

        if ($email !== '' && substr($email, -16) === '@prognos9ys.ru') {
            return true;
        }

        return (bool)preg_match('/^(gk|coach|cs2p_|cs2c_)/i', $login);
    }

    public static function addUserToTestGroup(int $userId, int $groupId = self::TEST_GROUP_ID): bool
    {
        if ($userId <= 0 || $groupId <= 0) {
            return false;
        }

        global $DB;
        if (!isset($DB)) {
            return false;
        }

        $existing = \CGroup::GetGroupUser($groupId);
        foreach ($existing as $uid) {
            if ((int)$uid === $userId) {
                return false;
            }
        }

        $arInsert = $DB->PrepareInsert('b_user_group', ['USER_ID' => $userId]);
        $strSql = "
            INSERT INTO b_user_group (
                GROUP_ID, {$arInsert[0]}
            ) VALUES (
                {$groupId}, {$arInsert[1]}
            )";
        $DB->Query($strSql, false, 'FILE: ' . __FILE__ . ' LINE: ' . __LINE__);
        \CUser::clearUserGroupCache();

        return true;
    }
}
