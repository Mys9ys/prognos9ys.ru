<?php

namespace Prognos9ys\Main\Service\Game;

/**
 * Выбор добывающей профессии для seed-ботов.
 *
 * База — суммарный спрос материалов рецептов усадьбы и гос. строек
 * (log+plank → лесоруб, stone_block → каменщик, ingot → рудокоп, window_glass → песок).
 * Хлопок в рецептах пока не фигурирует — доля добавлена под дом ур.2 и ткани.
 *
 * Профили распределения (сумма весов = 100):
 * - recipe_cotton_10 — от рецептов + 10% хлопок: 48/19/10/13/10
 * - cotton_heavy     — больше хлопка: 45/18/10/10/17
 * - round            — круглые числа (по умолчанию): 50/20/12/10/8
 */
class BotProfessionPickConfig
{
    public const DEFAULT_PROFILE = 'recipe_cotton_10';

    public const PROFILE_RECIPE_COTTON_10 = 'recipe_cotton_10';
    public const PROFILE_COTTON_HEAVY = 'cotton_heavy';
    public const PROFILE_ROUND = 'round';

    /**
     * @return array<string, int>
     */
    public static function gatheringWeights(string $profile = self::DEFAULT_PROFILE): array
    {
        switch ($profile) {
            case self::PROFILE_COTTON_HEAVY:
                return [
                    'woodcutter' => 45,
                    'quarryman' => 18,
                    'miner' => 10,
                    'sandgatherer' => 10,
                    'cottongatherer' => 17,
                ];
            case self::PROFILE_ROUND:
                return [
                    'woodcutter' => 50,
                    'quarryman' => 20,
                    'miner' => 12,
                    'sandgatherer' => 10,
                    'cottongatherer' => 8,
                ];
            case self::PROFILE_RECIPE_COTTON_10:
            default:
                return [
                    'woodcutter' => 48,
                    'quarryman' => 19,
                    'miner' => 10,
                    'sandgatherer' => 13,
                    'cottongatherer' => 10,
                ];
        }
    }

    /**
     * @return array<string, string>
     */
    public static function profileLabels(): array
    {
        return [
            self::PROFILE_RECIPE_COTTON_10 => 'Рецепт усадьбы + 10% хлопок (48/19/10/13/10)',
            self::PROFILE_COTTON_HEAVY => 'Больше хлопка (45/18/10/10/17)',
            self::PROFILE_ROUND => 'Круглые доли (50/20/12/10/8)',
        ];
    }

    public static function pickGatheringCodeForUser(int $userId, string $profile = self::DEFAULT_PROFILE): string
    {
        return self::pickWeightedCodeForUser($userId, self::gatheringWeights($profile), 'woodcutter');
    }

    /**
     * @return array<string, int>
     */
    public static function processingWeights(): array
    {
        return [
            'carpenter' => 20,
            'stonemason' => 20,
            'smelter' => 20,
            'glassblower' => 20,
            'weaver' => 20,
        ];
    }

    public static function pickProcessingCodeForUser(int $userId): string
    {
        return self::pickWeightedCodeForUser($userId, self::processingWeights(), 'carpenter');
    }

    /**
     * @param array<string, int> $weights
     */
    private static function pickWeightedCodeForUser(int $userId, array $weights, string $fallback): string
    {
        $roll = abs($userId) % 100;
        $cursor = 0;

        foreach ($weights as $code => $weight) {
            $cursor += $weight;
            if ($roll < $cursor) {
                return $code;
            }
        }

        return $fallback;
    }

    /**
     * Спрос по цепочкам добычи из всех рецептов EstateRecipesConfig (без хлопка).
     *
     * @return array<string, int>
     */
    public static function estateRecipeGatherDemand(): array
    {
        $totals = [
            'woodcutter' => 0,
            'quarryman' => 0,
            'miner' => 0,
            'sandgatherer' => 0,
            'cottongatherer' => 0,
        ];

        foreach (EstateRecipesConfig::all() as $recipe) {
            $materials = (array)($recipe['materials'] ?? []);
            foreach ($materials as $code => $qty) {
                $qty = (int)$qty;
                if ($qty <= 0) {
                    continue;
                }

                if ($code === 'log' || $code === 'plank') {
                    $totals['woodcutter'] += $qty;
                } elseif ($code === 'stone_block' || $code === 'stone' || $code === 'block') {
                    $totals['quarryman'] += $qty;
                } elseif ($code === 'ingot') {
                    $totals['miner'] += $qty;
                } elseif ($code === 'window_glass' || $code === 'glass') {
                    $totals['sandgatherer'] += $qty;
                }
            }
        }

        return $totals;
    }

    /**
     * @return array<string, float>
     */
    public static function estateRecipeGatherDemandPercents(): array
    {
        $demand = self::estateRecipeGatherDemand();
        unset($demand['cottongatherer']);
        $sum = array_sum($demand);
        if ($sum <= 0) {
            return [];
        }

        $percents = [];
        foreach ($demand as $code => $qty) {
            $percents[$code] = round($qty * 100 / $sum, 1);
        }

        return $percents;
    }
}
