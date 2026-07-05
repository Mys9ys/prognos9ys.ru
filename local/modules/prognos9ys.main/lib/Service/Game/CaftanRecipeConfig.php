<?php

namespace Prognos9ys\Main\Service\Game;

/**
 * Кафтаны по профессиям (10×3) и двухступенчатая обработка премиум-сырья.
 *
 * Цепочка на каждую линию добычи→обработки:
 *   1) премиум добычи + сырьё → новый промежуточный материал крафта
 *   2) промежуточный + базовый переработанный → fine_* (парча, хрусталь…)
 */
class CaftanRecipeConfig
{
    public const TIER_BASIC = 'basic';
    public const TIER_EMBROIDERED = 'embroidered';
    public const TIER_GRAND = 'grand';

    /** @var string[] */
    public const TIERS = [
        self::TIER_BASIC,
        self::TIER_EMBROIDERED,
        self::TIER_GRAND,
    ];

    /** @var array<string, float> */
    private const TIER_NOMINALS = [
        self::TIER_BASIC => 60.0,
        self::TIER_EMBROIDERED => 170.0,
        self::TIER_GRAND => 420.0,
    ];

    /**
     * @var array<string, array{
     *   profession:string,
     *   gather_premium:string,
     *   gather_base:string,
     *   intermediate:array{code:string,label:string,nominal:float,emoji:string},
     *   processed_base:string,
     *   fine:string,
     *   stage1:array{gather_premium:int,gather_base:int},
     *   stage2:array{intermediate:int,processed_base:int}
     * }>
     */
    private const PREMIUM_CHAINS = [
        'carpenter' => [
            'profession' => 'carpenter',
            'gather_premium' => 'amber',
            'gather_base' => 'log',
            'intermediate' => [
                'code' => 'craft_resin',
                'label' => 'Мастерская смола',
                'nominal' => 38.0,
                'emoji' => '🟤',
            ],
            'processed_base' => 'plank',
            'fine' => 'fine_plank',
            'stage1' => ['gather_premium' => 3, 'gather_base' => 4],
            'stage2' => ['intermediate' => 1, 'processed_base' => 6],
        ],
        'stonemason' => [
            'profession' => 'stonemason',
            'gather_premium' => 'marble',
            'gather_base' => 'stone',
            'intermediate' => [
                'code' => 'craft_sealstone',
                'label' => 'Печать камня',
                'nominal' => 38.0,
                'emoji' => '⚪',
            ],
            'processed_base' => 'block',
            'fine' => 'fine_block',
            'stage1' => ['gather_premium' => 3, 'gather_base' => 4],
            'stage2' => ['intermediate' => 1, 'processed_base' => 6],
        ],
        'smelter' => [
            'profession' => 'smelter',
            'gather_premium' => 'gold_nugget',
            'gather_base' => 'ore',
            'intermediate' => [
                'code' => 'craft_gilded_ore',
                'label' => 'Золотая жила',
                'nominal' => 38.0,
                'emoji' => '🟡',
            ],
            'processed_base' => 'ingot',
            'fine' => 'fine_ingot',
            'stage1' => ['gather_premium' => 3, 'gather_base' => 4],
            'stage2' => ['intermediate' => 1, 'processed_base' => 6],
        ],
        'glassblower' => [
            'profession' => 'glassblower',
            'gather_premium' => 'quartz',
            'gather_base' => 'sand',
            'intermediate' => [
                'code' => 'craft_prism_sand',
                'label' => 'Призматический песок',
                'nominal' => 38.0,
                'emoji' => '🔮',
            ],
            'processed_base' => 'glass',
            'fine' => 'fine_glass',
            'stage1' => ['gather_premium' => 3, 'gather_base' => 4],
            'stage2' => ['intermediate' => 1, 'processed_base' => 6],
        ],
        'weaver' => [
            'profession' => 'weaver',
            'gather_premium' => 'silk',
            'gather_base' => 'cotton',
            'intermediate' => [
                'code' => 'craft_golden_thread',
                'label' => 'Золотая нить',
                'nominal' => 38.0,
                'emoji' => '🧵',
            ],
            'processed_base' => 'cloth',
            'fine' => 'fine_cloth',
            'stage1' => ['gather_premium' => 3, 'gather_base' => 4],
            'stage2' => ['intermediate' => 1, 'processed_base' => 6],
        ],
    ];

    /** @var array<string, string> */
    private const GATHER_TO_PROCESS = [
        'woodcutter' => 'carpenter',
        'quarryman' => 'stonemason',
        'miner' => 'smelter',
        'sandgatherer' => 'glassblower',
        'cottongatherer' => 'weaver',
    ];

    /** @var string[] */
    private const LEGACY_RECIPE_CODES = [
        'recipe_caftan_basic',
        'recipe_caftan_embroidered',
        'recipe_caftan_grand',
    ];

