<?php

namespace Prognos9ys\Main\Service\Game;

/**
 * Экономика профессий: оплата труда, XP, комбо, награды за уровень.
 */
class ProfessionEconomyConfig
{
    public const ITERATION_MINUTES = 5;

    public const PAY_TREASURY_PER_ITERATION = 2.0;

    public const FEE_SELF_PER_ITERATION = 0.5;

    public const FREE_ITERATIONS_PER_SESSION = 6;

    public const NOMINAL_RAW = 5.0;

    public const NOMINAL_PROCESSED = 10.0;

    public const NAILS_PER_INGOT = 20;

    public const PROFESSION_LEVEL_ABSOLUTE_MAX = 10;

    /** Премиум-дроп при добыче доступен с этого уровня профессии. */
    public const PREMIUM_DROP_MIN_LEVEL = 2;

    /** XP за 1 ед. обычного ресурса (× комбо). */
    public const XP_PER_NORMAL_UNIT = 2;

    /** XP за 1 премиум (без комбо). */
    public const XP_PER_PREMIUM_UNIT = 5;

    public static function comboDoubleChance(int $level): float
    {
        $level = max(1, $level);

        return min(0.30, 0.06 + ($level - 1) * 0.03);
    }

    public static function comboTripleChance(int $level): float
    {
        if ($level < 3) {
            return 0.0;
        }

        return min(0.05, ($level - 2) * 0.005);
    }

    public static function premiumDropChance(int $level): float
    {
        $level = max(1, $level);

        if ($level < self::PREMIUM_DROP_MIN_LEVEL) {
            return 0.0;
        }

        return 0.0005 + $level * 0.0012;
    }

    /**
     * Шансы комбо и премиума для отображения в UI (уровень 0 → как ур. 1).
     *
     * @return array{combo_x2:float,combo_x3:float,premium:float,premium_min_level:int}
     */
    public static function chancesForLevel(int $level): array
    {
        $level = max(1, $level);

        return [
            'combo_x2' => round(self::comboDoubleChance($level) * 100, 2),
            'combo_x3' => round(self::comboTripleChance($level) * 100, 2),
            'premium' => round(self::premiumDropChance($level) * 100, 3),
            'premium_min_level' => self::PREMIUM_DROP_MIN_LEVEL,
        ];
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
            'cotton' => ['code' => 'silk', 'label' => 'Шёлк', 'nominal' => 75.0],
        ];
    }

    public const PREMIUM_ON_TREASURY_WORK_GOES_TO_PLAYER = true;

    /** Комбо ×2/×3: доп. единицы обычного ресурса игроку, не на госсклад. */
    public const COMBO_BONUS_ON_TREASURY_WORK_GOES_TO_PLAYER = true;

    /**
     * @return array{
     *   prognobaks:float,
     *   rublius:float,
     *   material_qty:int,
     *   chests:int,
     *   title:?string
     * }
     */
    public static function getProfessionLevelReward(int $level): array
    {
        if ($level <= 0) {
            return [
                'prognobaks' => 0.0,
                'rublius' => 0.0,
                'material_qty' => 0,
                'chests' => 0,
                'title' => null,
            ];
        }

        $player = GameEconomyConfig::getLevelUpReward($level);
        $materialQty = ($level % 5 === 0) ? 5 : 3;
        $chests = 1;

        return [
            'prognobaks' => round($player['prognobaks'] * 0.4, 1),
            'rublius' => round($player['rublius'] * 0.4, 1),
            'material_qty' => $materialQty,
            'chests' => $chests,
            'title' => $level === 10 ? 'Мастер' : null,
        ];
    }
}
