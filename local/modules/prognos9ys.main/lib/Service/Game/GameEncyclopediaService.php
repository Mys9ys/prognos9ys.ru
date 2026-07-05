<?php

namespace Prognos9ys\Main\Service\Game;

/**
 * Справочник предметов и механик игры — собирается из PHP-конфигов + GameLoreConfig.
 */
class GameEncyclopediaService
{
    /**
     * @return array{
     *   sections:array<int, array{
     *     id:string,
     *     label:string,
     *     entries:array<int, array<string, mixed>>
     *   }>,
     *   generated_at:string
     * }
     */
    public static function build(): array
    {
        return [
            'sections' => [
                self::materialsSection(),
                self::professionsSection(),
                self::recipesSection(),
                self::equipmentSection(),
                self::buildingsSection(),
                self::packsSection(),
            ],
            'generated_at' => date('c'),
        ];
    }

    /**
     * @return array{id:string,label:string,entries:array<int, array<string, mixed>>}
     */
    private static function materialsSection(): array
    {
        $entries = [];
        foreach (ProfessionMaterialConfig::materialCatalog() as $row) {
            $code = (string)$row['code'];
            $entries[] = [
                'code' => $code,
                'label' => (string)$row['label'],
                'emoji' => (string)($row['emoji'] ?? ''),
                'nominal' => (float)($row['nominal'] ?? 0),
                'is_premium' => (bool)($row['is_premium'] ?? false),
                'storage' => ProfessionCraftedItemConfig::isKnownItem($code)
                    ? ProfessionCraftedItemConfig::getStorage($code)
                    : 'material',
                'lore' => GameLoreConfig::text('materials', $code),
            ];
        }

        return self::section('materials', 'Материалы', $entries);
    }

    /**
     * @return array{id:string,label:string,entries:array<int, array<string, mixed>>}
     */
    private static function professionsSection(): array
    {
        $entries = [];
        foreach (ProfessionMaterialConfig::allProfessions() as $profession) {
            $code = (string)$profession['code'];
            $entry = [
                'code' => $code,
                'label' => (string)$profession['label'],
                'type' => (string)$profession['type'],
                'output_code' => (string)$profession['output'],
                'output_label' => (string)$profession['output_label'],
                'premium_code' => (string)$profession['premium'],
                'premium_label' => (string)$profession['premium_label'],
                'lore' => GameLoreConfig::text('professions', $code),
            ];

            if (!empty($profession['input'])) {
                $entry['input_code'] = (string)$profession['input'];
                $entry['input_label'] = (string)($profession['input_label'] ?? '');
            }

            $chain = CaftanRecipeConfig::chainForProcessingProfession($code);
            if ($chain !== null) {
                $entry['fine_code'] = (string)($chain['fine'] ?? '');
                $entry['intermediate_code'] = (string)($chain['intermediate']['code'] ?? '');
            }

            $entries[] = $entry;
        }

        return self::section('professions', 'Профессии', $entries);
    }

    /**
     * @return array{id:string,label:string,entries:array<int, array<string, mixed>>}
     */
    private static function recipesSection(): array
    {
        $definitions = ProfessionRecipeConfig::craftDefinitions();
        $meta = ProfessionRecipeConfig::all();
        $entries = [];

        foreach ($definitions as $recipeCode => $def) {
            $recipeMeta = $meta[$recipeCode] ?? [];
            $entries[] = [
                'code' => $recipeCode,
                'label' => (string)($recipeMeta['label'] ?? ProfessionRecipeConfig::getRecipeLabel($recipeCode)),
                'profession' => (string)($def['profession'] ?? ''),
                'profession_label' => self::professionLabel((string)($def['profession'] ?? '')),
                'tier' => (string)($def['tier'] ?? ''),
                'nominal' => (float)($recipeMeta['nominal'] ?? ProfessionRecipeConfig::getRecipeNominal($recipeCode)),
                'work_cost' => (int)($def['work_cost'] ?? 0),
                'craft_xp' => (int)($def['craft_xp'] ?? 0),
                'inputs' => self::enrichIoRows((array)($def['inputs'] ?? [])),
                'outputs' => self::enrichIoRows((array)($def['outputs'] ?? [])),
                'lore' => GameLoreConfig::text('recipes', $recipeCode),
            ];
        }

        return self::section('recipes', 'Рецепты', $entries);
    }

    /**
     * @return array{id:string,label:string,entries:array<int, array<string, mixed>>}
     */
    private static function equipmentSection(): array
    {
        $entries = [];
        foreach (EquipmentConfig::caftans() as $row) {
            $code = (string)$row['code'];
            $entries[] = [
                'code' => $code,
                'label' => (string)$row['label'],
                'profession' => (string)($row['profession_code'] ?? ''),
                'profession_label' => self::professionLabel((string)($row['profession_code'] ?? '')),
                'tier' => (string)($row['tier'] ?? ''),
                'nominal' => ProfessionCraftedItemConfig::getNominal($code),
                'combo_x2_bonus' => (float)($row['combo_x2_bonus'] ?? 0),
                'combo_x3_bonus' => (float)($row['combo_x3_bonus'] ?? 0),
                'premium_bonus' => (float)($row['premium_bonus'] ?? 0),
                'lore' => GameLoreConfig::text('equipment', $code),
            ];
        }

        return self::section('equipment', 'Экипировка', $entries);
    }

