<?php

namespace Prognos9ys\Main\Service\Game;

class ProfessionRecipeConfig
{
    public const PACK_RECIPE_BASIC = 'pack_recipe_basic';
    public const PACK_RECIPE_ADVANCED = 'pack_recipe_advanced';
    public const PACK_EQUIPMENT_WORK = 'pack_equipment_work';

    public const WORK_COST = 10;
    public const COPY_WORK_COST = 10;
    public const COPY_XP = 5;
    public const CRAFT_XP = 5;

    public const TIER_BASIC = 'basic';
    public const TIER_ADVANCED = 'advanced';

    public const RECIPE_ALBUM = 'recipe_album';
    public const RECIPE_CLEAN_SCROLL = 'recipe_clean_scroll';
    public const RECIPE_NAILS = 'recipe_nails';
    public const RECIPE_HINGE = 'recipe_hinge';
    public const RECIPE_HANDLE = 'recipe_handle';
    public const RECIPE_BEAM = 'recipe_beam';
    public const RECIPE_SMALL_FRAME = 'recipe_small_frame';
    public const RECIPE_FRAME = 'recipe_frame';
    public const RECIPE_TILE = 'recipe_tile';
    public const RECIPE_ROPE = 'recipe_rope';
    public const RECIPE_BURLAP = 'recipe_burlap';
    public const RECIPE_WINDOW_SMALL = 'recipe_window_small';
    public const RECIPE_LATCH = 'recipe_latch';
    public const RECIPE_BRACKET = 'recipe_bracket';
    public const RECIPE_FOUNDATION_BLOCK = 'recipe_foundation_block';
    public const RECIPE_THRESHOLD = 'recipe_threshold';
    public const RECIPE_WINDOW_REGULAR = 'recipe_window_regular';
    public const RECIPE_DOOR = 'recipe_door';
    public const RECIPE_ARCH_LINTEL = 'recipe_arch_lintel';
    public const RECIPE_FENCE_PANEL = 'recipe_fence_panel';
    public const RECIPE_WALL_SECTION_FENCE = 'recipe_wall_section_fence';
    public const RECIPE_WALL_SECTION = 'recipe_wall_section';
    public const RECIPE_WALL_SECTION_CORNER = 'recipe_wall_section_corner';
    public const RECIPE_WALL_SECTION_WINDOW = 'recipe_wall_section_window';
    public const RECIPE_WALL_SECTION_DOOR = 'recipe_wall_section_door';
    public const RECIPE_ROOF_BUNDLE = 'recipe_roof_bundle';
    public const RECIPE_ROOF_BUNDLE_LIGHT = 'recipe_roof_bundle_light';
    public const RECIPE_CAFTAN_BASIC = 'recipe_caftan_basic';
    public const RECIPE_CAFTAN_EMBROIDERED = 'recipe_caftan_embroidered';
    public const RECIPE_CAFTAN_GRAND = 'recipe_caftan_grand';

