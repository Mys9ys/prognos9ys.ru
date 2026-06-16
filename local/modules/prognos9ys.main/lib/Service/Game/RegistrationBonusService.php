<?php

namespace Prognos9ys\Main\Service\Game;

class RegistrationBonusService
{
    public static function onUserRegistered(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        try {
            (new WalletService())->grantStarterPack($userId);
        } catch (\Throwable $exception) {
            // Кошелёк мог быть создан ранее — не блокируем регистрацию.
        }
    }
}