    /**
     * @return array{id:string,label:string,entries:array<int, array<string, mixed>>}
     */
    private static function buildingsSection(): array
    {
        $entries = [];
        foreach (EstateRecipesConfig::all() as $project) {
            $code = (string)($project['code'] ?? '');
            if ($code === '') {
                continue;
            }

            $entries[] = [
                'code' => $code,
                'label' => (string)($project['label_ru'] ?? $project['label'] ?? $code),
                'kind' => (string)($project['kind'] ?? ''),
                'progress_total' => (int)($project['progress_total'] ?? 0),
                'nominal_total' => (float)($project['nominal_total'] ?? 0),
                'components' => self::enrichQtyMap((array)($project['components'] ?? [])),
                'materials' => self::enrichQtyMap((array)($project['materials'] ?? [])),
                'requires' => (string)($project['requires'] ?? ''),
                'unlock' => (string)($project['unlock'] ?? ''),
                'opens_city_map' => (bool)($project['opens_city_map'] ?? false),
                'lore' => GameLoreConfig::text('buildings', $code),
            ];
        }

        return self::section('buildings', 'Постройки', $entries);
    }

    /**
     * @return array{id:string,label:string,entries:array<int, array<string, mixed>>}
     */
    private static function packsSection(): array
    {
        $seen = [];
        $entries = [];

        foreach ([
            ChestLootConfig::getWc26Block3Table(),
            ChestLootConfig::getGenericBlock3Table(),
        ] as $table) {
            foreach ($table as $row) {
                if (($row['category'] ?? '') !== ChestLootConfig::CATEGORY_PACK) {
                    continue;
                }
                $code = (string)($row['code'] ?? '');
                if ($code === '' || isset($seen[$code])) {
                    continue;
                }
                $seen[$code] = true;
                $entries[] = self::packEntry($code, (string)($row['label'] ?? $code));
            }
        }

        foreach ([
            ProfessionRecipeConfig::PACK_RECIPE_BASIC,
            ProfessionRecipeConfig::PACK_RECIPE_ADVANCED,
            ProfessionRecipeConfig::PACK_EQUIPMENT_WORK,
        ] as $code) {
            if (isset($seen[$code])) {
                continue;
            }
            $seen[$code] = true;
            $entries[] = self::packEntry($code, ChestLootConfig::getProfessionPackLabel($code));
        }

        return self::section('packs', 'Паки', $entries);
    }

    /**
     * @return array<string, mixed>
     */
    private static function packEntry(string $code, string $label): array
    {
        $openable = PackOpenConfig::isFullyOpenable($code);
        $stub = PackOpenConfig::isStubPack($code);
        $drops = self::packDrops($code);

        return [
            'code' => $code,
            'label' => $label,
            'openable' => $openable,
            'stub' => $stub && !$openable,
            'reward_kind' => PackOpenConfig::getRewardKind($code),
            'drops' => $drops,
            'lore' => GameLoreConfig::text('packs', $code),
        ];
    }

    /**
     * @return array<int, array{code:string,label:string,weight:int}>
     */
    private static function packDrops(string $packCode): array
    {
        $table = null;
        if ($packCode === ProfessionRecipeConfig::PACK_RECIPE_BASIC) {
            $table = ProfessionRecipeConfig::recipeBasicPackDrops();
        } elseif ($packCode === ProfessionRecipeConfig::PACK_RECIPE_ADVANCED) {
            $table = ProfessionRecipeConfig::recipeAdvancedPackDrops();
        } elseif ($packCode === ProfessionRecipeConfig::PACK_EQUIPMENT_WORK) {
            $table = ProfessionRecipeConfig::equipmentWorkPackDrops();
        }

        if ($table === null) {
            return [];
        }

        $drops = [];
        foreach ($table as $row) {
            $drops[] = [
                'code' => (string)($row['code'] ?? ''),
                'label' => (string)($row['label'] ?? ''),
                'weight' => (int)($row['weight'] ?? 0),
            ];
        }

        return $drops;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private static function enrichIoRows(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $code = (string)($row['code'] ?? '');
            $out[] = array_merge($row, [
                'label' => self::itemLabel($code),
            ]);
        }

        return $out;
    }

    /**
     * @param array<string, int> $map
     * @return array<int, array{code:string,label:string,qty:int}>
     */
    private static function enrichQtyMap(array $map): array
    {
        $rows = [];
        foreach ($map as $code => $qty) {
            $rows[] = [
                'code' => (string)$code,
                'label' => self::itemLabel((string)$code),
                'qty' => (int)$qty,
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            return strcmp((string)$a['label'], (string)$b['label']);
        });

        return $rows;
    }

    private static function itemLabel(string $code): string
    {
        $code = trim($code);
        if ($code === '') {
            return '';
        }

        if (ProfessionRecipeConfig::isKnownRecipe($code)) {
            return ProfessionRecipeConfig::getRecipeLabel($code);
        }

        if (EquipmentConfig::isCaftanCode($code)) {
            return EquipmentConfig::getCaftanLabel($code);
        }

        return ProfessionMaterialConfig::getMaterialLabel($code);
    }

    private static function professionLabel(string $code): string
    {
        $code = trim($code);
        if ($code === '') {
            return '';
        }

        $profession = ProfessionMaterialConfig::getProfession($code);

        return (string)($profession['label'] ?? $code);
    }

    /**
     * @param array<int, array<string, mixed>> $entries
     * @return array{id:string,label:string,entries:array<int, array<string, mixed>>}
     */
    private static function section(string $id, string $label, array $entries): array
    {
        usort($entries, static function (array $a, array $b): int {
            return strcmp((string)($a['label'] ?? ''), (string)($b['label'] ?? ''));
        });

        return [
            'id' => $id,
            'label' => $label,
            'entries' => array_values($entries),
        ];
    }
}
