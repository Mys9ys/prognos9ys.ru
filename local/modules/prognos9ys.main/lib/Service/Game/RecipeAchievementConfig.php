<?php

namespace Prognos9ys\Main\Service\Game;

/**
 * Ачивки за рецепты: изучение и крафт альбомов.
 */
class RecipeAchievementConfig
{
    public const GROUP = AchievementConfig::GROUP_PRODUCTION;

    public const STAT_LEARNED = 'recipe_learned';
    public const STAT_ALBUM_CRAFT = 'recipe_album_craft';

    /** @var int[] */
    public const THRESHOLDS = [1, 3, 5, 10, 25];

    /**
     * @return array<string, int>
     */
    public static function emptyStatsTemplate(): array
    {
        return [
            self::STAT_LEARNED => 0,
            self::STAT_ALBUM_CRAFT => 0,
        ];
    }

    /**
     * @return array<string, array{
     *   title:string,
     *   description:string,
     *   group:string,
     *   icon:string,
     *   stat:string,
     *   levels: array<int, array{threshold:int,reward:array}>
     * }>
     */
    public static function getCatalogEntries(): array
    {
        $levels = [];
        $rubliusDefs = [1.0, 2.0, 4.0, 8.0, 15.0];
        foreach (self::THRESHOLDS as $index => $threshold) {
            $levels[] = [
                'threshold' => $threshold,
                'reward' => array_merge(
                    ['rublius' => $rubliusDefs[$index] ?? 1.0],
                    ProductionAchievementConfig::chestPacksForLevelIndex($index)
                ),
            ];
        }

        return [
            'recipe_learned' => [
                'title' => 'Книжник',
                'description' => 'Изученные рецепты',
                'group' => self::GROUP,
                'icon' => 'chest_warehouse',
                'stat' => self::STAT_LEARNED,
                'profession_stage' => 1,
                'levels' => $levels,
            ],
            'recipe_album_craft' => [
                'title' => 'Переплётчик',
                'description' => 'Крафт альбомов по рецепту',
                'group' => self::GROUP,
                'icon' => 'chest_opener',
                'stat' => self::STAT_ALBUM_CRAFT,
                'profession_stage' => 1,
                'levels' => $levels,
            ],
        ];
    }
}
