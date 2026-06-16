<?php

namespace Prognos9ys\Main\Service\Game;

class GameEconomyConfig
{
    public const START_PROGNOBAKS = 100.0;
    public const START_RUBLIUS = 1.0;
    public const RUBLIUS_TO_PROGNOBAKS = 10;

    public const CURRENCY_PROGNOBAKS = 'prognobaks';
    public const CURRENCY_RUBLIUS = 'rublius';

    public const XP_STATUS_PENDING = 'pending';
    public const XP_STATUS_CLAIMED = 'claimed';
    public const BET_STATUS_PENDING = 'pending';
    public const BET_STATUS_WON = 'won';
    public const BET_STATUS_LOST = 'lost';
    public const BET_STATUS_REFUNDED = 'refunded';
    public const BET_STAKE_PROGNOBAKS = 10.0;
    public const GAME_BANK_CODE_FOOTBALL_PARIMUTUEL = 'football_parimutuel';

    /** ID события-якоря (ЧМ-2026). 0 — автоопределение по названию. */
    public const ANCHOR_EVENT_ID = 63849;

    /**
     * Тестовый режим: опыт только за матч с этим номером.
     * 0 — без ограничения (прод). На локалке для проверки ставим 1.
     */
    public const TEST_ONLY_MATCH_NUMBER = 1;

    public static function isTestMatchNumberLimitEnabled(): bool
    {
        return self::TEST_ONLY_MATCH_NUMBER > 0;
    }

    /**
     * Пороги суммарного опыта для уровней (уровень => min XP).
     * Уровень 0 — до 100, далее по шкале пользователя.
     *
     * @return array<int, int>
     */
    public static function defaultLevelThresholds(): array
    {
        $tiers = [
            0 => 0,
            1 => 100,
            2 => 250,
            3 => 500,
            4 => 1000,
        ];

        for ($level = 5; $level <= 50; $level++) {
            $tiers[$level] = 1000 * ($level - 3);
        }

        return $tiers;
    }
}