    /**
     * @return array<string, array{code:string,label:string,profession:string,nominal:float,tier:string}>
     */
    public static function all(): array
    {
        return [
            self::RECIPE_CLEAN_SCROLL => self::meta(self::RECIPE_CLEAN_SCROLL, 'Рецепт чистого свитка', 'weaver', 5.0, self::TIER_BASIC),
            self::RECIPE_NAILS => self::meta(self::RECIPE_NAILS, 'Рецепт гвоздей', 'smelter', 15.0, self::TIER_BASIC),
            self::RECIPE_HINGE => self::meta(self::RECIPE_HINGE, 'Рецепт петель', 'smelter', 20.0, self::TIER_BASIC),
            self::RECIPE_HANDLE => self::meta(self::RECIPE_HANDLE, 'Рецепт ручки', 'smelter', 12.0, self::TIER_BASIC),
            self::RECIPE_BEAM => self::meta(self::RECIPE_BEAM, 'Рецепт бруса', 'carpenter', 25.0, self::TIER_BASIC),
            self::RECIPE_SMALL_FRAME => self::meta(self::RECIPE_SMALL_FRAME, 'Рецепт малой рамы', 'carpenter', 18.0, self::TIER_BASIC),
            self::RECIPE_FRAME => self::meta(self::RECIPE_FRAME, 'Рецепт рамы', 'carpenter', 34.0, self::TIER_BASIC),
            self::RECIPE_TILE => self::meta(self::RECIPE_TILE, 'Рецепт плитки', 'glassblower', 12.0, self::TIER_BASIC),
            self::RECIPE_ROPE => self::meta(self::RECIPE_ROPE, 'Рецепт верёвки', 'weaver', 12.0, self::TIER_BASIC),
            self::RECIPE_BURLAP => self::meta(self::RECIPE_BURLAP, 'Рецепт мешковины', 'weaver', 40.0, self::TIER_BASIC),
            self::RECIPE_WINDOW_SMALL => self::meta(self::RECIPE_WINDOW_SMALL, 'Рецепт окна малого', 'glassblower', 36.0, self::TIER_BASIC),
            self::RECIPE_LATCH => self::meta(self::RECIPE_LATCH, 'Рецепт защёлки', 'smelter', 15.0, self::TIER_BASIC),
            self::RECIPE_BRACKET => self::meta(self::RECIPE_BRACKET, 'Рецепт скобы', 'smelter', 12.0, self::TIER_BASIC),
            self::RECIPE_FOUNDATION_BLOCK => self::meta(self::RECIPE_FOUNDATION_BLOCK, 'Рецепт фундаментного блока', 'stonemason', 20.0, self::TIER_BASIC),
            self::RECIPE_THRESHOLD => self::meta(self::RECIPE_THRESHOLD, 'Рецепт порога', 'stonemason', 40.0, self::TIER_BASIC),
            self::RECIPE_WINDOW_REGULAR => self::meta(self::RECIPE_WINDOW_REGULAR, 'Рецепт окна обычного', 'glassblower', 64.0, self::TIER_ADVANCED),
            self::RECIPE_DOOR => self::meta(self::RECIPE_DOOR, 'Рецепт двери', 'carpenter', 111.0, self::TIER_ADVANCED),
            self::RECIPE_ARCH_LINTEL => self::meta(self::RECIPE_ARCH_LINTEL, 'Рецепт арки/перемычки', 'stonemason', 70.0, self::TIER_ADVANCED),
            self::RECIPE_FENCE_PANEL => self::meta(self::RECIPE_FENCE_PANEL, 'Рецепт секции плетня', 'carpenter', 40.0, self::TIER_BASIC),
            self::RECIPE_WALL_SECTION_FENCE => self::meta(self::RECIPE_WALL_SECTION_FENCE, 'Рецепт секции забора', 'stonemason', 55.0, self::TIER_BASIC),
            self::RECIPE_WALL_SECTION => self::meta(self::RECIPE_WALL_SECTION, 'Рецепт секции стены', 'carpenter', 71.0, self::TIER_BASIC),
            self::RECIPE_WALL_SECTION_CORNER => self::meta(self::RECIPE_WALL_SECTION_CORNER, 'Рецепт угла стены', 'carpenter', 59.0, self::TIER_BASIC),
            self::RECIPE_WALL_SECTION_WINDOW => self::meta(self::RECIPE_WALL_SECTION_WINDOW, 'Рецепт стены под окно', 'carpenter', 78.0, self::TIER_BASIC),
            self::RECIPE_WALL_SECTION_DOOR => self::meta(self::RECIPE_WALL_SECTION_DOOR, 'Рецепт секции с проёмом', 'carpenter', 133.0, self::TIER_ADVANCED),
            self::RECIPE_ROOF_BUNDLE => self::meta(self::RECIPE_ROOF_BUNDLE, 'Рецепт пакета крыши', 'carpenter', 235.0, self::TIER_ADVANCED),
            self::RECIPE_ROOF_BUNDLE_LIGHT => self::meta(self::RECIPE_ROOF_BUNDLE_LIGHT, 'Рецепт лёгкого покрытия крыши', 'carpenter', 140.0, self::TIER_BASIC),
            self::RECIPE_ALBUM => self::meta(self::RECIPE_ALBUM, 'Рецепт альбома коллекции', 'weaver', 10.0, self::TIER_ADVANCED),
            self::RECIPE_CAFTAN_BASIC => self::meta(self::RECIPE_CAFTAN_BASIC, 'Рецепт кафтана (обычный)', 'weaver', 60.0, self::TIER_ADVANCED),
            self::RECIPE_CAFTAN_EMBROIDERED => self::meta(self::RECIPE_CAFTAN_EMBROIDERED, 'Рецепт кафтана (расшитый)', 'weaver', 170.0, self::TIER_ADVANCED),
            self::RECIPE_CAFTAN_GRAND => self::meta(self::RECIPE_CAFTAN_GRAND, 'Рецепт кафтана (великолепный)', 'weaver', 420.0, self::TIER_ADVANCED),
        ];
    }