    /** @var string[] */
    private const LEGACY_PRODUCT_CODES = [
        'caftan_basic',
        'caftan_embroidered',
        'caftan_grand',
    ];

    /**
     * Коды для CLI-отката на бою (см. local/tools/fix_rollback_legacy_caftans.php).
     *
     * @return array{recipes:array<int,string>,products:array<int,string>}
     */
    public static function legacyRollbackTargets(): array
    {
        return [
            'recipes' => self::LEGACY_RECIPE_CODES,
            'products' => self::LEGACY_PRODUCT_CODES,
        ];
    }

    /**
     * @return string[]
     */
    public static function professionCodes(): array
    {
        return array_keys(ProfessionMaterialConfig::allProfessions());
    }

    /**
     * @return array<string, array{
     *   profession:string,
     *   gather_premium:string,
     *   gather_base:string,
     *   intermediate:array{code:string,label:string,nominal:float,emoji:string},
     *   processed_base:string,
     *   fine:string,
     *   stage1:array{gather_premium:int,gather_base:int},
     *   stage2:array{intermediate:int,processed_base:int}
     * }>
     */
    public static function premiumChains(): array
    {
        return self::PREMIUM_CHAINS;
    }

    public static function chainForProcessingProfession(string $professionCode): ?array
    {
        return self::PREMIUM_CHAINS[$professionCode] ?? null;
    }

    public static function productCode(string $tier, string $professionCode): string
    {
        return 'caftan_' . $tier . '_' . $professionCode;
    }

    public static function recipeCode(string $tier, string $professionCode): string
    {
        return 'recipe_caftan_' . $tier . '_' . $professionCode;
    }

    public static function stage1RecipeCode(string $intermediateCode): string
    {
        return 'recipe_craft_' . $intermediateCode;
    }

    public static function stage2RecipeCode(string $fineCode): string
    {
        return 'recipe_refine_' . $fineCode;
    }

    public static function linkedProcessingProfession(string $professionCode): string
    {
        return self::GATHER_TO_PROCESS[$professionCode] ?? $professionCode;
    }

    public static function professionPremiumCode(string $professionCode): string
    {
        $definition = ProfessionMaterialConfig::getProfession($professionCode);

        return (string)($definition['premium'] ?? '');
    }

    public static function fineMaterialCode(string $professionCode): string
    {
        $processingCode = self::linkedProcessingProfession($professionCode);
        $chain = self::chainForProcessingProfession($processingCode);
        if ($chain) {
            return (string)$chain['fine'];
        }

        $definition = ProfessionMaterialConfig::getProfession($processingCode);

        return (string)($definition['premium'] ?? '');
    }

    public static function isCaftanProduct(string $code): bool
    {
        $code = trim($code);
        if ($code === '') {
            return false;
        }

        return (bool)preg_match('/^caftan_(basic|embroidered|grand)_[a-z]+$/', $code);
    }

    public static function isCaftanRecipe(string $recipeCode): bool
    {
        $recipeCode = trim($recipeCode);
        if ($recipeCode === '') {
            return false;
        }

        if (in_array($recipeCode, self::LEGACY_RECIPE_CODES, true)) {
            return false;
        }

        return (bool)preg_match('/^recipe_caftan_(basic|embroidered|grand)_[a-z]+$/', $recipeCode);
    }

    public static function isPremiumChainRecipe(string $recipeCode): bool
    {
        $recipeCode = trim($recipeCode);

        return strpos($recipeCode, 'recipe_craft_') === 0
            || strpos($recipeCode, 'recipe_refine_fine_') === 0;
    }

    /** @deprecated use isPremiumChainRecipe */
    public static function isFineRefineRecipe(string $recipeCode): bool
    {
        return self::isPremiumChainRecipe($recipeCode);
    }

    public static function isEquipmentAchievementRecipe(string $recipeCode): bool
    {
        return self::isCaftanRecipe($recipeCode) || self::isPremiumChainRecipe($recipeCode);
    }

    public static function isIntermediateCraftMaterial(string $code): bool
    {
        foreach (self::PREMIUM_CHAINS as $chain) {
            if (($chain['intermediate']['code'] ?? '') === $code) {
                return true;
            }
        }

        return false;
    }

