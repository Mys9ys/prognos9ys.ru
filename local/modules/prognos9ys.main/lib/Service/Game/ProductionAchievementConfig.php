<?php

namespace Prognos9ys\Main\Service\Game;

/**
 * Ачивки производства: запуски крафта по рецептам, копии, полная библиотека.
 */
class ProductionAchievementConfig
{
    public const GROUP = AchievementConfig::GROUP_PRODUCTION;

    public const STAT_CRAFT_TOTAL = 'production_craft_total';
    public const STAT_COPY_TOTAL = 'production_copy_total';
    public const STAT_CRAFTABLE_LEARNED = 'production_craftable_learned';

    /** @var int[] */
    public const CRAFT_TOTAL_THRESHOLDS = [5, 25, 100, 500, 1000];

    /** @var int[] */
    public const COPY_THRESHOLDS = [1, 5, 25, 100];

    /** @var int[] */
    public const PROFESSION_STAGE1_THRESHOLDS = [10, 50, 150, 400, 1000];

    /** @var int[] */
    public const PROFESSION_STAGE2_THRESHOLDS = [1500, 3000, 6000, 12000, 25000];

    /** @var float[] */
    private const TOTAL_RUBLIUS = [1.0, 2.0, 5.0, 10.0, 20.0];

    /** @var int[] */
    private const TOTAL_CHESTS = [1, 1, 2, 3, 5];

    /** @var float[] */
    private const COPY_RUBLIUS = [1.0, 2.0, 5.0, 10.0];

    /** @var int[] */
    private const COPY_CHESTS = [1, 1, 2, 3];

    public static function statKeyProfessionCraft(string $professionCode, int $stage): string
    {
        $professionCode = trim($professionCode);
        if ($stage === 2) {
            return 'production_craft_' . $professionCode . '_2';
        }

        return 'production_craft_' . $professionCode;
    }

    public static function craftAchCode(string $professionCode, int $stage): string
    {
        $professionCode = trim($professionCode);
        if ($stage === 2) {
            return 'production_' . $professionCode . '_craft_2';
        }

        return 'production_' . $professionCode . '_craft';
    }