    public static function isKnownRecipe(string $recipeCode): bool
    {
        return isset(self::all()[trim($recipeCode)]);
    }

    public static function getRecipeLabel(string $recipeCode): string
    {
        $recipeCode = trim($recipeCode);
        if ($recipeCode === '') {
            return '';
        }

        return (string)(self::all()[$recipeCode]['label'] ?? $recipeCode);
    }

    public static function getRecipeNominal(string $recipeCode): float
    {
        $recipeCode = trim($recipeCode);
        if ($recipeCode === '') {
            return 10.0;
        }

        return (float)(self::all()[$recipeCode]['nominal'] ?? 10.0);
    }

    public static function getRecipeProfession(string $recipeCode): string
    {
        $recipeCode = trim($recipeCode);

        return (string)(self::all()[$recipeCode]['profession'] ?? '');
    }

    public static function isCraftableViaService(string $recipeCode): bool
    {
        $recipeCode = trim($recipeCode);

        return $recipeCode !== '' && $recipeCode !== self::RECIPE_ALBUM && isset(self::craftDefinitions()[$recipeCode]);
    }

    /**
     * @return array<string, array{
     *   recipe_code:string,
     *   profession:string,
     *   tier:string,
     *   work_cost:int,
     *   craft_xp:int,
     *   inputs:array<int, array{code:string,qty:int,source:string,premium?:bool}>,
     *   outputs:array<int, array{code:string,qty:int,source:string}>
     * }>
     */
    public static function craftDefinitions(): array
    {
        static $definitions = null;
        if ($definitions !== null) {
            return $definitions;
        }

        $material = 'material';
        $equipment = 'equipment';

        $definitions = [
            self::RECIPE_CLEAN_SCROLL => self::craftDef(self::RECIPE_CLEAN_SCROLL, 'weaver', self::TIER_BASIC, [
                self::input('cloth', 1, $material),
            ], [
                self::output('clean_scroll', 4, $material),
            ]),
            self::RECIPE_NAILS => self::craftDef(self::RECIPE_NAILS, 'smelter', self::TIER_BASIC, [
                self::input('ingot', 1, $material),
            ], [
                self::output('nails', 20, $material),
            ]),
            self::RECIPE_HINGE => self::craftDef(self::RECIPE_HINGE, 'smelter', self::TIER_BASIC, [
                self::input('ingot', 1, $material),
            ], [
                self::output('hinge', 8, $material),
            ]),
            self::RECIPE_HANDLE => self::craftDef(self::RECIPE_HANDLE, 'smelter', self::TIER_BASIC, [
                self::input('ingot', 1, $material),
            ], [
                self::output('handle', 4, $material),
            ]),
            self::RECIPE_BEAM => self::craftDef(self::RECIPE_BEAM, 'carpenter', self::TIER_BASIC, [
                self::input('plank', 4, $material),
            ], [
                self::output('beam', 2, $material),
            ]),
            self::RECIPE_SMALL_FRAME => self::craftDef(self::RECIPE_SMALL_FRAME, 'carpenter', self::TIER_BASIC, [
                self::input('plank', 2, $material),
                self::input('nails', 2, $material),
            ], [
                self::output('small_frame', 2, $material),
            ]),
            self::RECIPE_FRAME => self::craftDef(self::RECIPE_FRAME, 'carpenter', self::TIER_BASIC, [
                self::input('plank', 2, $material),
                self::input('nails', 4, $material),
            ], [
                self::output('frame', 1, $material),
            ]),
            self::RECIPE_TILE => self::craftDef(self::RECIPE_TILE, 'glassblower', self::TIER_BASIC, [
                self::input('glass', 4, $material),
            ], [
                self::output('tile', 10, $material),
            ]),
            self::RECIPE_ROPE => self::craftDef(self::RECIPE_ROPE, 'weaver', self::TIER_BASIC, [
                self::input('cloth', 2, $material),
            ], [
                self::output('rope', 3, $material),
            ]),
            self::RECIPE_BURLAP => self::craftDef(self::RECIPE_BURLAP, 'weaver', self::TIER_BASIC, [
                self::input('cloth', 3, $material),
            ], [
                self::output('burlap', 1, $material),
            ]),
            self::RECIPE_WINDOW_SMALL => self::craftDef(self::RECIPE_WINDOW_SMALL, 'glassblower', self::TIER_BASIC, [
                self::input('glass', 1, $material),
                self::input('small_frame', 1, $material),
            ], [
                self::output('window_small', 1, $material),
            ]),
            self::RECIPE_LATCH => self::craftDef(self::RECIPE_LATCH, 'smelter', self::TIER_BASIC, [
                self::input('ingot', 1, $material),
                self::input('handle', 1, $material),
            ], [
                self::output('latch', 2, $material),
            ]),
            self::RECIPE_BRACKET => self::craftDef(self::RECIPE_BRACKET, 'smelter', self::TIER_BASIC, [
                self::input('ingot', 1, $material),
                self::input('nails', 10, $material),
            ], [
                self::output('bracket', 4, $material),
            ]),
            self::RECIPE_FOUNDATION_BLOCK => self::craftDef(self::RECIPE_FOUNDATION_BLOCK, 'stonemason', self::TIER_BASIC, [
                self::input('stone', 2, $material),
            ], [
                self::output('foundation_block', 1, $material),
            ]),
            self::RECIPE_THRESHOLD => self::craftDef(self::RECIPE_THRESHOLD, 'stonemason', self::TIER_BASIC, [
                self::input('block', 2, $material),
                self::input('ingot', 1, $material),
            ], [
                self::output('threshold', 1, $material),
            ]),
            self::RECIPE_WINDOW_REGULAR => self::craftDef(self::RECIPE_WINDOW_REGULAR, 'glassblower', self::TIER_ADVANCED, [
                self::input('glass', 2, $material),
                self::input('frame', 1, $material),
            ], [
                self::output('window_regular', 1, $material),
            ]),
            self::RECIPE_DOOR => self::craftDef(self::RECIPE_DOOR, 'carpenter', self::TIER_ADVANCED, [
                self::input('plank', 4, $material),
                self::input('nails', 20, $material),
                self::input('hinge', 2, $material),
                self::input('window_small', 1, $material),
            ], [
                self::output('door', 1, $material),
            ]),
            self::RECIPE_ARCH_LINTEL => self::craftDef(self::RECIPE_ARCH_LINTEL, 'stonemason', self::TIER_ADVANCED, [
                self::input('block', 3, $material),
                self::input('beam', 1, $material),
            ], [
                self::output('arch_lintel', 1, $material),
            ]),
            self::RECIPE_FENCE_PANEL => self::craftDef(self::RECIPE_FENCE_PANEL, 'carpenter', self::TIER_BASIC, [
                self::input('plank', 3, $material),
                self::input('rope', 1, $material),
            ], [
                self::output('fence_panel', 1, $material),
            ]),
            self::RECIPE_WALL_SECTION_FENCE => self::craftDef(self::RECIPE_WALL_SECTION_FENCE, 'stonemason', self::TIER_BASIC, [
                self::input('foundation_block', 1, $material),
                self::input('beam', 1, $material),
                self::input('rope', 1, $material),
            ], [
                self::output('wall_section_fence', 1, $material),
            ]),
            self::RECIPE_WALL_SECTION => self::craftDef(self::RECIPE_WALL_SECTION, 'carpenter', self::TIER_BASIC, [
                self::input('plank', 4, $material),
                self::input('nails', 6, $material),
                self::input('beam', 1, $material),
            ], [
                self::output('wall_section', 1, $material),
            ]),
            self::RECIPE_WALL_SECTION_CORNER => self::craftDef(self::RECIPE_WALL_SECTION_CORNER, 'carpenter', self::TIER_BASIC, [
                self::input('plank', 3, $material),
                self::input('nails', 4, $material),
                self::input('beam', 1, $material),
            ], [
                self::output('wall_section_corner', 1, $material),
            ]),
            self::RECIPE_WALL_SECTION_WINDOW => self::craftDef(self::RECIPE_WALL_SECTION_WINDOW, 'carpenter', self::TIER_BASIC, [
                self::input('plank', 4, $material),
                self::input('nails', 4, $material),
                self::input('frame', 1, $material),
            ], [
                self::output('wall_section_window', 1, $material),
            ]),
            self::RECIPE_WALL_SECTION_DOOR => self::craftDef(self::RECIPE_WALL_SECTION_DOOR, 'carpenter', self::TIER_ADVANCED, [
                self::input('plank', 6, $material),
                self::input('nails', 8, $material),
                self::input('beam', 1, $material),
                self::input('threshold', 1, $material),
            ], [
                self::output('wall_section_door', 1, $material),
            ]),
            self::RECIPE_ROOF_BUNDLE => self::craftDef(self::RECIPE_ROOF_BUNDLE, 'carpenter', self::TIER_ADVANCED, [
                self::input('beam', 3, $material),
                self::input('burlap', 2, $material),
                self::input('plank', 6, $material),
                self::input('rope', 2, $material),
            ], [
                self::output('roof_bundle', 1, $material),
            ]),
            self::RECIPE_ROOF_BUNDLE_LIGHT => self::craftDef(self::RECIPE_ROOF_BUNDLE_LIGHT, 'carpenter', self::TIER_BASIC, [
                self::input('beam', 2, $material),
                self::input('burlap', 1, $material),
                self::input('plank', 4, $material),
                self::input('rope', 1, $material),
            ], [
                self::output('roof_bundle_light', 1, $material),
            ]),
            self::RECIPE_CAFTAN_BASIC => self::craftDef(self::RECIPE_CAFTAN_BASIC, 'weaver', self::TIER_ADVANCED, [
                self::input('cloth', 4, $material),
                self::input('rope', 1, $material),
            ], [
                self::output('caftan_basic', 1, $equipment),
            ]),
            self::RECIPE_CAFTAN_EMBROIDERED => self::craftDef(self::RECIPE_CAFTAN_EMBROIDERED, 'weaver', self::TIER_ADVANCED, [
                self::input('cloth', 6, $material),
                self::input('rope', 2, $material),
                self::input('fine_cloth', 1, $material, true),
            ], [
                self::output('caftan_embroidered', 1, $equipment),
            ]),
            self::RECIPE_CAFTAN_GRAND => self::craftDef(self::RECIPE_CAFTAN_GRAND, 'weaver', self::TIER_ADVANCED, [
                self::input('cloth', 8, $material),
                self::input('fine_cloth', 2, $material, true),
                self::input('caftan_embroidered', 1, $equipment),
            ], [
                self::output('caftan_grand', 1, $equipment),
            ]),
        ];

        return $definitions;
    }

