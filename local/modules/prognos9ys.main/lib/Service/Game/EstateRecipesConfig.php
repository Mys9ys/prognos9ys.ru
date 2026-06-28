<?php

namespace Prognos9ys\Main\Service\Game;

/**
 * Рецепты построек: усадьба игрока и общие здания казны.
 * Стоимость — сумма биржевых номиналов материалов (не прямая оплата казне).
 */
class EstateRecipesConfig
{
    /** Очки прогресса за итерацию сборщика (не слот профессии). */
    public const BUILDER_PROGRESS_PER_ITERATION = 10;

    /** Слоты бригады сборщиков на стройке (своя усадьба). */
    public const BRIGADE_SLOTS_ESTATE_LEVEL_1 = 2;

    public const BRIGADE_SLOTS_ESTATE_LEVEL_2 = 4;

    /** Слоты бригады на общегосударственной стройке. */
    public const BRIGADE_SLOTS_CIVIC = 8;

    /**
     * @return array<string, array{
     *   code:string,
     *   label:string,
     *   kind:string,
     *   progress_total:int,
     *   materials:array<string,int>,
     *   nominal_total:float,
     *   unlock?:string
     * }>
     */
    public static function all(): array
    {
        return [
            'estate_house_1' => self::estateHouse1(),
            'estate_house_2' => self::estateHouse2(),
            'civic_treasury_1' => self::civicTreasury1(),
            'civic_fair_1' => self::civicFair1(),
            'civic_palace_1' => self::civicPalace1(),
        ];
    }

    /**
     * Дом ур.1 — «Избушка», ~3000 🪙 номинала.
     *
     * @return array<string, mixed>
     */
    public static function estateHouse1(): array
    {
        $materials = [
            'plank' => 140,
            'log' => 74,
            'stone_block' => 80,
            'ingot' => 50,
            'window_glass' => 22,
        ];

        return [
            'code' => 'estate_house_1',
            'label' => 'Избушка (дом ур.1)',
            'kind' => 'player_estate',
            'progress_total' => 300,
            'materials' => $materials,
            'nominal_total' => self::calcNominal($materials),
            'unlock' => 'Участок усадьбы: сервант, базовый двор',
        ];
    }

    /**
     * Дом ур.2 — «Дом с мезонином», ~9500 🪙 номинала.
     *
     * @return array<string, mixed>
     */
    public static function estateHouse2(): array
    {
        $materials = [
            'plank' => 480,
            'log' => 154,
            'stone_block' => 230,
            'ingot' => 160,
            'window_glass' => 100,
        ];

        return [
            'code' => 'estate_house_2',
            'label' => 'Дом с мезонином (дом ур.2)',
            'kind' => 'player_estate',
            'requires' => 'estate_house_1',
            'progress_total' => 950,
            'materials' => $materials,
            'nominal_total' => self::calcNominal($materials),
            'unlock' => '+2 слота бригады, хлопковое поле во дворе (позже)',
        ];
    }

    /**
     * Здание казны ур.1 — общий проект, ~12 000 🪙.
     *
     * @return array<string, mixed>
     */
    public static function civicTreasury1(): array
    {
        $materials = [
            'plank' => 550,
            'log' => 220,
            'stone_block' => 400,
            'ingot' => 200,
            'window_glass' => 70,
        ];

        return [
            'code' => 'civic_treasury_1',
            'label' => 'Здание казны',
            'kind' => 'civic',
            'progress_total' => 1200,
            'materials' => $materials,
            'nominal_total' => self::calcNominal($materials),
            'unlock' => 'Вкладка казны: гос. аукцион сырья, прогресс стройки в UI',
        ];
    }

    /**
     * Ярмарка ур.1 — ~28 000 🪙.
     *
     * @return array<string, mixed>
     */
    public static function civicFair1(): array
    {
        $materials = [
            'plank' => 1500,
            'log' => 470,
            'stone_block' => 700,
            'ingot' => 350,
            'window_glass' => 300,
        ];

        return [
            'code' => 'civic_fair_1',
            'label' => 'Ярмарка',
            'kind' => 'civic',
            'requires' => 'civic_treasury_1',
            'progress_total' => 2800,
            'materials' => $materials,
            'nominal_total' => self::calcNominal($materials),
            'unlock' => 'Ряды NPC-торговли ресурсами госсклада, −2% комиссии биржи (кап 15%)',
        ];
    }

    /**
     * Дворец ур.1 — ~75 000 🪙, долгая общая цель.
     *
     * @return array<string, mixed>
     */
    public static function civicPalace1(): array
    {
        $materials = [
            'plank' => 3800,
            'log' => 1200,
            'stone_block' => 2000,
            'ingot' => 1100,
            'window_glass' => 700,
        ];

        return [
            'code' => 'civic_palace_1',
            'label' => 'Дворец',
            'kind' => 'civic',
            'requires' => 'civic_fair_1',
            'progress_total' => 7500,
            'materials' => $materials,
            'nominal_total' => self::calcNominal($materials),
            'unlock' => 'Сезонные награды, зал славы ЧМ, клановые слоты (TBD)',
        ];
    }

    /**
     * @param array<string, int> $materials
     */
    public static function calcNominal(array $materials): float
    {
        $raw = ['log', 'stone', 'ore', 'sand', 'cotton'];
        $total = 0.0;

        foreach ($materials as $code => $qty) {
            $nominal = in_array($code, $raw, true)
                ? ProfessionEconomyConfig::NOMINAL_RAW
                : ProfessionEconomyConfig::NOMINAL_PROCESSED;
            $total += $qty * $nominal;
        }

        return round($total, 1);
    }

    /**
     * Гвозди из слитков в рецепте (не отдельная строка в materials).
     */
    public static function nailsFromIngots(int $ingotCount): int
    {
        return $ingotCount * ProfessionEconomyConfig::NAILS_PER_INGOT;
    }
}
