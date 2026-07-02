<?php

namespace Prognos9ys\Main\Service\Game;

/**
 * Массовая добыча и крафт на казну: циклы (1 / полная смена) и аудитория.
 */
class FarmBulkActionConfig
{
    public const SCOPE_ALL = 'all';
    public const SCOPE_POOR = 'poor';
    public const SCOPE_INDEBTED = 'indebted';

    /**
     * @return string[]
     */
    public static function treasuryActionIds(): array
    {
        return [
            'farm_treasury_1',
            'farm_treasury_5',
            'farm_treasury_1_poor',
            'farm_treasury_5_poor',
            'farm_treasury_1_indebted',
            'farm_treasury_5_indebted',
            'farm_treasury_gather',
        ];
    }

    public static function isTreasuryAction(string $action): bool
    {
        return self::parseTreasuryAction($action) !== null;
    }

    /**
     * @return array{iterations:int,scope:string}|null
     */
    public static function parseTreasuryAction(string $action): ?array
    {
        $map = [
            'farm_treasury_1' => ['iterations' => 1, 'scope' => self::SCOPE_ALL],
            'farm_treasury_5' => [
                'iterations' => ProfessionEconomyConfig::FREE_ITERATIONS_PER_SESSION,
                'scope' => self::SCOPE_ALL,
            ],
            'farm_treasury_gather' => [
                'iterations' => ProfessionEconomyConfig::FREE_ITERATIONS_PER_SESSION,
                'scope' => self::SCOPE_ALL,
            ],
            'farm_treasury_1_poor' => ['iterations' => 1, 'scope' => self::SCOPE_POOR],
            'farm_treasury_5_poor' => [
                'iterations' => ProfessionEconomyConfig::FREE_ITERATIONS_PER_SESSION,
                'scope' => self::SCOPE_POOR,
            ],
            'farm_treasury_1_indebted' => ['iterations' => 1, 'scope' => self::SCOPE_INDEBTED],
            'farm_treasury_5_indebted' => [
                'iterations' => ProfessionEconomyConfig::FREE_ITERATIONS_PER_SESSION,
                'scope' => self::SCOPE_INDEBTED,
            ],
        ];

        return $map[$action] ?? null;
    }

    /**
     * @return string[]
     */
    public static function treasuryCraftActionIds(): array
    {
        return [
            'farm_treasury_craft_1',
            'farm_treasury_craft_5',
        ];
    }

    public static function isTreasuryCraftAction(string $action): bool
    {
        return self::parseTreasuryCraftAction($action) !== null;
    }

    /**
     * @return array{iterations:int,scope:string}|null
     */
    public static function parseTreasuryCraftAction(string $action): ?array
    {
        $map = [
            'farm_treasury_craft_1' => ['iterations' => 1, 'scope' => self::SCOPE_ALL],
            'farm_treasury_craft_5' => [
                'iterations' => ProfessionEconomyConfig::FREE_ITERATIONS_PER_SESSION,
                'scope' => self::SCOPE_ALL,
            ],
        ];

        return $map[$action] ?? null;
    }

    public static function moderatorTitle(string $action): string
    {
        $craftParsed = self::parseTreasuryCraftAction($action);
        if ($craftParsed) {
            $cycles = (int)$craftParsed['iterations'];

            return 'Крафт ×' . $cycles . ' (всем)';
        }

        $parsed = self::parseTreasuryAction($action);
        if (!$parsed) {
            return 'Добыча на казну';
        }

        $cycles = (int)$parsed['iterations'];
        $scopeLabel = [
            self::SCOPE_ALL => 'всем',
            self::SCOPE_POOR => 'бедным',
            self::SCOPE_INDEBTED => 'в долгах',
        ][$parsed['scope']] ?? 'всем';

        return 'Добыча ×' . $cycles . ' (' . $scopeLabel . ')';
    }

    public static function moderatorConfirm(string $action): string
    {
        $craftParsed = self::parseTreasuryCraftAction($action);
        if ($craftParsed) {
            $cycles = (int)$craftParsed['iterations'];
            $payPerUser = $cycles * ProfessionEconomyConfig::PAY_TREASURY_PER_ITERATION;

            return 'Мгновенный крафт на казну (×' . $cycles . ' цикл'
                . ($cycles > 1 ? 'а' : '')
                . ', +' . $payPerUser . ' 🪙 каждому) по профессии обработки игрока?'
                . ' Сырьё — с госсклада или по заказу казны на бирже.'
                . ' Пропуск — нет обработки, смена активна или нет заказа/сырья.';
        }

        $parsed = self::parseTreasuryAction($action);
        if (!$parsed) {
            return 'Запустить массовую добычу на казну?';
        }

        $cycles = (int)$parsed['iterations'];
        $payPerUser = $cycles * ProfessionEconomyConfig::PAY_TREASURY_PER_ITERATION;
        $scopeText = [
            self::SCOPE_ALL => 'всем игрокам с кошельком',
            self::SCOPE_POOR => 'игрокам с менее чем '
                . (int)GameEconomyConfig::MODERATOR_BULK_LOAN_SHOP_WALLET_MAX
                . ' 🪙 на руках',
            self::SCOPE_INDEBTED => 'игрокам с активным банковским займом',
        ][$parsed['scope']] ?? 'всем';

        return 'Мгновенная смена на казну (×' . $cycles . ' цикл'
            . ($cycles > 1 ? 'а' : '')
            . ', +' . $payPerUser . ' 🪙 каждому) для: ' . $scopeText
            . '? Пропуск — если уже идёт смена или в казне не хватает монет.';
    }
}
