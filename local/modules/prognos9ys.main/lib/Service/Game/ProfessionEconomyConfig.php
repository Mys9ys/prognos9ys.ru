<?php

namespace Prognos9ys\Main\Service\Game;

/**
 * Экономика профессий: единая оплата труда, комбо, премиум-дроп.
 */
class ProfessionEconomyConfig
{
    public const ITERATION_MINUTES = 5;

    /** Оплата за итерацию при работе на казну / заказчика (🪙). */
    public const PAY_TREASURY_PER_ITERATION = 2.0;

    /** Плата игрока за итерацию в режиме «для себя» (🪙). */
    public const FEE_SELF_PER_ITERATION = 0.5;

    /** Итераций без премиума за ~30 мин, затем ручной перезапуск. */
    public const FREE_ITERATIONS_PER_SESSION = 6;

    /** Номинал обычного сырья на бирже (🪙). */
    public const NOMINAL_RAW = 5.0;

    /** Номинал переработанного материала на бирже (🪙). */
    public const NOMINAL_PROCESSED = 9.0;

    /** 1 слиток → гвозди (только для построек, не на биржу в MVP). */
    public const NAILS_PER_INGOT = 20;

    /**
     * Шанс комбо ×2 (0..1) по уровню профессии.
     */
    public static function comboDoubleChance(int $level): float
    {
        $level = max(1, $level);

        return min(0.30, 0.06 + ($level - 1) * 0.03);
    }

    /**
     * Шанс комбо ×3 (0..1) по уровню профессии.
     */
    public static function comboTripleChance(int $level): float
    {
        if ($level < 3) {
            return 0.0;
        }

        return min(0.05, ($level - 2) * 0.005);
    }

    /**
     * Шанс премиум-дропа за итерацию (отдельный ролл, не умножается комбо).
     */
    public static function premiumDropChance(int $level): float
    {
        $level = max(1, $level);

        return (0.0005 + $level * 0.0012);
    }

    /**
     * @return array<string, array{code:string,label:string,nominal:float}>
     */
    public static function premiumMaterialsGathering(): array
    {
        return [
            'wood' => ['code' => 'amber', 'label' => 'Янтарь', 'nominal' => 80.0],
            'stone' => ['code' => 'marble', 'label' => 'Мрамор', 'nominal' => 80.0],
            'ore' => ['code' => 'gold_nugget', 'label' => 'Самородок', 'nominal' => 90.0],
            'sand' => ['code' => 'quartz', 'label' => 'Кварц', 'nominal' => 70.0],
        ];
    }

    /**
     * Премиум при работе на казну всегда остаётся добытчику (обычный дроп — на госсклад).
     */
    public const PREMIUM_ON_TREASURY_WORK_GOES_TO_PLAYER = true;
}
