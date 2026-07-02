<?php

namespace Prognos9ys\Main\Service\Game;

/**
 * Ачивки за покупки на бирже (по исполненным сделкам, в штуках).
 */
class ExchangeBuyAchievementConfig
{
    public const GROUP = 'exchange';

    /** @var int[] */
    public const THRESHOLDS_MATERIAL_NORMAL = [500, 1000, 2000, 5000, 10000];

    /** @var int[] */
    public const THRESHOLDS_MATERIAL_PREMIUM = [25, 50, 100, 250, 500];

    /** @var int[] */
    public const THRESHOLDS_CCG = [10, 25, 50, 100, 250];

    /** @var int[] */
    public const THRESHOLDS_SOUVENIR = [10, 25, 50, 100, 200];

    /** @var int[] */
    public const THRESHOLDS_CERT = [3, 5, 10];

    /** @var int[] */
    public const THRESHOLDS_XP_SPLIT = [20, 40, 80, 150, 300];

    /** @var int[] */
    public const THRESHOLDS_CHEST = [10, 25, 50, 100, 250];

    public const STAT_MATERIAL_NORMAL = 'exchange_buy_material_normal';
    public const STAT_MATERIAL_PREMIUM = 'exchange_buy_material_premium';
    public const STAT_CCG = 'exchange_buy_ccg_pack';
    public const STAT_SOUVENIR = 'exchange_buy_souvenir';
    public const STAT_CERT = 'exchange_buy_cert';
    public const STAT_XP_PLAYER_25 = 'exchange_buy_xp_player_25';
    public const STAT_XP_PLAYER_50 = 'exchange_buy_xp_player_50';
    public const STAT_XP_MINING = 'exchange_buy_xp_mining';
    public const STAT_XP_CRAFTING = 'exchange_buy_xp_crafting';
    public const STAT_CHEST = 'exchange_buy_chest';
    public const STAT_RECIPE = 'exchange_buy_recipe';

    /**
     * @return array<string, int>
     */
    public static function emptyStatsTemplate(): array
    {
        return [
            self::STAT_MATERIAL_NORMAL => 0,
            self::STAT_MATERIAL_PREMIUM => 0,
            self::STAT_CCG => 0,
            self::STAT_SOUVENIR => 0,
            self::STAT_CERT => 0,
            self::STAT_XP_PLAYER_25 => 0,
            self::STAT_XP_PLAYER_50 => 0,
            self::STAT_XP_MINING => 0,
            self::STAT_XP_CRAFTING => 0,
            self::STAT_CHEST => 0,
            self::STAT_RECIPE => 0,
        ];
    }

    public static function resolveBuyStatKey(string $kind, string $code, string $category): string
    {
        $kind = trim($kind);
        $code = trim($code);
        $category = trim($category);

        if ($kind === ExchangeConfig::KIND_MATERIAL) {
            return $category === ExchangeConfig::MATERIAL_CATEGORY_PREMIUM
                ? self::STAT_MATERIAL_PREMIUM
                : self::STAT_MATERIAL_NORMAL;
        }

        if ($kind === ExchangeConfig::KIND_CHEST) {
            return self::STAT_CHEST;
        }

        if ($kind === ExchangeConfig::KIND_PENNANT) {
            return self::STAT_SOUVENIR;
        }

        if ($kind === ExchangeConfig::KIND_LOOT) {
            if ($category === ChestLootConfig::CATEGORY_XP_BANK) {
                if ($code === 'xp_bank_player_25') {
                    return self::STAT_XP_PLAYER_25;
                }
                if ($code === 'xp_bank_player_50') {
                    return self::STAT_XP_PLAYER_50;
                }
                if (strpos($code, 'mining') !== false) {
                    return self::STAT_XP_MINING;
                }
                if (strpos($code, 'crafting') !== false) {
                    return self::STAT_XP_CRAFTING;
                }

                return self::STAT_XP_PLAYER_25;
            }

            if ($category === ChestLootConfig::CATEGORY_CERT) {
                return self::STAT_CERT;
            }

            if ($category === ChestLootConfig::CATEGORY_RECIPE) {
                return self::STAT_RECIPE;
            }

            if ($category === ChestLootConfig::CATEGORY_PACK) {
                if (self::isSouvenirPackCode($code)) {
                    return self::STAT_SOUVENIR;
                }

                return self::STAT_CCG;
            }

            if (self::isSouvenirLootCode($code)) {
                return self::STAT_SOUVENIR;
            }
        }

        return '';
    }

