<?php

namespace Prognos9ys\Main\Service\Game;

/**
 * Ачивки экипировки: пошив кафтанов и обработка премиум-сырья.
 */
class EquipmentAchievementConfig
{
    public const GROUP = AchievementConfig::GROUP_EQUIPMENT;

    /** @var int[] */
    public const RECIPE_CRAFT_THRESHOLDS = [5, 25, 100, 250, 500];

    /** @var int[] */
    public const REFINE_THRESHOLDS = [3, 15, 50, 150, 300];

    /** @var float[] */
    private const RECIPE_CRAFT_RUBLIUS = [1.0, 2.0, 4.0, 8.0, 15.0];

    /** @var float[] */
    private const REFINE_RUBLIUS = [1.0, 2.0, 5.0, 10.0, 20.0];

    public static function statKeyRecipeCraft(string $recipeCode): string
    {
        return ProductionAchievementConfig::statKeyRecipeCraft($recipeCode);
    }

    public static function recipeAchCode(string $recipeCode): string
    {
        $suffix = preg_replace('/^recipe_/', '', trim($recipeCode)) ?? $recipeCode;

        return 'equipment_' . $suffix;
    }

    /**
     * @return array<string, int>
     */
    public static function emptyStatsTemplate(): array
    {
        $stats = [];
        foreach (ProfessionRecipeConfig::craftDefinitions() as $recipeCode => $definition) {
            if (!CaftanRecipeConfig::isEquipmentAchievementRecipe($recipeCode)) {
                continue;
            }
            $stats[self::statKeyRecipeCraft($recipeCode)] = 0;
        }

        return $stats;
    }

    /**
     * @return array<string, array{
     *   title:string,
     *   description:string,
     *   group:string,
     *   icon:string,
     *   stat:string,
     *   recipe_code?:string,
     *   profession_stage?:int,
     *   levels: array<int, array{threshold:int,reward:array}>
     * }>
     */
    public static function getCatalogEntries(): array
    {
        $entries = [];

        foreach (ProfessionRecipeConfig::craftDefinitions() as $recipeCode => $definition) {
            if (!CaftanRecipeConfig::isEquipmentAchievementRecipe($recipeCode)) {
                continue;
            }

            $entries = array_merge($entries, self::buildRecipeAchievement($recipeCode, $definition));
        }

        return $entries;
    }

    /**
     * @param array<string, mixed> $definition
     * @return array<string, array>
     */
    private static function buildRecipeAchievement(string $recipeCode, array $definition): array
    {
        $achCode = self::recipeAchCode($recipeCode);
        $outputs = (array)($definition['outputs'] ?? []);
        $outputCode = (string)($outputs[0]['code'] ?? '');
        $isStage1 = strpos($recipeCode, 'recipe_craft_') === 0;
        $isStage2 = strpos($recipeCode, 'recipe_refine_fine_') === 0;

        if ($isStage1 || $isStage2) {
            $productLabel = $outputCode !== ''
                ? ProfessionCraftedItemConfig::getLabel($outputCode)
                : ProfessionRecipeConfig::getRecipeLabel($recipeCode);
            if ($productLabel === '' || $productLabel === $outputCode) {
                $productLabel = ProfessionMaterialConfig::getMaterialLabel($outputCode);
            }
            $description = $isStage1
                ? 'Первичная обработка: ' . mb_strtolower($productLabel)
                : 'Финальная обработка: ' . mb_strtolower($productLabel);
            $icon = 'chest_warehouse';
            $thresholds = self::REFINE_THRESHOLDS;
            $rublius = self::REFINE_RUBLIUS;
        } else {
            $productLabel = $outputCode !== ''
                ? ProfessionCraftedItemConfig::getLabel($outputCode)
                : ProfessionRecipeConfig::getRecipeLabel($recipeCode);
            $description = 'Пошив: ' . mb_strtolower($productLabel);
            $icon = 'chest_opener';
            $thresholds = self::RECIPE_CRAFT_THRESHOLDS;
            $rublius = self::RECIPE_CRAFT_RUBLIUS;
        }

        if ($productLabel === '') {
            $productLabel = $recipeCode;
        }

        $levels = [];
        foreach ($thresholds as $index => $threshold) {
            $levels[] = [
                'threshold' => $threshold,
                'reward' => array_merge(
                    ['rublius' => $rublius[$index] ?? 1.0],
                    ProductionAchievementConfig::chestPacksForLevelIndex($index)
                ),
            ];
        }

        return [
            $achCode => [
                'title' => $productLabel,
                'description' => $description,
                'group' => self::GROUP,
                'icon' => $icon,
                'stat' => self::statKeyRecipeCraft($recipeCode),
                'recipe_code' => $recipeCode,
                'profession_stage' => 1,
                'levels' => $levels,
            ],
        ];
    }
}