    public static function getCraftDefinition(string $recipeCode): ?array
    {
        $recipeCode = trim($recipeCode);

        return self::craftDefinitions()[$recipeCode] ?? null;
    }

    public static function findRecipeCodeByOutputCode(string $outputCode): ?string
    {
        $outputCode = trim($outputCode);
        if ($outputCode === '') {
            return null;
        }

        foreach (self::craftDefinitions() as $recipeCode => $definition) {
            foreach ($definition['outputs'] ?? [] as $output) {
                if ((string)($output['code'] ?? '') === $outputCode
                    && (string)($output['source'] ?? 'material') === 'material') {
                    return (string)$recipeCode;
                }
            }
        }

        return null;
    }

    /**
     * @return array<int, array{code:string,weight:int,kind:string,category:string,label:string}>
     */
    public static function professionChestRecipeDrops(): array
    {
        return [
            ['code' => self::PACK_RECIPE_BASIC, 'weight' => 52, 'kind' => 'item', 'category' => ChestLootConfig::CATEGORY_PACK, 'label' => 'Пак рецептов: базовый'],
            ['code' => self::PACK_RECIPE_ADVANCED, 'weight' => 33, 'kind' => 'item', 'category' => ChestLootConfig::CATEGORY_PACK, 'label' => 'Пак рецептов: продвинутый'],
            ['code' => self::PACK_EQUIPMENT_WORK, 'weight' => 15, 'kind' => 'item', 'category' => ChestLootConfig::CATEGORY_PACK, 'label' => 'Пак экипировки: рабочий'],
        ];
    }

