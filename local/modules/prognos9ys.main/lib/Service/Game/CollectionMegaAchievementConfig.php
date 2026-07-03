<?php

namespace Prognos9ys\Main\Service\Game;

/**
 * Мега-ачивки за вклейку вымпелов/шарфов ЧМ-26 в альбом (16 / 32 / 48).
 */
class CollectionMegaAchievementConfig
{
    public const GROUP_COLLECTION = 'collection';

    public const CODE_PENNANT = 'collection_pennant_wc26';
    public const CODE_SCARF = 'collection_scarf_wc26';
    public const CODE_ACHIEVEMENT_PENNANT = 'collection_pennant_achievement';

    /**
     * @return array<string, array{
     *   title: string,
     *   description: string,
     *   group: string,
     *   icon: string,
     *   stat: string,
     *   levels: array<int, array{threshold:int,reward:array<string, mixed>|null}>
     * }>
     */
    public static function getCatalogEntries(): array
    {
        return [
            self::CODE_PENNANT => [
                'title' => 'Мега: вымпелы ЧМ-26',
                'description' => 'Уникальные сборные, вклеенные в альбом вымпелов',
                'group' => self::GROUP_COLLECTION,
                'icon' => 'pennant_chm2026',
                'stat' => 'album_pennant_glued',
                'levels' => [
                    [
                        'threshold' => 16,
                        'reward' => [
                            'chests' => 12,
                            'chest_type' => 'wc26',
                            'prognobaks' => 300.0,
                            'rublius' => 20.0,
                        ],
                    ],
                    [
                        'threshold' => 32,
                        'reward' => [
                            'chests' => 24,
                            'chest_type' => 'wc26',
                            'prognobaks' => 600.0,
                            'rublius' => 40.0,
                        ],
                    ],
                    [
                        'threshold' => 48,
                        'reward' => [
                            'chests' => 48,
                            'chest_type' => 'wc26',
                            'prognobaks' => 1200.0,
                            'rublius' => 80.0,
                        ],
                    ],
                ],
            ],
            self::CODE_SCARF => [
                'title' => 'Мега: шарфы ЧМ-26',
                'description' => 'Уникальные сборные, вклеенные в альбом шарфов',
                'group' => self::GROUP_COLLECTION,
                'icon' => 'scarf',
                'stat' => 'album_scarf_glued',
                'levels' => [
                    [
                        'threshold' => 16,
                        'reward' => [
                            'chests' => 16,
                            'chest_type' => 'wc26',
                            'prognobaks' => 500.0,
                            'rublius' => 30.0,
                        ],
                    ],
                    [
                        'threshold' => 32,
                        'reward' => [
                            'chests' => 32,
                            'chest_type' => 'wc26',
                            'prognobaks' => 1000.0,
                            'rublius' => 60.0,
                        ],
                    ],
                    [
                        'threshold' => 48,
                        'reward' => [
                            'chests' => 64,
                            'chest_type' => 'wc26',
                            'prognobaks' => 2000.0,
                            'rublius' => 120.0,
                        ],
                    ],
                ],
            ],
            self::CODE_ACHIEVEMENT_PENNANT => [
                'title' => 'Мега: альбом достижений',
                'description' => 'Вымпелы достижений, вклеенные в альбом',
                'group' => self::GROUP_COLLECTION,
                'icon' => 'pennant_site',
                'stat' => 'album_achievement_pennant_glued',
                'levels' => [
                    [
                        'threshold' => 10,
                        'reward' => [
                            'chests' => 3,
                            'chest_type' => TreasureService::CHEST_TYPE_PROFESSION_TIER_2,
                            'prognobaks' => 200.0,
                            'rublius' => 10.0,
                        ],
                    ],
                    [
                        'threshold' => 20,
                        'reward' => [
                            'chests' => 5,
                            'chest_type' => TreasureService::CHEST_TYPE_PROFESSION_TIER_2,
                            'prognobaks' => 400.0,
                            'rublius' => 20.0,
                        ],
                    ],
                    [
                        'threshold' => 30,
                        'reward' => [
                            'chests' => 8,
                            'chest_type' => TreasureService::CHEST_TYPE_PROFESSION_TIER_3,
                            'prognobaks' => 600.0,
                            'rublius' => 30.0,
                        ],
                    ],
                    [
                        'threshold' => 40,
                        'reward' => [
                            'chests' => 10,
                            'chest_type' => TreasureService::CHEST_TYPE_PROFESSION_TIER_3,
                            'prognobaks' => 800.0,
                            'rublius' => 40.0,
                        ],
                    ],
                    [
                        'threshold' => 50,
                        'reward' => [
                            'chests' => 12,
                            'chest_type' => TreasureService::CHEST_TYPE_PROFESSION_TIER_3,
                            'prognobaks' => 1000.0,
                            'rublius' => 50.0,
                        ],
                    ],
                ],
            ],
        ];
    }

    public static function achievementCodeForCollection(string $collection): ?string
    {
        if ($collection === AlbumConfig::COLLECTION_PENNANT_WC26) {
            return self::CODE_PENNANT;
        }
        if ($collection === AlbumConfig::COLLECTION_SCARF_WC26) {
            return self::CODE_SCARF;
        }
        if ($collection === AlbumConfig::COLLECTION_PENNANT_ACHIEVEMENT) {
            return self::CODE_ACHIEVEMENT_PENNANT;
        }

        return null;
    }
}
