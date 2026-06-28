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
    /** Свойство прогноза: явный отказ от ставки (галочка снята). Пусто = бот/legacy → backfill при расчёте. */
    public const PROGNOSIS_PROP_BET_ENABLED = 'bet_enabled';
    public const PROGNOSIS_BET_ENABLED_YES = 'Y';
    public const PROGNOSIS_BET_ENABLED_NO = 'N';
    public const GAME_BANK_CODE_FOOTBALL_PARIMUTUEL = 'football_parimutuel';
    public const GAME_BANK_CODE_STATE_TREASURY = 'state_treasury';

    /** Лавка казны: волны сундуков ЧМ-26 (40, 50, 60…). */
    public const TREASURY_SHOP_FIRST_MILESTONE = 40;
    public const TREASURY_SHOP_MILESTONE_STEP = 10;
    public const TREASURY_SHOP_CHEST_PROGNOBAKS_PRICE = 50.0;
    public const TREASURY_SHOP_CHEST_RUBLIUS_PRICE = 5.0;
    /** Премиум 3д/5д — на бирже (в разработке), не в лавке. */
    public const TREASURY_SHOP_PREMIUM_1D_RUBLIUS_PRICE = 3.0;
    /** Для будущей биржи. */
    public const EXCHANGE_PREMIUM_3D_RUBLIUS_PRICE = 7.0;
    public const EXCHANGE_PREMIUM_5D_RUBLIUS_PRICE = 10.0;

    public const CONTRACT_TYPE_REGULAR = 'regular';
    public const CONTRACT_TYPE_GOV_SUPPORT = 'gov_support';
    public const GOV_SUPPORT_DEPOSIT_AMOUNT_PROGNOBAKS = 500.0;
    public const GOV_SUPPORT_DEPOSIT_LARGE_AMOUNT_PROGNOBAKS = 2500.0;
    public const GOV_SUPPORT_DEPOSIT_INTEREST_PERCENT = 5.0;
    public const CONTRACT_STATUS_INTEREST_PAID = 'interest_paid';

    /** Фаза C+ — частные банки и кредиты (см. ROADMAP). */
    public const BANK_OPEN_MIN_WALLET_PROGNOBAKS = 250.0;
    public const BANK_RESERVED_CAPITAL_PROGNOBAKS = 200.0;
    /** @deprecated use BANK_RESERVED_CAPITAL_PROGNOBAKS */
    public const BANK_MIN_CAPITAL_PROGNOBAKS = 200.0;
    public const DEPOSIT_MIN_AMOUNT_PROGNOBAKS = 100.0;
    public const LOAN_MIN_AMOUNT_PROGNOBAKS = 50.0;
    /** Массовый займ модератора: кошелёк ниже порога — выдаём 50 🪙 на ставки. */
    public const MODERATOR_BULK_LOAN_BET_WALLET_MAX = 20.0;
    /** Массовый займ модератора: кошелёк ниже цены сундука в лавке. */
    public const MODERATOR_BULK_LOAN_SHOP_WALLET_MAX = self::TREASURY_SHOP_CHEST_PROGNOBAKS_PRICE;
    public const BANK_TERM_MATCHES = 5;
    /** @deprecated use BANK_TERM_MATCHES */
    public const LOAN_TERM_MATCHES = 5;
    public const LOAN_INTEREST_PERCENT = 15.0;
    public const DEPOSIT_INTEREST_PERCENT = 7.0;
    public const POOR_WALLET_THRESHOLD_PROGNOBAKS = 50.0;

    public const USER_BANK_STATUS_ACTIVE = 'active';
    public const USER_BANK_STATUS_CLOSED = 'closed';
    public const CONTRACT_STATUS_ACTIVE = 'active';
    public const CONTRACT_STATUS_EXTENDED = 'extended';
    public const CONTRACT_STATUS_CLOSED = 'closed';

    /** ID события-якоря (ЧМ-2026). 0 — автоопределение по названию. */
    public const ANCHOR_EVENT_ID = 63849;

    /** true — только якорное событие; false — все турниры с даты ECONOMY_ACTIVITY_SINCE. */
    public const ANCHOR_ONLY_SCOPE = false;

    /** Соревнования с ACTIVE_FROM раньше этой даты не участвуют в игровой экономике. */
    public const ECONOMY_ACTIVITY_SINCE = '2026-06-10';

    public static function getEconomyActivitySinceTimestamp(): int
    {
        static $timestamp = null;

        if ($timestamp === null) {
            $parsed = strtotime(self::ECONOMY_ACTIVITY_SINCE . ' 00:00:00');
            $timestamp = $parsed !== false ? $parsed : 0;
        }

        return $timestamp;
    }

    /**
     * Тестовый режим: экономика только для матчей в диапазоне номеров.
     * MIN=0 и MAX=0 — без ограничения (прод).
     * TEST_ONLY_MATCH_NUMBER > 0 — legacy: один матч (перекрывает диапазон).
     */
    public const TEST_MATCH_NUMBER_MIN = 0;
    public const TEST_MATCH_NUMBER_MAX = 0;
    public const TEST_ONLY_MATCH_NUMBER = 0;

    /** Подробный error_log в AchievementService (тяжёлый на больших турах). */
    public const ACHIEVEMENT_CLAIM_DEBUG = false;

    public static function isAchievementClaimDebugEnabled(): bool
    {
        return self::ACHIEVEMENT_CLAIM_DEBUG;
    }

    public static function isTestMatchNumberLimitEnabled(): bool
    {
        if (self::TEST_ONLY_MATCH_NUMBER > 0) {
            return true;
        }

        return self::TEST_MATCH_NUMBER_MIN > 0 && self::TEST_MATCH_NUMBER_MAX > 0;
    }

    public static function getTestMatchNumberMin(): int
    {
        if (self::TEST_ONLY_MATCH_NUMBER > 0) {
            return self::TEST_ONLY_MATCH_NUMBER;
        }

        return self::TEST_MATCH_NUMBER_MIN;
    }

    public static function getTestMatchNumberMax(): int
    {
        if (self::TEST_ONLY_MATCH_NUMBER > 0) {
            return self::TEST_ONLY_MATCH_NUMBER;
        }

        return self::TEST_MATCH_NUMBER_MAX;
    }

    public static function isMatchNumberInTestScope(int $matchNumber): bool
    {
        if (!self::isTestMatchNumberLimitEnabled()) {
            return true;
        }

        return $matchNumber >= self::getTestMatchNumberMin()
            && $matchNumber <= self::getTestMatchNumberMax();
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

    /**
     * Награда за переход на указанный уровень (не за текущий статус, а за факт апа).
     *
     * @return array{prognobaks: float, rublius: float}
     */
    public static function getLevelUpReward(int $level): array
    {
        if ($level <= 0) {
            return ['prognobaks' => 0.0, 'rublius' => 0.0];
        }

        if ($level <= 5) {
            $baseP = 50.0;
            $baseR = 5.0;
        } elseif ($level <= 10) {
            $baseP = 100.0;
            $baseR = 10.0;
        } else {
            $baseP = 150.0;
            $baseR = 15.0;
        }

        if ($level % 5 === 0) {
            $baseP *= 4;
            $baseR *= 4;
        }

        return [
            'prognobaks' => $baseP,
            'rublius' => $baseR,
        ];
    }

    public static function calculateDepositInterest(float $principal, ?float $ratePercent = null): float
    {
        $rate = $ratePercent ?? self::DEPOSIT_INTEREST_PERCENT;

        return round($principal * $rate / 100, 1);
    }

    public static function calculateGovSupportInterest(float $principal): float
    {
        return self::calculateDepositInterest($principal, self::GOV_SUPPORT_DEPOSIT_INTEREST_PERCENT);
    }

    /**
     * @return float[]
     */
    public static function govSupportDepositAmounts(): array
    {
        return [
            self::GOV_SUPPORT_DEPOSIT_AMOUNT_PROGNOBAKS,
            self::GOV_SUPPORT_DEPOSIT_LARGE_AMOUNT_PROGNOBAKS,
        ];
    }

    public static function resolveGovSupportDepositAmount(float $amount): float
    {
        $amount = round($amount, 1);
        if ($amount <= 0) {
            return self::GOV_SUPPORT_DEPOSIT_AMOUNT_PROGNOBAKS;
        }

        foreach (self::govSupportDepositAmounts() as $allowed) {
            if (abs($amount - $allowed) < 0.01) {
                return $allowed;
            }
        }

        throw new \InvalidArgumentException('Недопустимая сумма гос. вклада');
    }

    /**
     * @return int[]
     */
    public static function getTreasuryShopMilestonesUpTo(int $currentTour): array
    {
        $milestones = [];
        $m = self::TREASURY_SHOP_FIRST_MILESTONE;

        while ($m <= $currentTour && $m <= 200) {
            $milestones[] = $m;
            $m += self::TREASURY_SHOP_MILESTONE_STEP;
        }

        return $milestones;
    }

    public static function calculateLoanInterest(float $principal): float
    {
        return round($principal * self::LOAN_INTEREST_PERCENT / 100, 1);
    }
}
