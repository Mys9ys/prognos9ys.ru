<?php

namespace Prognos9ys\Main\Service\Game;

/**
 * Конфиг сезонных наград ЧМ-26 (номинации + призы по местам).
 */
class SeasonAwardConfig
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_CLAIMED = 'claimed';

    public const DEFAULT_EVENT_ID = GameEconomyConfig::ANCHOR_EVENT_ID;

    /**
     * @return array<string, array{
     *   title: string,
     *   description: string,
     *   icon: string,
     *   kind: string
     * }>
     */
    public static function getNominations(): array
    {
        return [
            'all' => [
                'title' => 'Общий зачёт',
                'description' => 'Сводный рейтинг турнира',
                'icon' => '🏆',
                'kind' => 'selector',
            ],
            'result' => [
                'title' => 'Исход',
                'description' => 'Рейтинг по исходу матчей',
                'icon' => '🎯',
                'kind' => 'selector',
            ],
            'score' => [
                'title' => 'Точный счёт',
                'description' => 'Рейтинг по точному счёту',
                'icon' => '🔢',
                'kind' => 'selector',
            ],
            'diff' => [
                'title' => 'Разница голов',
                'description' => 'Рейтинг по разнице голов',
                'icon' => '📊',
                'kind' => 'selector',
            ],
            'corner' => [
                'title' => 'Угловые',
                'description' => 'Рейтинг по угловым',
                'icon' => '⛳',
                'kind' => 'selector',
            ],
            'domination' => [
                'title' => 'Владение',
                'description' => 'Рейтинг по владению',
                'icon' => '⏱️',
                'kind' => 'selector',
            ],
            'penalty' => [
                'title' => 'Пенальти',
                'description' => 'Пенальти в матче (сумма для обеих команд)',
                'icon' => '⚽',
                'kind' => 'selector',
            ],
            'best' => [
                'title' => 'Снайпер',
                'description' => 'Лучшие прогнозы (≥30 очков за матч)',
                'icon' => '🎯',
                'kind' => 'selector',
            ],
            'playoff' => [
                'title' => 'Драматург',
                'description' => 'Доп. время + серия пенальти',
                'icon' => '🎭',
                'kind' => 'playoff',
            ],
        ];
    }

    /**
     * @return array{prognobaks: float, chests: int, premium_scroll_days: int, cup: string}
     */
    public static function getRewardForPlace(string $selector, int $place): array
    {
        $selector = trim($selector);
        $place = max(1, min(3, $place));

        $table = self::getPrizeTable();
        $row = $table[$selector][$place] ?? null;
        if (!is_array($row)) {
            return [
                'prognobaks' => 0.0,
                'chests' => 0,
                'premium_scroll_days' => 0,
                'cup' => SeasonCupConfig::buildCode($selector, $place),
            ];
        }

        $cup = trim((string)($row['cup'] ?? ''));
        if ($cup === '') {
            $cup = SeasonCupConfig::buildCode($selector, $place);
        }

        return [
            'prognobaks' => (float)($row['prognobaks'] ?? 0),
            'chests' => (int)($row['chests'] ?? 0),
            'premium_scroll_days' => (int)($row['premium_scroll_days'] ?? 0),
            'cup' => $cup,
        ];
    }

    public static function buildAwardCode(string $selector, int $place): string
    {
        return 'wc26_' . trim($selector) . '_' . max(1, min(3, $place));
    }

    /**
     * @return array{label: string, icon: string, place: int, selector: string}
     */
    public static function getBadgeMeta(string $selector, int $place): array
    {
        $noms = self::getNominations();
        $title = (string)($noms[$selector]['title'] ?? $selector);
        $icons = [1 => '🥇', 2 => '🥈', 3 => '🥉'];
        $place = max(1, min(3, $place));

        return [
            'label' => $title . ' — ' . $place . '-е место',
            'icon' => $icons[$place] ?? '🏅',
            'place' => $place,
            'selector' => $selector,
        ];
    }

    /**
     * @return list<string>
     */
    public static function getSelectorCodes(): array
    {
        return array_keys(self::getNominations());
    }

    /**
     * Сундуки ЧМ-26 + памятный кубок на каждое призовое место.
     *
     * @return array<string, array<int, array{chests: int, premium_scroll_days?: int, cup: string, prognobaks?: float}>>
     */
    private static function getPrizeTable(): array
    {
        $withCup = static function (string $selector, int $place, array $reward): array {
            $reward['cup'] = SeasonCupConfig::buildCode($selector, $place);

            return $reward;
        };

        return [
            'all' => [
                1 => $withCup('all', 1, ['chests' => 10, 'premium_scroll_days' => 3]),
                2 => $withCup('all', 2, ['chests' => 6]),
                3 => $withCup('all', 3, ['chests' => 3]),
            ],
            'result' => [
                1 => $withCup('result', 1, ['chests' => 3]),
                2 => $withCup('result', 2, ['chests' => 2]),
                3 => $withCup('result', 3, ['chests' => 1]),
            ],
            'score' => [
                1 => $withCup('score', 1, ['chests' => 3]),
                2 => $withCup('score', 2, ['chests' => 2]),
                3 => $withCup('score', 3, ['chests' => 1]),
            ],
            'diff' => [
                1 => $withCup('diff', 1, ['chests' => 3]),
                2 => $withCup('diff', 2, ['chests' => 2]),
                3 => $withCup('diff', 3, ['chests' => 1]),
            ],
            'corner' => [
                1 => $withCup('corner', 1, ['chests' => 3]),
                2 => $withCup('corner', 2, ['chests' => 2]),
                3 => $withCup('corner', 3, ['chests' => 1]),
            ],
            'domination' => [
                1 => $withCup('domination', 1, ['chests' => 3]),
                2 => $withCup('domination', 2, ['chests' => 2]),
                3 => $withCup('domination', 3, ['chests' => 1]),
            ],
            'penalty' => [
                1 => $withCup('penalty', 1, ['chests' => 3]),
                2 => $withCup('penalty', 2, ['chests' => 2]),
                3 => $withCup('penalty', 3, ['chests' => 1]),
            ],
            'best' => [
                1 => $withCup('best', 1, ['chests' => 4]),
                2 => $withCup('best', 2, ['chests' => 3]),
                3 => $withCup('best', 3, ['chests' => 2]),
            ],
            'playoff' => [
                1 => $withCup('playoff', 1, ['chests' => 4]),
                2 => $withCup('playoff', 2, ['chests' => 3]),
                3 => $withCup('playoff', 3, ['chests' => 2]),
            ],
        ];
    }
}