    /**
     * @return array<int, array{code:string,weight:int,kind:string,category:string,label:string}>
     */
    public static function recipeBasicPackDrops(): array
    {
        return self::packRows([
            self::RECIPE_NAILS => 10,
            self::RECIPE_HINGE => 9,
            self::RECIPE_HANDLE => 7,
            self::RECIPE_BEAM => 7,
            self::RECIPE_SMALL_FRAME => 8,
            self::RECIPE_FRAME => 7,
            self::RECIPE_TILE => 7,
            self::RECIPE_ROPE => 7,
            self::RECIPE_BURLAP => 6,
            self::RECIPE_WINDOW_SMALL => 4,
            self::RECIPE_LATCH => 2,
            self::RECIPE_BRACKET => 2,
            self::RECIPE_FOUNDATION_BLOCK => 4,
            self::RECIPE_THRESHOLD => 3,
            self::RECIPE_FENCE_PANEL => 4,
            self::RECIPE_WALL_SECTION_FENCE => 3,
            self::RECIPE_ROOF_BUNDLE_LIGHT => 3,
        ]);
    }

    /**
     * @return array<int, array{code:string,weight:int,kind:string,category:string,label:string}>
     */
    public static function recipeAdvancedPackDrops(): array
    {
        return self::packRows([
            self::RECIPE_WINDOW_REGULAR => 22,
            self::RECIPE_DOOR => 18,
            self::RECIPE_ARCH_LINTEL => 16,
            self::RECIPE_WALL_SECTION => 6,
            self::RECIPE_WALL_SECTION_CORNER => 5,
            self::RECIPE_WALL_SECTION_WINDOW => 5,
            self::RECIPE_WALL_SECTION_DOOR => 4,
            self::RECIPE_ROOF_BUNDLE => 4,
        ]);
    }