    public static function caftanTierFromProduct(string $productCode): ?string
    {
        $productCode = trim($productCode);
        if (preg_match('/^caftan_(basic|embroidered|grand)(?:_|$)/', $productCode, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public static function professionFromProduct(string $productCode): ?string
    {
        $productCode = trim($productCode);
        if (preg_match('/^caftan_(?:basic|embroidered|grand)_([a-z]+)$/', $productCode, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public static function caftanLabel(string $tier, string $professionCode): string
    {
        $profession = ProfessionMaterialConfig::getProfession($professionCode);
        $professionLabel = (string)($profession['label'] ?? $professionCode);
        $tierLabel = [
            self::TIER_BASIC => 'обычный',
            self::TIER_EMBROIDERED => 'расшитый',
            self::TIER_GRAND => 'великолепный',
        ][$tier] ?? $tier;

        return 'Кафтан ' . $professionLabel . ' (' . $tierLabel . ')';
    }

    /**
     * @return array<string, array{code:string,label:string,nominal:float,emoji:string}>
     */
    public static function intermediateMaterialCatalog(): array
    {
        $catalog = [];
        foreach (self::PREMIUM_CHAINS as $chain) {
            $item = $chain['intermediate'];
            $code = (string)$item['code'];
            $catalog[$code] = [
                'code' => $code,
                'label' => (string)$item['label'],
                'nominal' => (float)$item['nominal'],
                'emoji' => (string)$item['emoji'],
            ];
        }

        return $catalog;
    }

    /**
     * @return array<string, array{code:string,label:string,profession:string,nominal:float,tier:string}>
     */
    public static function recipeMetaEntries(): array
    {
        $entries = [];

        foreach (self::professionCodes() as $professionCode) {
            foreach (self::TIERS as $tier) {
                $recipeCode = self::recipeCode($tier, $professionCode);
                $entries[$recipeCode] = [
                    'code' => $recipeCode,
                    'label' => 'Рецепт: ' . mb_strtolower(self::caftanLabel($tier, $professionCode)),
                    'profession' => 'weaver',
                    'nominal' => self::TIER_NOMINALS[$tier],
                    'tier' => ProfessionRecipeConfig::TIER_ADVANCED,
                ];
            }
        }

        foreach (self::premiumChainRecipeDefinitions() as $recipeCode => $definition) {
            $entries[$recipeCode] = [
                'code' => $recipeCode,
                'label' => (string)($definition['label'] ?? $recipeCode),
                'profession' => (string)($definition['profession'] ?? ''),
                'nominal' => (float)($definition['nominal'] ?? 45.0),
                'tier' => ProfessionRecipeConfig::TIER_ADVANCED,
            ];
        }

        return $entries;
    }

    /**
     * @return array<string, array{code:string,label:string,nominal:float,storage:string}>
     */
    public static function craftedItemEntries(): array
    {
        $entries = [];

        foreach (self::professionCodes() as $professionCode) {
            foreach (self::TIERS as $tier) {
                $code = self::productCode($tier, $professionCode);
                $entries[$code] = [
                    'code' => $code,
                    'label' => self::caftanLabel($tier, $professionCode),
                    'nominal' => self::TIER_NOMINALS[$tier],
                    'storage' => ProfessionCraftedItemConfig::STORAGE_EQUIPMENT,
                ];
            }
        }

        foreach (self::intermediateMaterialCatalog() as $code => $item) {
            $entries[$code] = [
                'code' => $code,
                'label' => (string)$item['label'],
                'nominal' => (float)$item['nominal'],
                'storage' => ProfessionCraftedItemConfig::STORAGE_MATERIAL,
            ];
        }

        return $entries;
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
    public static function craftDefinitionEntries(): array
    {
        $definitions = [];
        $material = 'material';
        $equipment = 'equipment';

        foreach (self::professionCodes() as $professionCode) {
            $premiumCode = self::professionPremiumCode($professionCode);
            $fineCode = self::fineMaterialCode($professionCode);

            $definitions[self::recipeCode(self::TIER_BASIC, $professionCode)] = self::craftDef(
                self::recipeCode(self::TIER_BASIC, $professionCode),
                'weaver',
                ProfessionRecipeConfig::TIER_ADVANCED,
                [
                    self::input('cloth', 4, $material),
                    self::input('rope', 1, $material),
                    self::input($premiumCode, 3, $material, true),
                ],
                [
                    self::output(self::productCode(self::TIER_BASIC, $professionCode), 1, $equipment),
                ]
            );

            $definitions[self::recipeCode(self::TIER_EMBROIDERED, $professionCode)] = self::craftDef(
                self::recipeCode(self::TIER_EMBROIDERED, $professionCode),
                'weaver',
                ProfessionRecipeConfig::TIER_ADVANCED,
                [
                    self::input('cloth', 6, $material),
                    self::input('rope', 2, $material),
                    self::input($premiumCode, 3, $material, true),
                    self::input($fineCode, 1, $material, true),
                ],
                [
                    self::output(self::productCode(self::TIER_EMBROIDERED, $professionCode), 1, $equipment),
                ]
            );

            $definitions[self::recipeCode(self::TIER_GRAND, $professionCode)] = self::craftDef(
                self::recipeCode(self::TIER_GRAND, $professionCode),
                'weaver',
                ProfessionRecipeConfig::TIER_ADVANCED,
                [
                    self::input('cloth', 8, $material),
                    self::input($fineCode, 2, $material, true),
                    self::input(self::productCode(self::TIER_EMBROIDERED, $professionCode), 1, $equipment),
                ],
                [
                    self::output(self::productCode(self::TIER_GRAND, $professionCode), 1, $equipment),
                ]
            );
        }

        foreach (self::premiumChainCraftDefinitions() as $recipeCode => $definition) {
            $definitions[$recipeCode] = $definition;
        }

        return $definitions;
    }

    /**
     * @return array<string, array{
     *   profession:string,
     *   label:string,
     *   nominal:float,
     *   inputs:array<int, array{code:string,qty:int,source:string,premium?:bool}>,
     *   output_code:string,
     *   output_premium:bool
     * }>
     */
    private static function premiumChainRecipeDefinitions(): array
    {
        $definitions = [];

        foreach (self::PREMIUM_CHAINS as $chain) {
            $profession = (string)$chain['profession'];
            $intermediate = $chain['intermediate'];
            $intermediateCode = (string)$intermediate['code'];
            $fineCode = (string)$chain['fine'];

            $stage1Code = self::stage1RecipeCode($intermediateCode);
            $definitions[$stage1Code] = [
                'profession' => $profession,
                'label' => 'Рецепт: ' . mb_strtolower((string)$intermediate['label']),
                'nominal' => (float)$intermediate['nominal'],
                'inputs' => [],
                'output_code' => $intermediateCode,
                'output_premium' => false,
            ];

            $fineLabel = ProfessionMaterialConfig::getMaterialLabel($fineCode);
            $stage2Code = self::stage2RecipeCode($fineCode);
            $definitions[$stage2Code] = [
                'profession' => $profession,
                'label' => 'Рецепт: ' . mb_strtolower($fineLabel),
                'nominal' => 55.0,
                'inputs' => [],
                'output_code' => $fineCode,
                'output_premium' => true,
            ];
        }

        return $definitions;
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
    private static function premiumChainCraftDefinitions(): array
    {
        $material = 'material';
        $definitions = [];

        foreach (self::PREMIUM_CHAINS as $chain) {
            $profession = (string)$chain['profession'];
            $intermediate = $chain['intermediate'];
            $intermediateCode = (string)$intermediate['code'];
            $fineCode = (string)$chain['fine'];
            $stage1 = $chain['stage1'];
            $stage2 = $chain['stage2'];

            $stage1Code = self::stage1RecipeCode($intermediateCode);
            $definitions[$stage1Code] = self::craftDef(
                $stage1Code,
                $profession,
                ProfessionRecipeConfig::TIER_ADVANCED,
                [
                    self::input(
                        (string)$chain['gather_premium'],
                        (int)$stage1['gather_premium'],
                        $material,
                        true
                    ),
                    self::input(
                        (string)$chain['gather_base'],
                        (int)$stage1['gather_base'],
                        $material
                    ),
                ],
                [
                    self::output($intermediateCode, 1, $material),
                ]
            );

            $stage2Code = self::stage2RecipeCode($fineCode);
            $definitions[$stage2Code] = self::craftDef(
                $stage2Code,
                $profession,
                ProfessionRecipeConfig::TIER_ADVANCED,
                [
                    self::input(
                        $intermediateCode,
                        (int)$stage2['intermediate'],
                        $material
                    ),
                    self::input(
                        (string)$chain['processed_base'],
                        (int)$stage2['processed_base'],
                        $material
                    ),
                ],
                [
                    self::output($fineCode, 1, $material, true),
                ]
            );
        }

        return $definitions;
    }

    /**
     * @return array<int, array{code:string,weight:int}>
     */
    public static function equipmentWorkPackWeights(): array
    {
        $weights = [];
        foreach (self::professionCodes() as $professionCode) {
            foreach (self::TIERS as $tier) {
                $weights[self::recipeCode($tier, $professionCode)] = 10;
            }
        }

        return $weights;
    }

    /**
     * @return array<string, int>
     */
    public static function refineRecipePackWeights(): array
    {
        $weights = [];
        foreach (self::premiumChainRecipeDefinitions() as $recipeCode => $definition) {
            $weights[$recipeCode] = strpos($recipeCode, 'recipe_craft_') === 0 ? 14 : 20;
        }

        return $weights;
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
            'work_cost' => ProfessionRecipeConfig::WORK_COST,
            'craft_xp' => ProfessionRecipeConfig::CRAFT_XP,
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
     * @return array{code:string,qty:int,source:string,premium?:bool}
     */
    private static function output(string $code, int $qty, string $source, bool $premium = false): array
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
}
