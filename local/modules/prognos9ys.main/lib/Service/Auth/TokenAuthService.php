<?php

namespace Prognos9ys\Main\Service\Auth;

use Bitrix\Main\UserTable;

class TokenAuthService
{
    private static ?int $currentUserId = null;

    public function getUserIdByToken(?string $token): ?int
    {
        if (!$token) {
            return null;
        }

        $user = UserTable::getRow([
            'select' => ['ID'],
            'filter' => ['=UF_TOKEN' => $token],
        ]);

        return $user ? (int)$user['ID'] : null;
    }

    public static function setCurrentUserId(int $userId): void
    {
        self::$currentUserId = $userId;
    }

    public static function getCurrentUserId(): ?int
    {
        return self::$currentUserId;
    }

    public static function resetCurrentUserId(): void
    {
        self::$currentUserId = null;
    }
}