    /**
     * @return array<int, array{code:string,weight:int,kind:string,category:string,label:string}>
     */
    public static function equipmentWorkPackDrops(): array
    {
        return self::packRows([
            self::RECIPE_CAFTAN_BASIC => 70,
            self::RECIPE_CAFTAN_EMBROIDERED => 22,
            self::RECIPE_CAFTAN_GRAND => 8,
        ]);
    }

    /**
     * @param array<string, int> $weights
     * @return array<int, array{code:string,weight:int,kind:string,category:string,label:string}>
     */
    private static function packRows(array $weights): array
    {
        $rows = [];
        foreach ($weights as $code => $weight) {
            $rows[] = [
                'code' => $code,
                'weight' => $weight,
                'kind' => 'item',
                'category' => ChestLootConfig::CATEGORY_RECIPE,
                'label' => self::getRecipeLabel($code),
            ];
        }

        return $rows;
    }

    /**
     * @return array{code:string,label:string,profession:string,nominal:float,tier:string}
     */
    private static function meta(
        string $code,
        string $label,
        string $profession,
        float $nominal,
        string $tier
    ): array {
        return [
            'code' => $code,
            'label' => $label,
            'profession' => $profession,
            'nominal' => $nominal,
            'tier' => $tier,
        ];
    }

    /**
     * @param array<int, array{code:string,qty:int,source:string,premium?:bool}> $inputs
     * @param array<int, array{code:string,qty:int,source:string}> $outputs
     * @return array{
     *   recipe_code:string,
     *   profession:string,
     *   tier:string,
     *   work_cost:int,
     *   craft_xp:int,
     *   inputs:array<int, array{code:string,qty:int,source:string,premium?:bool}>,
     *   outputs:array<int, array{code:string,qty:int,source:string}>
     * }
     */
    private static function craftDef(
        string $recipeCode,
        string $profession,
        string $tier,
        array $inputs,
        array $outputs
    ): array {
        return [
            'recipe_code' => $recipeCode,
            'profession' => $profession,
            'tier' => $tier,
            'work_cost' => self::WORK_COST,
            'craft_xp' => self::CRAFT_XP,
            'inputs' => $inputs,
            'outputs' => $outputs,
        ];
    }

    /**
     * @return array{code:string,qty:int,source:string,premium?:bool}
     */
    private static function input(string $code, int $qty, string $source, bool $premium = false): array
    {
        $row = [
            'code' => $code,
            'qty' => $qty,
            'source' => $source,
        ];
        if ($premium) {
            $row['premium'] = true;
        }

        return $row;
    }

    /**
     * @return array{code:string,qty:int,source:string}
     */
    private static function output(string $code, int $qty, string $source): array
    {
        return [
            'code' => $code,
            'qty' => $qty,
            'source' => $source,
        ];
    }
}
