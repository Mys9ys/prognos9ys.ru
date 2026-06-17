<?php

namespace Prognos9ys\Main\Service\Game;

class AchievementConfig
{
    public const GROUP_WELCOME = 'welcome';
    public const GROUP_PROGNOSIS = 'prognosis';
    public const GROUP_CHM = 'chm';

    /**
     * @return array<string, array{title: string, description: string, group: string, stat?: string, threshold?: int}>
     */
    public static function getCatalog(): array
    {
        return [
            'welcome' => [
                'title' => 'Добро пожаловать',
                'description' => 'Регистрация на Прогносяусе',
                'group' => self::GROUP_WELCOME,
                'stat' => 'welcome',
                'threshold' => 1,
            ],
            'prognosis_first' => [
                'title' => 'Первый прогноз',
                'description' => 'Отправлен футбольный прогноз',
                'group' => self::GROUP_PROGNOSIS,
                'stat' => 'football_prognosis',
                'threshold' => 1,
            ],
            'prognosis_10' => [
                'title' => 'Десятка',
                'description' => '10 футбольных прогнозов',
                'group' => self::GROUP_PROGNOSIS,
                'stat' => 'football_prognosis',
                'threshold' => 10,
            ],
            'prognosis_50' => [
                'title' => 'Болельщик',
                'description' => '50 футбольных прогнозов',
                'group' => self::GROUP_PROGNOSIS,
                'stat' => 'football_prognosis',
                'threshold' => 50,
            ],
            'chm_first' => [
                'title' => 'ЧМ-2026',
                'description' => 'Прогноз на матч чемпионата мира',
                'group' => self::GROUP_CHM,
                'stat' => 'chm_prognosis',
                'threshold' => 1,
            ],
            'chm_5' => [
                'title' => 'Фанат ЧМ',
                'description' => '5 прогнозов на ЧМ-2026',
                'group' => self::GROUP_CHM,
                'stat' => 'chm_prognosis',
                'threshold' => 5,
            ],
        ];
    }
}
