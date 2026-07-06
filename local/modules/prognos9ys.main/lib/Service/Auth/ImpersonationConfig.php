<?php

namespace Prognos9ys\Main\Service\Auth;

/**
 * Группа «Супермодераторы» в Bitrix — вход за других пользователей (тесты).
 * При необходимости поменяйте ID под вашу группу.
 */
class ImpersonationConfig
{
    /** Bitrix GROUP_ID группы «Супермодераторы». */
    public const SUPER_MODERATOR_GROUP_ID = 8;

    /** Bitrix GROUP_ID администраторов — за них нельзя входить. */
    public const ADMIN_GROUP_ID = 1;

    /** Роли, которым разрешён вход за другого. */
    public const ALLOWED_ROLES = ['admin', 'super_moder'];

    /**
     * @return int[]
     */
    public static function allowedGroupIds(): array
    {
        return array_unique([
            self::ADMIN_GROUP_ID,
            self::SUPER_MODERATOR_GROUP_ID,
        ]);
    }

    public static function isAdminUser(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $groups = array_map('intval', (array)\CUser::GetUserGroup($userId));

        return in_array(self::ADMIN_GROUP_ID, $groups, true);
    }

    /** Админы и супермодераторы — дашборд посещений экранов. */
    public static function canViewVisitStats(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $groups = array_map('intval', (array)\CUser::GetUserGroup($userId));

        foreach (self::allowedGroupIds() as $groupId) {
            if (in_array((int)$groupId, $groups, true)) {
                return true;
            }
        }

        return false;
    }
}
