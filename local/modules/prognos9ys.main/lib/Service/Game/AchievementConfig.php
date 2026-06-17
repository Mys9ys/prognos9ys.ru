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
        return [
            // 1. Добро пожаловать / 1 прогноз / вымпел сайта
            'welcome' => [
                'title' => 'Добро пожаловать',
                'description' => 'Сделай 1 прогноз',
                'group' => self::GROUP_WELCOME,
                'icon' => '🏳️',
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
                'icon' => '⚽',
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
                'icon' => '🏆',
                'stat' => 'chm_prognosis',
                'levels' => [
                    ['threshold' => 10, 'reward' => ['chests' => 1, 'rublius' => 2.0, 'pennant' => 'chm2026']],
                    ['threshold' => 50, 'reward' => ['chests' => 5, 'rublius' => 10.0]],
                    ['threshold' => 100, 'reward' => ['chests' => 10, 'rublius' => 20.0]],
                ],
            ],

            // 4. Отличный прогноз / 30..39 баллов / reward позже
            'great_prediction' => [
                'title' => 'Отличный прогноз',
                'description' => 'Прогноз с результатом 30–39',
                'group' => self::GROUP_QUALITY,
                'icon' => '⭐',
                'stat' => 'score_30_39',
                'levels' => [
                    ['threshold' => 3, 'reward' => null],
                    ['threshold' => 7, 'reward' => null],
                    ['threshold' => 20, 'reward' => null],
                    ['threshold' => 50, 'reward' => null],
                    ['threshold' => 100, 'reward' => null],
                ],
            ],

            // 5. Вундеркинд / 40+ / reward позже
            'prodigy' => [
                'title' => 'Вундеркинд',
                'description' => 'Прогноз с результатом 40+',
                'group' => self::GROUP_QUALITY,
                'icon' => '🧠',
                'stat' => 'score_40_plus',
                'levels' => [
                    ['threshold' => 1, 'reward' => null],
                    ['threshold' => 3, 'reward' => null],
                    ['threshold' => 5, 'reward' => null],
                    ['threshold' => 10, 'reward' => null],
                    ['threshold' => 25, 'reward' => null],
                ],
            ],

            // 6. Еще повезет / 0 баллов / рублиусы
            'better_luck' => [
                'title' => 'Еще повезет',
                'description' => 'Прогнозы с 0 баллов',
                'group' => self::GROUP_LUCK,
                'icon' => '🍀',
                'stat' => 'score_0',
                'levels' => [
                    ['threshold' => 3, 'reward' => ['rublius' => 3.0]],
                    ['threshold' => 7, 'reward' => ['rublius' => 5.0]],
                    ['threshold' => 20, 'reward' => ['rublius' => 10.0]],
                    ['threshold' => 50, 'reward' => ['rublius' => 20.0]],
                    ['threshold' => 100, 'reward' => ['rublius' => 25.0]],
                ],
            ],

            // 7. Блок одинаковых ачивок (счет/исход/сумма/разница) — награда позже
            'metric_exact_score' => [
                'title' => 'Метрика: Счет',
                'description' => 'Угаданные точные счета (футбол)',
                'group' => self::GROUP_METRICS,
                'icon' => '🎯',
                'stat' => 'metric_exact_score',
                'levels' => [
                    ['threshold' => 5, 'reward' => null],
                    ['threshold' => 10, 'reward' => null],
                    ['threshold' => 50, 'reward' => null],
                    ['threshold' => 100, 'reward' => null],
                    ['threshold' => 500, 'reward' => null],
                ],
            ],
            'metric_outcome' => [
                'title' => 'Метрика: Исход',
                'description' => 'Угаданные исходы (футбол)',
                'group' => self::GROUP_METRICS,
                'icon' => '✅',
                'stat' => 'metric_outcome',
                'levels' => [
                    ['threshold' => 5, 'reward' => null],
                    ['threshold' => 10, 'reward' => null],
                    ['threshold' => 50, 'reward' => null],
                    ['threshold' => 100, 'reward' => null],
                    ['threshold' => 500, 'reward' => null],
                ],
            ],
            'metric_total_goals' => [
                'title' => 'Метрика: Голы',
                'description' => 'Угаданные суммы голов (футбол)',
                'group' => self::GROUP_METRICS,
                'icon' => '🥅',
                'stat' => 'metric_total_goals',
                'levels' => [
                    ['threshold' => 5, 'reward' => null],
                    ['threshold' => 10, 'reward' => null],
                    ['threshold' => 50, 'reward' => null],
                    ['threshold' => 100, 'reward' => null],
                    ['threshold' => 500, 'reward' => null],
                ],
            ],
            'metric_goal_diff' => [
                'title' => 'Метрика: Разница',
                'description' => 'Угаданные разницы голов (футбол)',
                'group' => self::GROUP_METRICS,
                'icon' => '➗',
                'stat' => 'metric_goal_diff',
                'levels' => [
                    ['threshold' => 5, 'reward' => null],
                    ['threshold' => 10, 'reward' => null],
                    ['threshold' => 50, 'reward' => null],
                    ['threshold' => 100, 'reward' => null],
                    ['threshold' => 500, 'reward' => null],
                ],
            ],

            // 8. Плавающие метрики (угловые/желтые/% владения) — награда позже
            'metric_variable_points' => [
                'title' => 'Плавающие метрики',
                'description' => 'Сумма баллов за угловые/ЖК/% владения',
                'group' => self::GROUP_METRICS,
                'icon' => '📈',
                'stat' => 'metric_variable_points',
                'levels' => [
                    ['threshold' => 20, 'reward' => null],
                    ['threshold' => 50, 'reward' => null],
                    ['threshold' => 100, 'reward' => null],
                    ['threshold' => 250, 'reward' => null],
                    ['threshold' => 500, 'reward' => null],
                ],
            ],

            // 9. Редкие события (красная/пенальти) — только “ДА” (факт события) — награда позже
            'rare_events_yes' => [
                'title' => 'Редкие события',
                'description' => 'Угадан факт красной карточки или пенальти (только “ДА”)',
                'group' => self::GROUP_METRICS,
                'icon' => '🟥',
                'stat' => 'rare_events_yes',
                'levels' => [
                    ['threshold' => 5, 'reward' => null],
                    ['threshold' => 10, 'reward' => null],
                    ['threshold' => 25, 'reward' => null],
                    ['threshold' => 50, 'reward' => null],
                    ['threshold' => 100, 'reward' => null],
                ],
            ],

            // 10. Невероятные события (точное количество > 1) — награда позже
            'wow_red' => [
                'title' => 'Ого красных',
                'description' => 'Угадано точное количество красных (больше 1)',
                'group' => self::GROUP_METRICS,
                'icon' => '🤯',
                'stat' => 'wow_red',
                'levels' => [
                    ['threshold' => 1, 'reward' => null],
                    ['threshold' => 3, 'reward' => null],
                    ['threshold' => 5, 'reward' => null],
                    ['threshold' => 10, 'reward' => null],
                    ['threshold' => 20, 'reward' => null],
                ],
            ],
            'wow_pen' => [
                'title' => 'Ого пенальти',
                'description' => 'Угадано точное количество пенальти (больше 1)',
                'group' => self::GROUP_METRICS,
                'icon' => '🤯',
                'stat' => 'wow_pen',
                'levels' => [
                    ['threshold' => 1, 'reward' => null],
                    ['threshold' => 3, 'reward' => null],
                    ['threshold' => 5, 'reward' => null],
                    ['threshold' => 10, 'reward' => null],
                    ['threshold' => 20, 'reward' => null],
                ],
            ],

            // 11. Доп. время / серия пенальти — награда позже
            'extra_time_or_pen_series' => [
                'title' => 'Доп. время и пенальти',
                'description' => 'Угадан факт доп. времени или серии пенальти',
                'group' => self::GROUP_METRICS,
                'icon' => '⏱️',
                'stat' => 'extra_time_or_pen_series',
                'levels' => [
                    ['threshold' => 5, 'reward' => null],
                    ['threshold' => 10, 'reward' => null],
                    ['threshold' => 20, 'reward' => null],
                    ['threshold' => 50, 'reward' => null],
                    ['threshold' => 100, 'reward' => null],
                ],
            ],
        ];
    }
}
