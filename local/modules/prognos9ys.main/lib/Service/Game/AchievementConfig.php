<?php

namespace Prognos9ys\Main\Service\Game;

class AchievementConfig
{
    public const GROUP_WELCOME = 'welcome';
    public const GROUP_PROGNOSIS = 'prognosis';
    public const GROUP_CHM = 'chm';
    public const GROUP_QUALITY = 'quality';
    public const GROUP_LUCK = 'luck';
    public const GROUP_METRICS = 'metrics';
    public const GROUP_ECONOMY = 'economy';
    public const GROUP_PROFESSION = 'profession';
    public const GROUP_POTION = 'potion';
    public const GROUP_EXCHANGE = 'exchange';

    /**
     * @return array<string, array{
     *   title: string,
     *   description: string,
     *   group: string,
     *   icon: string,
     *   stat: string,
     *   levels: array<int, array{threshold:int,reward:array{rublius?:float,chests?:int,pennant?:string}|null}>
     * }>
     */
    public static function getCatalog(): array
    {
        $base = [
            // 1. Добро пожаловать / 1 прогноз / вымпел сайта
            'welcome' => [
                'title' => 'Добро пожаловать',
                'description' => 'Сделай 1 прогноз',
                'group' => self::GROUP_WELCOME,
                'icon' => 'welcome',
                'stat' => 'football_prognosis',
                'levels' => [
                    ['threshold' => 1, 'reward' => ['pennant' => 'site']],
                ],
            ],

            // 2. Прогнозист / 5,10,50,100,500 / рублиусы + сундуки
            'prognosis' => [
                'title' => 'Прогнозист',
                'description' => 'Количество футбольных прогнозов',
                'group' => self::GROUP_PROGNOSIS,
                'icon' => 'total_all',
                'stat' => 'football_prognosis',
                'levels' => [
                    ['threshold' => 5, 'reward' => ['rublius' => 1.0]],
                    ['threshold' => 10, 'reward' => ['rublius' => 2.0]],
                    ['threshold' => 50, 'reward' => ['rublius' => 5.0, 'chests' => 1]],
                    ['threshold' => 100, 'reward' => ['rublius' => 10.0, 'chests' => 2]],
                    ['threshold' => 500, 'reward' => ['rublius' => 50.0, 'chests' => 5]],
                ],
            ],

            // 3. ЧМ2026 / 10,50,100 / сундуки + рублиусы + вымпел ЧМ2026
            'chm2026' => [
                'title' => 'ЧМ2026',
                'description' => 'Прогнозы на матчи ЧМ-2026',
                'group' => self::GROUP_CHM,
                'icon' => 'chm2026',
                'stat' => 'chm_prognosis',
                'levels' => [
                    ['threshold' => 10, 'reward' => ['chests' => 1, 'rublius' => 2.0, 'pennant' => 'chm2026']],
                    ['threshold' => 50, 'reward' => ['chests' => 5, 'rublius' => 10.0]],
                    ['threshold' => 100, 'reward' => ['chests' => 10, 'rublius' => 20.0]],
                ],
            ],

            // 4. Отличный прогноз / 30..39 баллов
            'great_prediction' => [
                'title' => 'Отличный прогноз',
                'description' => 'Прогноз с результатом 30–39',
                'group' => self::GROUP_QUALITY,
                'icon' => 'rating_prodigy',
                'stat' => 'score_30_39',
                'levels' => [
                    ['threshold' => 3, 'reward' => ['rublius' => 2.0]],
                    ['threshold' => 7, 'reward' => ['rublius' => 4.0]],
                    ['threshold' => 20, 'reward' => ['rublius' => 8.0, 'chests' => 1]],
                    ['threshold' => 50, 'reward' => ['rublius' => 15.0, 'chests' => 2]],
                    ['threshold' => 100, 'reward' => ['rublius' => 30.0, 'chests' => 3]],
                ],
            ],

            // 5. Вундеркинд / 40+
            'prodigy' => [
                'title' => 'Вундеркинд',
                'description' => 'Прогноз с результатом 40+',
                'group' => self::GROUP_QUALITY,
                'icon' => 'prodigy',
                'stat' => 'score_40_plus',
                'levels' => [
                    ['threshold' => 1, 'reward' => ['rublius' => 5.0, 'chests' => 1]],
                    ['threshold' => 3, 'reward' => ['rublius' => 10.0]],
                    ['threshold' => 5, 'reward' => ['rublius' => 15.0, 'chests' => 2]],
                    ['threshold' => 10, 'reward' => ['rublius' => 25.0, 'chests' => 3]],
                    ['threshold' => 25, 'reward' => ['rublius' => 50.0, 'chests' => 5]],
                ],
            ],

            // 6. Еще повезет / 0 баллов / рублиусы
            'better_luck' => [
                'title' => 'Еще повезет',
                'description' => 'Прогнозы с 0 баллов',
                'group' => self::GROUP_LUCK,
                'icon' => 'luck',
                'stat' => 'score_0',
                'levels' => [
                    ['threshold' => 3, 'reward' => ['rublius' => 3.0]],
                    ['threshold' => 7, 'reward' => ['rublius' => 5.0]],
                    ['threshold' => 20, 'reward' => ['rublius' => 10.0]],
                    ['threshold' => 50, 'reward' => ['rublius' => 20.0]],
                    ['threshold' => 100, 'reward' => ['rublius' => 25.0]],
                ],
            ],

            // 7. Метрики: счёт / исход / сумма / разница (5,10,50,100,500)
            'metric_exact_score' => [
                'title' => 'Счет матча',
                'description' => 'Угаданные точные счета (футбол)',
                'group' => self::GROUP_METRICS,
                'icon' => 'scoreboard',
                'stat' => 'metric_exact_score',
                'levels' => [
                    ['threshold' => 5, 'reward' => ['rublius' => 2.0]],
                    ['threshold' => 10, 'reward' => ['rublius' => 3.0]],
                    ['threshold' => 50, 'reward' => ['rublius' => 7.0, 'chests' => 1]],
                    ['threshold' => 100, 'reward' => ['rublius' => 15.0, 'chests' => 2]],
                    ['threshold' => 500, 'reward' => ['rublius' => 40.0, 'chests' => 5]],
                ],
            ],
            'metric_outcome' => [
                'title' => 'Исход матча',
                'description' => 'Угаданные исходы (футбол)',
                'group' => self::GROUP_METRICS,
                'icon' => 'outcome',
                'stat' => 'metric_outcome',
                'levels' => [
                    ['threshold' => 5, 'reward' => ['rublius' => 1.0]],
                    ['threshold' => 10, 'reward' => ['rublius' => 2.0]],
                    ['threshold' => 50, 'reward' => ['rublius' => 5.0, 'chests' => 1]],
                    ['threshold' => 100, 'reward' => ['rublius' => 10.0, 'chests' => 2]],
                    ['threshold' => 500, 'reward' => ['rublius' => 25.0, 'chests' => 5]],
                ],
            ],
            'metric_total_goals' => [
                'title' => 'Сумма голов',
                'description' => 'Угаданные суммы голов (футбол)',
                'group' => self::GROUP_METRICS,
                'icon' => 'sum',
                'stat' => 'metric_total_goals',
                'levels' => [
                    ['threshold' => 5, 'reward' => ['rublius' => 1.0]],
                    ['threshold' => 10, 'reward' => ['rublius' => 2.0]],
                    ['threshold' => 50, 'reward' => ['rublius' => 5.0, 'chests' => 1]],
                    ['threshold' => 100, 'reward' => ['rublius' => 10.0, 'chests' => 2]],
                    ['threshold' => 500, 'reward' => ['rublius' => 25.0, 'chests' => 5]],
                ],
            ],
            'metric_goal_diff' => [
                'title' => 'Разница голов',
                'description' => 'Угаданные разницы голов (футбол)',
                'group' => self::GROUP_METRICS,
                'icon' => 'diff',
                'stat' => 'metric_goal_diff',
                'levels' => [
                    ['threshold' => 5, 'reward' => ['rublius' => 1.0]],
                    ['threshold' => 10, 'reward' => ['rublius' => 2.0]],
                    ['threshold' => 50, 'reward' => ['rublius' => 5.0, 'chests' => 1]],
                    ['threshold' => 100, 'reward' => ['rublius' => 10.0, 'chests' => 2]],
                    ['threshold' => 500, 'reward' => ['rublius' => 25.0, 'chests' => 5]],
                ],
            ],

            // 8. Суммарные баллы метрик: угловые / жёлтые / владение (20,50,100,250,500)
            'metric_corners' => [
                'title' => 'Σ Угловых',
                'description' => 'Баллы за угаданные угловые',
                'group' => self::GROUP_METRICS,
                'icon' => 'corners',
                'stat' => 'metric_corners',
                'levels' => [
                    ['threshold' => 50, 'reward' => ['rublius' => 1.0]],
                    ['threshold' => 100, 'reward' => ['rublius' => 3.0]],
                    ['threshold' => 200, 'reward' => ['rublius' => 5.0, 'chests' => 1]],
                    ['threshold' => 500, 'reward' => ['rublius' => 12.0, 'chests' => 2]],
                    ['threshold' => 1000, 'reward' => ['rublius' => 25.0, 'chests' => 3]],
                ],
            ],
            'metric_yellow' => [
                'title' => 'Σ Желтых',
                'description' => 'Баллы за угаданные жёлтые карточки',
                'group' => self::GROUP_METRICS,
                'icon' => 'yellow',
                'stat' => 'metric_yellow',
                'levels' => [
                    ['threshold' => 50, 'reward' => ['rublius' => 1.0]],
                    ['threshold' => 100, 'reward' => ['rublius' => 3.0]],
                    ['threshold' => 200, 'reward' => ['rublius' => 5.0, 'chests' => 1]],
                    ['threshold' => 500, 'reward' => ['rublius' => 12.0, 'chests' => 2]],
                    ['threshold' => 1000, 'reward' => ['rublius' => 25.0, 'chests' => 3]],
                ],
            ],
            'metric_possession' => [
                'title' => '% владения',
                'description' => 'Баллы за угаданный % владения мячом',
                'group' => self::GROUP_METRICS,
                'icon' => 'possession',
                'stat' => 'metric_possession',
                'levels' => [
                    ['threshold' => 50, 'reward' => ['rublius' => 1.0]],
                    ['threshold' => 100, 'reward' => ['rublius' => 3.0]],
                    ['threshold' => 200, 'reward' => ['rublius' => 5.0, 'chests' => 1]],
                    ['threshold' => 500, 'reward' => ['rublius' => 12.0, 'chests' => 2]],
                    ['threshold' => 1000, 'reward' => ['rublius' => 25.0, 'chests' => 3]],
                ],
            ],

            // 9. Редкие события — факт «ДА» (5,10,25,50,100)
            'rare_red' => [
                'title' => 'Красные факт',
                'description' => 'Точно угадана ровно 1 красная в матче',
                'group' => self::GROUP_METRICS,
                'icon' => 'red',
                'stat' => 'rare_red',
                'levels' => [
                    ['threshold' => 5, 'reward' => ['rublius' => 2.0]],
                    ['threshold' => 10, 'reward' => ['rublius' => 4.0]],
                    ['threshold' => 25, 'reward' => ['rublius' => 8.0, 'chests' => 1]],
                    ['threshold' => 50, 'reward' => ['rublius' => 15.0, 'chests' => 2]],
                    ['threshold' => 100, 'reward' => ['rublius' => 30.0, 'chests' => 3]],
                ],
            ],
            'rare_penalty' => [
                'title' => 'Пенальти факт',
                'description' => 'Точно угадан ровно 1 пенальти в матче',
                'group' => self::GROUP_METRICS,
                'icon' => 'penalty',
                'stat' => 'rare_penalty',
                'levels' => [
                    ['threshold' => 5, 'reward' => ['rublius' => 2.0]],
                    ['threshold' => 10, 'reward' => ['rublius' => 4.0]],
                    ['threshold' => 25, 'reward' => ['rublius' => 8.0, 'chests' => 1]],
                    ['threshold' => 50, 'reward' => ['rublius' => 15.0, 'chests' => 2]],
                    ['threshold' => 100, 'reward' => ['rublius' => 30.0, 'chests' => 3]],
                ],
            ],

            // 10. Точное количество > 1 (1,3,5,10,20)
            'wow_red' => [
                'title' => 'Ого красных',
                'description' => 'Точно угадано количество красных (2 и больше)',
                'group' => self::GROUP_METRICS,
                'icon' => 'wow_red',
                'stat' => 'wow_red',
                'levels' => [
                    ['threshold' => 1, 'reward' => ['rublius' => 3.0, 'chests' => 1]],
                    ['threshold' => 3, 'reward' => ['rublius' => 5.0]],
                    ['threshold' => 5, 'reward' => ['rublius' => 10.0, 'chests' => 1]],
                    ['threshold' => 10, 'reward' => ['rublius' => 20.0, 'chests' => 2]],
                    ['threshold' => 20, 'reward' => ['rublius' => 40.0, 'chests' => 3]],
                ],
            ],
            'wow_pen' => [
                'title' => 'Ого пенальти',
                'description' => 'Точно угадано количество пенальти (2 и больше)',
                'group' => self::GROUP_METRICS,
                'icon' => 'wow_pen',
                'stat' => 'wow_pen',
                'levels' => [
                    ['threshold' => 1, 'reward' => ['rublius' => 3.0, 'chests' => 1]],
                    ['threshold' => 3, 'reward' => ['rublius' => 5.0]],
                    ['threshold' => 5, 'reward' => ['rublius' => 10.0, 'chests' => 1]],
                    ['threshold' => 10, 'reward' => ['rublius' => 20.0, 'chests' => 2]],
                    ['threshold' => 20, 'reward' => ['rublius' => 40.0, 'chests' => 3]],
                ],
            ],

            // 11. Доп. время и серия пенальти (5,10,20,50,100)
            'metric_extra_time' => [
                'title' => 'Доп. время',
                'description' => 'Угадан факт дополнительного времени',
                'group' => self::GROUP_METRICS,
                'icon' => 'extra_time',
                'stat' => 'metric_extra_time',
                'levels' => [
                    ['threshold' => 5, 'reward' => ['rublius' => 2.0]],
                    ['threshold' => 10, 'reward' => ['rublius' => 4.0]],
                    ['threshold' => 20, 'reward' => ['rublius' => 8.0, 'chests' => 1]],
                    ['threshold' => 50, 'reward' => ['rublius' => 15.0, 'chests' => 2]],
                    ['threshold' => 100, 'reward' => ['rublius' => 30.0, 'chests' => 3]],
                ],
            ],
            'metric_shootout' => [
                'title' => 'Серия пенальти',
                'description' => 'Угадана серия пенальти после матча',
                'group' => self::GROUP_METRICS,
                'icon' => 'shootout',
                'stat' => 'metric_shootout',
                'levels' => [
                    ['threshold' => 5, 'reward' => ['rublius' => 2.0]],
                    ['threshold' => 10, 'reward' => ['rublius' => 4.0]],
                    ['threshold' => 20, 'reward' => ['rublius' => 8.0, 'chests' => 1]],
                    ['threshold' => 50, 'reward' => ['rublius' => 15.0, 'chests' => 2]],
                    ['threshold' => 100, 'reward' => ['rublius' => 30.0, 'chests' => 3]],
                ],
            ],

            // 12. Экономика
            'rich_bettor' => [
                'title' => 'Типстер',
                'description' => 'Выигрыши со ставок на исход — в прогнобаксах (любые соревнования)',
                'group' => self::GROUP_WELCOME,
                'icon' => 'tipster',
                'stat' => 'bet_winnings_prognobaks',
                'levels' => [
                    ['threshold' => 100, 'reward' => ['rublius' => 1.0]],
                    ['threshold' => 200, 'reward' => ['rublius' => 2.0]],
                    ['threshold' => 500, 'reward' => ['chests' => 1]],
                    ['threshold' => 1000, 'reward' => ['chests' => 2]],
                    ['threshold' => 2000, 'reward' => ['chests' => 3]],
                ],
            ],
            'chest_pioneer' => [
                'title' => 'Сундучивен',
                'description' => 'Откройте сундуки в инвентаре (любой тип)',
                'group' => self::GROUP_WELCOME,
                'icon' => 'chest_opener',
                'stat' => 'chests_opened',
                'levels' => [
                    ['threshold' => 5, 'reward' => ['rublius' => 1.0]],
                    ['threshold' => 10, 'reward' => ['rublius' => 2.0]],
                    ['threshold' => 25, 'reward' => ['chests' => 1]],
                    ['threshold' => 50, 'reward' => ['chests' => 2]],
                    ['threshold' => 100, 'reward' => ['chests' => 3]],
                ],
            ],
            'rublius_trader' => [
                'title' => 'Скрудж',
                'description' => 'Заработанные рублиусы — любые источники, кроме стартового бонуса',
                'group' => self::GROUP_WELCOME,
                'icon' => 'scrooge',
                'stat' => 'rublius_earned',
                'levels' => [
                    ['threshold' => 50, 'reward' => ['rublius' => 1.0]],
                    ['threshold' => 100, 'reward' => ['rublius' => 2.0]],
                    ['threshold' => 250, 'reward' => ['chests' => 1]],
                    ['threshold' => 500, 'reward' => ['chests' => 2]],
                    ['threshold' => 1000, 'reward' => ['chests' => 3]],
                ],
            ],
            'chest_collector' => [
                'title' => 'Целый склад',
                'description' => 'Полученные сундуки за матчи, уровень и ачивки (не покупка в лавке)',
                'group' => self::GROUP_WELCOME,
                'icon' => 'chest_warehouse',
                'stat' => 'chests_earned',
                'levels' => [
                    ['threshold' => 25, 'reward' => ['rublius' => 1.0]],
                    ['threshold' => 50, 'reward' => ['rublius' => 2.0]],
                    ['threshold' => 100, 'reward' => ['chests' => 1]],
                    ['threshold' => 250, 'reward' => ['chests' => 2]],
                    ['threshold' => 500, 'reward' => ['chests' => 3]],
                ],
            ],
        ];

        return array_merge(
            $base,
            ProfessionAchievementConfig::getCatalogEntries(),
            XpBankAchievementConfig::getCatalogEntries(),
            ExchangeBuyAchievementConfig::getCatalogEntries(),
            RecipeAchievementConfig::getCatalogEntries(),
            CollectionMegaAchievementConfig::getCatalogEntries()
        );
    }

    /**
     * Ачивки, награда за которые — сундук пула ЧМ-26 (не классический ачивочный).
     */
    public static function grantsWc26Chest(string $code): bool
    {
        return in_array($code, [
            'chm2026',
            CollectionMegaAchievementConfig::CODE_PENNANT,
            CollectionMegaAchievementConfig::CODE_SCARF,
        ], true);
    }

    public static function grantsProfessionChest(string $code): bool
    {
        if (strpos($code, 'prof_') === 0) {
            return true;
        }

        return in_array($code, [
            'recipe_learned',
            'recipe_album_craft',
            'exchange_buy_material_normal',
            'exchange_buy_material_premium',
            'exchange_buy_xp_mining',
            'exchange_buy_xp_crafting',
            'exchange_buy_recipe',
        ], true);
    }
}