    private static function isSouvenirPackCode(string $code): bool
    {
        return strpos($code, 'pennant') !== false || strpos($code, 'scarf') !== false;
    }

    private static function isSouvenirLootCode(string $code): bool
    {
        return strpos($code, 'scarf') !== false || strpos($code, 'pennant') !== false;
    }

    /**
     * @return array<int, array{threshold:int,reward:array}>
     */
    public static function buildFiveTierRewards(): array
    {
        $defs = [
            ['rublius' => 1.0, 'chests' => 1],
            ['rublius' => 3.0, 'chests' => 1],
            ['rublius' => 5.0, 'chests' => 1],
            ['rublius' => 10.0, 'chests' => 3],
            ['rublius' => 20.0, 'chests' => 5],
        ];

        return self::levelsFromDefs(self::THRESHOLDS_MATERIAL_NORMAL, $defs);
    }

    /**
     * @return array<int, array{threshold:int,reward:array}>
     */
    public static function buildThreeTierRewards(): array
    {
        $defs = [
            ['rublius' => 1.0, 'chests' => 1],
            ['rublius' => 3.0, 'chests' => 1],
            ['rublius' => 5.0, 'chests' => 1],
        ];

        return self::levelsFromDefs(self::THRESHOLDS_CERT, $defs);
    }