    /**
     * @return array<string, int>
     */
    public static function emptyStatsTemplate(): array
    {
        $stats = [
            self::STAT_CRAFT_TOTAL => 0,
            self::STAT_COPY_TOTAL => 0,
            self::STAT_CRAFTABLE_LEARNED => 0,
        ];

        foreach (ProfessionMaterialConfig::processingProfessions() as $code => $profession) {
            $stats[self::statKeyProfessionCraft($code, 1)] = 0;
            $stats[self::statKeyProfessionCraft($code, 2)] = 0;
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
     *   profession_code?:string,
     *   profession_stage?:int,
     *   levels: array<int, array{threshold:int,reward:array}>
     * }>
     */
    public static function getCatalogEntries(): array
    {
        $entries = [
            'production_craft_total' => self::buildLeveledAchievement(
                'Мастерская',
                'Запуски крафта по рецептам',
                self::STAT_CRAFT_TOTAL,
                self::CRAFT_TOTAL_THRESHOLDS,
                self::TOTAL_RUBLIUS,
                self::TOTAL_CHESTS,
                'chest_opener',
                1
            ),
            'production_copy_total' => self::buildCopyAchievement(),
            'production_learned_all' => self::buildLearnedAllAchievement(),
        ];

        foreach (ProfessionMaterialConfig::processingProfessions() as $code => $profession) {
            $entries = array_merge(
                $entries,
                self::buildProfessionCraftAchievement($code, $profession, 1),
                self::buildProfessionCraftAchievement($code, $profession, 2)
            );
        }

        return $entries;
    }

    /**
     * @param array<string, mixed> $profession
     * @return array<string, array>
     */
    private static function buildProfessionCraftAchievement(string $code, array $profession, int $stage): array
    {
        $achCode = self::craftAchCode($code, $stage);
        $thresholds = $stage === 1 ? self::PROFESSION_STAGE1_THRESHOLDS : self::PROFESSION_STAGE2_THRESHOLDS;
        $rublius = $stage === 1
            ? ProfessionAchievementConfig::NORMAL_RUBLIUS_STAGE1
            : ProfessionAchievementConfig::NORMAL_RUBLIUS_STAGE2;
        $chests = $stage === 1
            ? ProfessionAchievementConfig::NORMAL_CHESTS_STAGE1
            : ProfessionAchievementConfig::NORMAL_CHESTS_STAGE2;
        $suffix = $stage === 1 ? '' : ' (этап 2)';

        $levels = [];
        foreach ($thresholds as $index => $threshold) {
            $reward = [
                'rublius' => $rublius[$index],
                'chests' => $chests[$index],
                'chest_type' => self::resolveProfessionChestTypeForStage($stage, $index),
            ];

            if ($stage === 1 && $index === 4) {
                $reward['pennant'] = AchievementPennantConfig::professionPennantCode($code);
            }

            $levels[] = [
                'threshold' => $threshold,
                'reward' => $reward,
            ];
        }

        return [
            $achCode => [
                'title' => $profession['label'] . ': производство' . $suffix,
                'description' => 'Запуски крафта по рецептам профессии' . $suffix,
                'group' => self::GROUP,
                'icon' => 'total_all',
                'stat' => self::statKeyProfessionCraft($code, $stage),
                'profession_code' => $code,
                'profession_stage' => $stage,
                'levels' => $levels,
            ],
        ];
    }

    /**
     * @param int[] $thresholds
     * @param float[] $rublius
     * @param int[] $chests
     * @return array<string, array>
     */
    private static function buildLeveledAchievement(
        string $title,
        string $description,
        string $stat,
        array $thresholds,
        array $rublius,
        array $chests,
        string $icon,
        int $professionStage
    ): array {
        $levels = [];
        foreach ($thresholds as $index => $threshold) {
            $levels[] = [
                'threshold' => $threshold,
                'reward' => [
                    'rublius' => $rublius[$index] ?? 1.0,
                    'chests' => $chests[$index] ?? 1,
                    'chest_type' => self::resolveProfessionChestTypeForIndex($index),
                ],
            ];
        }

        $code = $stat === self::STAT_CRAFT_TOTAL ? 'production_craft_total' : $stat;

        return [
            'title' => $title,
            'description' => $description,
            'group' => self::GROUP,
            'icon' => $icon,
            'stat' => $stat,
            'profession_stage' => $professionStage,
            'levels' => $levels,
        ];
    }

    /**
     * @return array<string, array>
     */
    private static function buildCopyAchievement(): array
    {
        $levels = [];
        foreach (self::COPY_THRESHOLDS as $index => $threshold) {
            $levels[] = [
                'threshold' => $threshold,
                'reward' => [
                    'rublius' => self::COPY_RUBLIUS[$index] ?? 1.0,
                    'chests' => self::COPY_CHESTS[$index] ?? 1,
                    'chest_type' => self::resolveProfessionChestTypeForIndex($index),
                ],
            ];
        }

        return [
            'production_copy_total' => [
                'title' => 'Переписчик',
                'description' => 'Копии рецептов на чистый свиток',
                'group' => self::GROUP,
                'icon' => 'chest_warehouse',
                'stat' => self::STAT_COPY_TOTAL,
                'profession_stage' => 1,
                'levels' => $levels,
            ],
        ];
    }

    /**
     * @return array<string, array>
     */
    private static function buildLearnedAllAchievement(): array
    {
        $totalCraftable = count(ProfessionRecipeConfig::craftDefinitions());

        return [
            'production_learned_all' => [
                'title' => 'Полная библиотека',
                'description' => 'Изучены все рецепты производства',
                'group' => self::GROUP,
                'icon' => 'chest_warehouse',
                'stat' => self::STAT_CRAFTABLE_LEARNED,
                'profession_stage' => 1,
                'levels' => [
                    [
                        'threshold' => max(1, $totalCraftable),
                        'reward' => [
                            'rublius' => 25.0,
                            'chests' => 5,
                            'chest_type' => TreasureService::CHEST_TYPE_PROFESSION_TIER_3,
                        ],
                    ],
                ],
            ],
        ];
    }

    private static function resolveProfessionChestTypeForIndex(int $index): string
    {
        if ($index >= 4) {
            return TreasureService::CHEST_TYPE_PROFESSION_TIER_3;
        }

        if ($index >= 3) {
            return TreasureService::CHEST_TYPE_PROFESSION_TIER_2;
        }

        return TreasureService::CHEST_TYPE_PROFESSION_TIER_1;
    }

    private static function resolveProfessionChestTypeForStage(int $stage, int $index): string
    {
        if ($stage <= 1) {
            return TreasureService::CHEST_TYPE_PROFESSION_TIER_1;
        }

        return $index >= 3
            ? TreasureService::CHEST_TYPE_PROFESSION_TIER_3
            : TreasureService::CHEST_TYPE_PROFESSION_TIER_2;
    }
}
