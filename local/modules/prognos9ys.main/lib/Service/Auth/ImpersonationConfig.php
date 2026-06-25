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
            1,
            self::SUPER_MODERATOR_GROUP_ID,
        ]);
    }
}
