<?php

namespace Prognos9ys\Main\Service\Game;

/**
 * Проекты построек: усадьба игрока, госздания города ЧМ-26, мегапроекты столицы.
 * Стоимость — сумма биржевых номиналов компонентов (крафтовые детали) или сырья (legacy).
 */
class EstateRecipesConfig
{
    /** @deprecated Монтаж без бригады; поле сохранено для совместимости API. */
    public const BUILDER_PROGRESS_PER_ITERATION = 10;

    /** @deprecated */
    public const BRIGADE_SLOTS_ESTATE_LEVEL_1 = 2;

    /** @deprecated */
    public const BRIGADE_SLOTS_ESTATE_LEVEL_2 = 4;

    /** @deprecated */
    public const BRIGADE_SLOTS_CIVIC = 8;

    /** +13% к номиналу компонентных проектов (компенсация за монтаж без бригады). */
    public const COMPONENT_NOMINAL_SURCHARGE = 1.13;

    /**
     * @return array<string, array{
     *   code:string,
     *   label:string,
     *   label_ru:string,
     *   kind:string,
     *   progress_total:int,
     *   components?:array<string,int>,
     *   materials?:array<string,int>,
     *   nominal_total:float,
     *   requires?:string,
     *   unlock?:string,
     *   opens_city_map?:bool
     * }>
     */
    public static function all(): array
    {
        return [
            'estate_fence_1' => self::estateFence1(),
            'estate_house_1' => self::estateHouse1(),
            'estate_house_2' => self::estateHouse2(),
            'civic_city_hall' => self::civicCityHall(),
            'civic_exchange_branch' => self::civicExchangeBranch(),
            'civic_bank_branch' => self::civicBankBranch(),
            'civic_treasury_1' => self::civicTreasury1(),
            'civic_fair_1' => self::civicFair1(),
            'civic_palace_1' => self::civicPalace1(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function estateFence1(): array
    {
        $components = [
            'fence_panel' => 18,
            'wall_section_fence' => 5,
            'wall_section_door' => 1,
            'door' => 1,
            'rope' => 7,
        ];

        return self::projectRow(
            'estate_fence_1',
            'Ограда',
            'player_estate',
            $components,
            [],
            null,
            'Огороженный двор, можно строить дом'
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function estateHouse1(): array
    {
        $components = [
            'foundation_block' => 16,
            'wall_section' => 14,
            'wall_section_corner' => 5,
            'wall_section_window' => 3,
            'wall_section_door' => 1,
            'roof_bundle' => 1,
            'door' => 1,
            'window_regular' => 3,
            'arch_lintel' => 3,
            'threshold' => 3,
            'bracket' => 9,
            'beam' => 5,
        ];

        return self::projectRow(
            'estate_house_1',
            'Дом трёхкомнатный (ур.1)',
            'player_estate',
            $components,
            [],
            'estate_fence_1',
            'Прихожая, комната, зал; двор под коллекцию'
        );
    }

    /**
     * Дом ур.2 — сырьевой legacy-рецепт до проектирования тира с черепицей.
     *
     * @return array<string, mixed>
     */
    public static function estateHouse2(): array
    {
        $materials = [
            'plank' => 543,
            'log' => 174,
            'stone_block' => 260,
            'ingot' => 181,
            'window_glass' => 113,
        ];

        return self::projectRow(
            'estate_house_2',
            'Дом с мезонином (ур.2)',
            'player_estate',
            [],
            $materials,
            'estate_house_1',
            'Черепица, расширенный двор (TBD)'
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function civicCityHall(): array
    {
        $components = [
            'foundation_block' => 14,
            'wall_section' => 12,
            'wall_section_corner' => 5,
            'roof_bundle' => 1,
            'door' => 2,
            'window_regular' => 6,
            'arch_lintel' => 3,
            'threshold' => 3,
            'bracket' => 9,
            'beam' => 7,
        ];

        return self::projectRow(
            'civic_city_hall',
            'Управа',
            'civic_city',
            $components,
            [],
            null,
            'Город на карте; лицензии на участки 1–10',
            true
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function civicExchangeBranch(): array
    {
        $components = [
            'foundation_block' => 7,
            'wall_section' => 9,
            'roof_bundle_light' => 1,
            'window_regular' => 5,
            'door' => 1,
            'threshold' => 1,
            'arch_lintel' => 1,
            'bracket' => 5,
        ];

        return self::projectRow(
            'civic_exchange_branch',
            'Филиал биржи',
            'civic_city',
            $components,
            [],
            null,
            'Косметика филиала биржи в городе'
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function civicBankBranch(): array
    {
        $components = [
            'foundation_block' => 8,
            'wall_section' => 10,
            'roof_bundle_light' => 1,
            'window_regular' => 3,
            'door' => 2,
            'threshold' => 2,
            'bracket' => 7,
        ];

        return self::projectRow(
            'civic_bank_branch',
            'Филиал банка',
            'civic_city',
            $components,
            [],
            null,
            'Косметика филиала банка; прописка 50 🪙'
        );
    }

    /**
     * Мегапроект столицы (чудо) — legacy сырьё.
     *
     * @return array<string, mixed>
     */
    public static function civicTreasury1(): array
    {
        $materials = [
            'plank' => 622,
            'log' => 249,
            'stone_block' => 452,
            'ingot' => 226,
            'window_glass' => 79,
        ];

        return self::projectRow(
            'civic_treasury_1',
            'Здание казны (столица)',
            'civic_capital',
            [],
            $materials,
            null,
            'Долгострой столицы (TBD)'
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function civicFair1(): array
    {
        $materials = [
            'plank' => 1695,
            'log' => 531,
            'stone_block' => 791,
            'ingot' => 396,
            'window_glass' => 339,
        ];

        return self::projectRow(
            'civic_fair_1',
            'Ярмарка (столица)',
            'civic_capital',
            [],
            $materials,
            'civic_treasury_1',
            'Долгострой столицы (TBD)'
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function civicPalace1(): array
    {
        $materials = [
            'plank' => 4294,
            'log' => 1356,
            'stone_block' => 2260,
            'ingot' => 1243,
            'window_glass' => 791,
        ];

        return self::projectRow(
            'civic_palace_1',
            'Дворец (столица)',
            'civic_capital',
            [],
            $materials,
            'civic_fair_1',
            'Чудо света (TBD)'
        );
    }

    /**
     * @param array<string, int> $components
     * @param array<string, int> $materials
     * @return array<string, mixed>
     */
    private static function projectRow(
        string $code,
        string $labelRu,
        string $kind,
        array $components,
        array $materials,
        ?string $requires,
        string $unlock,
        bool $opensCityMap = false
    ): array {
        $nominalTotal = $components !== []
            ? self::calcComponentNominal($components)
            : self::calcRawNominal($materials);

        $row = [
            'code' => $code,
            'label' => $labelRu,
            'label_ru' => $labelRu,
            'kind' => $kind,
            'progress_total' => 0,
            'nominal_total' => $nominalTotal,
            'unlock' => $unlock,
        ];

        if ($components !== []) {
            $row['components'] = $components;
        }
        if ($materials !== []) {
            $row['materials'] = $materials;
        }
        if ($requires !== null && $requires !== '') {
            $row['requires'] = $requires;
        }
        if ($opensCityMap) {
            $row['opens_city_map'] = true;
        }

        return $row;
    }

    /**
     * @param array<string, int> $components
     */
    public static function calcComponentNominal(array $components): float
    {
        $total = 0.0;

        foreach ($components as $code => $qty) {
            $qty = (int)$qty;
            if ($qty <= 0) {
                continue;
            }

            $total += $qty * ProfessionCraftedItemConfig::getNominal((string)$code);
        }

        return round($total * self::COMPONENT_NOMINAL_SURCHARGE, 1);
    }

    /**
     * @param array<string, int> $materials
     */
    public static function calcRawNominal(array $materials): float
    {
        $raw = ['log', 'stone', 'ore', 'sand', 'cotton'];
        $total = 0.0;

        foreach ($materials as $code => $qty) {
            $nominal = in_array($code, $raw, true)
                ? ProfessionEconomyConfig::NOMINAL_RAW
                : ProfessionEconomyConfig::NOMINAL_PROCESSED;
            $total += (int)$qty * $nominal;
        }

        return round($total * self::COMPONENT_NOMINAL_SURCHARGE, 1);
    }

    /**
     * @deprecated Используйте calcComponentNominal / calcRawNominal.
     *
     * @param array<string, int> $materials
     */
    public static function calcNominal(array $materials): float
    {
        return self::calcRawNominal($materials);
    }

    /**
     * Гвозди из слитков в рецепте (не отдельная строка в materials).
     */
    public static function nailsFromIngots(int $ingotCount): int
    {
        return $ingotCount * ProfessionEconomyConfig::NAILS_PER_INGOT;
    }

    /**
     * Унифицированный BOM: components + legacy materials.
     *
     * @return array<string, int>
     */
    public static function billOfMaterials(string $recipeCode): array
    {
        $recipe = self::all()[$recipeCode] ?? null;
        if ($recipe === null) {
            return [];
        }

        $components = (array)($recipe['components'] ?? []);
        $materials = (array)($recipe['materials'] ?? []);

        return array_merge($materials, $components);
    }
}