    /**
     * @param int[] $thresholds
     * @param array<int, array{rublius:float,chests:int}> $rewardDefs
     * @return array<int, array{threshold:int,reward:array}>
     */
    private static function levelsFromDefs(array $thresholds, array $rewardDefs, bool $professionChests = false): array
    {
        $levels = [];
        foreach ($thresholds as $index => $threshold) {
            $reward = $rewardDefs[$index] ?? ['rublius' => 1.0, 'chests' => 1];
            $levels[] = [
                'threshold' => $threshold,
                'reward' => [
                    'rublius' => $reward['rublius'],
                    'chests' => $reward['chests'],
                    'chest_type' => $professionChests
                        ? self::resolveProfessionChestTypeForIndex($index)
                        : 'achievement',
                ],
            ];
        }

        return $levels;
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
        $fiveTierRewards = [
            ['rublius' => 1.0, 'chests' => 1],
            ['rublius' => 3.0, 'chests' => 1],
            ['rublius' => 5.0, 'chests' => 1],
            ['rublius' => 10.0, 'chests' => 3],
            ['rublius' => 20.0, 'chests' => 5],
        ];
        $threeTier = self::buildThreeTierRewards();
        $xpLevels = self::levelsFromDefs(self::THRESHOLDS_XP_SPLIT, $fiveTierRewards);

        $materialRewards = [
            ['rublius' => 1.0, 'chests' => 1],
            ['rublius' => 3.0, 'chests' => 1],
            ['rublius' => 5.0, 'chests' => 1],
            ['rublius' => 10.0, 'chests' => 3],
            ['rublius' => 20.0, 'chests' => 5],
        ];

        return [
            'exchange_buy_material_normal' => self::professionEntry(
                'Закупщик: материалы',
                'Куплено обычных материалов на бирже',
                self::STAT_MATERIAL_NORMAL,
                self::levelsFromDefs(self::THRESHOLDS_MATERIAL_NORMAL, $materialRewards, true)
            ),
            'exchange_buy_material_premium' => self::entry(
                'Закупщик: премиум',
                'Куплено премиум-материалов на бирже',
                self::STAT_MATERIAL_PREMIUM,
                self::levelsFromDefs(self::THRESHOLDS_MATERIAL_PREMIUM, [
                    ['rublius' => 1.0, 'chests' => 1],
                    ['rublius' => 3.0, 'chests' => 1],
                    ['rublius' => 5.0, 'chests' => 1],
                    ['rublius' => 10.0, 'chests' => 3],
                    ['rublius' => 20.0, 'chests' => 5],
                ])
            ),
            'exchange_buy_ccg' => self::entry(
                'Коллекционер ККИ',
                'Куплено паков карточек на бирже',
                self::STAT_CCG,
                self::levelsFromDefs(self::THRESHOLDS_CCG, [
                    ['rublius' => 1.0, 'chests' => 1],
                    ['rublius' => 3.0, 'chests' => 1],
                    ['rublius' => 5.0, 'chests' => 1],
                    ['rublius' => 10.0, 'chests' => 3],
                    ['rublius' => 20.0, 'chests' => 5],
                ])
            ),
            'exchange_buy_souvenir' => self::entry(
                'Сувенирщик',
                'Куплено вымпелов и шарфов на бирже',
                self::STAT_SOUVENIR,
                self::levelsFromDefs(self::THRESHOLDS_SOUVENIR, [
                    ['rublius' => 1.0, 'chests' => 1],
                    ['rublius' => 3.0, 'chests' => 1],
                    ['rublius' => 5.0, 'chests' => 1],
                    ['rublius' => 10.0, 'chests' => 3],
                    ['rublius' => 20.0, 'chests' => 5],
                ])
            ),
            'exchange_buy_cert' => self::entry(
                'Лицензиат',
                'Куплено лицензий на бирже',
                self::STAT_CERT,
                $threeTier
            ),
            'exchange_buy_xp_player_25' => self::entry(
                'Алхимик: XP игрока (25)',
                'Куплено банок XP игрока (25) на бирже',
                self::STAT_XP_PLAYER_25,
                $xpLevels
            ),
            'exchange_buy_xp_player_50' => self::entry(
                'Алхимик: XP игрока (50)',
                'Куплено банок XP игрока (50) на бирже',
                self::STAT_XP_PLAYER_50,
                $xpLevels
            ),
            'exchange_buy_xp_mining' => self::professionEntry(
                'Алхимик: XP добычи',
                'Куплено банок XP добычи на бирже',
                self::STAT_XP_MINING,
                self::levelsFromDefs(self::THRESHOLDS_XP_SPLIT, $fiveTierRewards, true)
            ),
            'exchange_buy_xp_crafting' => self::professionEntry(
                'Алхимик: XP крафта',
                'Куплено банок XP крафта на бирже',
                self::STAT_XP_CRAFTING,
                self::levelsFromDefs(self::THRESHOLDS_XP_SPLIT, $fiveTierRewards, true)
            ),
            'exchange_buy_chest' => self::entry(
                'Охотник за сундуками',
                'Куплено сундуков на бирже',
                self::STAT_CHEST,
                self::levelsFromDefs(self::THRESHOLDS_CHEST, [
                    ['rublius' => 1.0, 'chests' => 1],
                    ['rublius' => 3.0, 'chests' => 1],
                    ['rublius' => 5.0, 'chests' => 1],
                    ['rublius' => 10.0, 'chests' => 3],
                    ['rublius' => 20.0, 'chests' => 5],
                ])
            ),
            'exchange_buy_recipe' => self::professionEntry(
                'Библиотекарь',
                'Куплено рецептов на бирже',
                self::STAT_RECIPE,
                self::levelsFromDefs(self::THRESHOLDS_CCG, [
                    ['rublius' => 1.0, 'chests' => 1],
                    ['rublius' => 3.0, 'chests' => 1],
                    ['rublius' => 5.0, 'chests' => 1],
                    ['rublius' => 10.0, 'chests' => 3],
                    ['rublius' => 20.0, 'chests' => 5],
                ], true)
            ),
        ];
    }

    /**
     * @param array<int, array{threshold:int,reward:array}> $levels
     * @return array<string, mixed>
     */
    private static function professionEntry(string $title, string $description, string $stat, array $levels): array
    {
        return array_merge(self::entry($title, $description, $stat, $levels), [
            'group' => AchievementConfig::GROUP_PROFESSION,
            'profession_stage' => 1,
        ]);
    }

    /**
     * @param array<int, array{threshold:int,reward:array}> $levels
     * @return array<string, mixed>
     */
    private static function entry(string $title, string $description, string $stat, array $levels): array
    {
        return [
            'title' => $title,
            'description' => $description,
            'group' => self::GROUP,
            'icon' => 'scrooge',
            'stat' => $stat,
            'levels' => $levels,
        ];
    }
}
