<?php

namespace Prognos9ys\Main\Service\Game;

/**
 * Текстовый лор для энциклопедии: описания, истории, подсказки.
 * Числа и связи — в игровых конфигах; здесь только narrative.
 */
class GameLoreConfig
{
    /**
     * @var array<string, array<string, string>>
     */
    private const TEXT = [
        'materials' => [
            'log' => 'Бревно — основа столярного дела и первых построек. Лесорубы добывают его на ферме, переработчики превращают в доски.',
            'plank' => 'Гладкая доска из бревна. Нужна для рам, брусьев и многих строительных рецептов.',
            'ore' => 'Сырая руда из шахты. Плавильщики выплавляют из неё слитки для метизов и фурнитуры.',
        ],
        'professions' => [
            'woodcutter' => 'Лесоруб добывает бревна и редкий янтарь. Базовая профессия для всей деревянной ветки экономики.',
            'carpenter' => 'Столяр перерабатывает бревна в доски и крафтит деревянные детали — от рам до секций стен.',
        ],
        'recipes' => [
            'recipe_nails' => 'Простейший метиз: один слиток даёт партию гвоздей для сборки рам и мебели.',
            'recipe_beam' => 'Брус скрепляет конструкции. Из досок получается пара брусьев за цикл крафта.',
        ],
        'equipment' => [],
        'buildings' => [
            'estate_house_1' => 'Первый дом на усадьбе: фундамент, стены, крыша и дверь. Открывает следующий уровень развития участка.',
            'civic_city_hall' => 'Управа города ЧМ-26 — общий проект игроков. После сдачи открывает карту города и гражданские постройки.',
        ],
        'packs' => [
            'pack_recipe_basic' => 'Случайный базовый рецепт крафта. Удобен для старта производственной линии.',
            'pack_pennant_wc26' => 'Сувенирный пак ЧМ-26: внутри вымпел одной из сборных турнира.',
        ],
    ];

    public static function text(string $section, string $code): string
    {
        $section = trim($section);
        $code = trim($code);
        if ($section === '' || $code === '') {
            return '';
        }

        return (string)(self::TEXT[$section][$code] ?? '');
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function allForSection(string $section): array
    {
        $section = trim($section);
        if ($section === '') {
            return [];
        }

        return self::TEXT[$section] ?? [];
    }
}
