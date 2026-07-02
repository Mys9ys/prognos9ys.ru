<?php

namespace Prognos9ys\Main\Service\Game;

/**
 * Ачивки профессий: обычный и премиум ресурс, этапы 1 и 2.
 */
class ProfessionAchievementConfig
{
    public const GROUP = 'profession';

    /** @var int[] */
    public const NORMAL_THRESHOLDS_STAGE1 = [50, 100, 250, 500, 1000];

    /** @var int[] */
    public const NORMAL_THRESHOLDS_STAGE2 = [2500, 5000, 10000, 25000, 50000];

    /** @var int[] */
    public const PREMIUM_THRESHOLDS_STAGE1 = [1, 5, 20, 50, 150];

    /** @var int[] */
    public const PREMIUM_THRESHOLDS_STAGE2 = [300, 500, 750, 1000, 1500];

    /** @var int[] */
    public const NORMAL_RESOURCE_REWARDS = [2, 4, 6, 8, 10];

    /** @var int[] */
    public const PREMIUM_RESOURCE_REWARDS_STAGE1 = [1, 2, 3, 5, 8];

    /** @var int[] */
    public const PREMIUM_RESOURCE_REWARDS_STAGE2 = [1, 2, 3, 4, 5];

    /** @var float[] */
    public const NORMAL_RUBLIUS_STAGE1 = [1, 2, 4, 6, 10];

    /** @var float[] */
    public const NORMAL_RUBLIUS_STAGE2 = [12, 15, 18, 22, 30];

    /** @var float[] */
    public const PREMIUM_RUBLIUS_STAGE1 = [3, 5, 10, 18, 30];

    /** @var float[] */
    public const PREMIUM_RUBLIUS_STAGE2 = [12, 15, 18, 22, 30];

    /** @var int[] */
    public const NORMAL_CHESTS_STAGE1 = [0, 0, 1, 1, 2];

    /** @var int[] */
    public const NORMAL_CHESTS_STAGE2 = [1, 1, 2, 2, 3];

    /** @var int[] */
    public const PREMIUM_CHESTS_STAGE1 = [0, 0, 1, 2, 3];

    /** @var int[] */
    public const PREMIUM_CHESTS_STAGE2 = [1, 1, 2, 2, 3];

    public static function statKeyNormal(string $professionCode): string
    {
        return 'prof_yield_' . $professionCode . '_normal';
    }

    public static function statKeyPremium(string $professionCode): string
    {
        return 'prof_yield_' . $professionCode . '_premium';
    }

    /**
     * @return array<string, array{
     *   title:string,
     *   description:string,
     *   group:string,
     *   icon:string,
     *   stat:string,
     *   profession_code:string,
     *   levels: array<int, array{threshold:int,reward:array}>
     * }>
     */
    public static function getCatalogEntries(): array
    {
        $entries = [];

        foreach (ProfessionMaterialConfig::allProfessions() as $code => $profession) {
            $entries = array_merge(
                $entries,
                self::buildNormalAchievement($code, $profession, 1),
                self::buildNormalAchievement($code, $profession, 2),
                self::buildPremiumAchievement($code, $profession, 1),
                self::buildPremiumAchievement($code, $profession, 2)
            );
        }

        return $entries;
    }

    /**
     * @param array<string, mixed> $profession
     * @return array<string, array>
     */
    private static function buildNormalAchievement(string $code, array $profession, int $stage): array
    {
        $achCode = $stage === 1 ? 'prof_' . $code . '_gather' : 'prof_' . $code . '_gather_2';
        $thresholds = $stage === 1 ? self::NORMAL_THRESHOLDS_STAGE1 : self::NORMAL_THRESHOLDS_STAGE2;
        $rublius = $stage === 1 ? self::NORMAL_RUBLIUS_STAGE1 : self::NORMAL_RUBLIUS_STAGE2;
        $chests = $stage === 1 ? self::NORMAL_CHESTS_STAGE1 : self::NORMAL_CHESTS_STAGE2;
        $suffix = $stage === 1 ? '' : ' (этап 2)';

        $levels = [];
        foreach ($thresholds as $index => $threshold) {
            $reward = [
                'rublius' => $rublius[$index],
                'chests' => $chests[$index],
                'chest_type' => self::resolveProfessionChestTypeForStage($stage, $index),
                'materials' => [[
                    'code' => (string)$profession['output'],
                    'qty' => self::NORMAL_RESOURCE_REWARDS[$index],
                    'is_premium' => false,
                ]],
            ];

            if ($stage === 1 && $index === 4) {
                $reward['pennant'] = 'prof_' . $code;
            }

            $levels[] = [
                'threshold' => $threshold,
                'reward' => $reward,
            ];
        }

        return [
            $achCode => [
                'title' => $profession['label'] . ': ' . $profession['output_label'] . $suffix,
                'description' => 'Добыто/изготовлено ' . $profession['output_label'] . $suffix,
                'group' => self::GROUP,
                'icon' => 'total_all',
                'stat' => self::statKeyNormal($code),
                'profession_code' => $code,
                'profession_stage' => $stage,
                'levels' => $levels,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $profession
     * @return array<string, array>
     */
    private static function buildPremiumAchievement(string $code, array $profession, int $stage): array
    {
        $achCode = $stage === 1 ? 'prof_' . $code . '_premium' : 'prof_' . $code . '_premium_2';
        $thresholds = $stage === 1 ? self::PREMIUM_THRESHOLDS_STAGE1 : self::PREMIUM_THRESHOLDS_STAGE2;
        $rublius = $stage === 1 ? self::PREMIUM_RUBLIUS_STAGE1 : self::PREMIUM_RUBLIUS_STAGE2;
        $chests = $stage === 1 ? self::PREMIUM_CHESTS_STAGE1 : self::PREMIUM_CHESTS_STAGE2;
        $resourceRewards = $stage === 1
            ? self::PREMIUM_RESOURCE_REWARDS_STAGE1
            : self::PREMIUM_RESOURCE_REWARDS_STAGE2;
        $suffix = $stage === 1 ? '' : ' (этап 2)';

        $levels = [];
        foreach ($thresholds as $index => $threshold) {
            $levels[] = [
                'threshold' => $threshold,
                'reward' => [
                    'rublius' => $rublius[$index],
                    'chests' => $chests[$index],
                    'chest_type' => self::resolveProfessionChestTypeForStage($stage, $index),
                    'materials' => [[
                        'code' => (string)$profession['premium'],
                        'qty' => $resourceRewards[$index],
                        'is_premium' => true,
                    ]],
                ],
            ];
        }

        return [
            $achCode => [
                'title' => $profession['label'] . ': ' . $profession['premium_label'] . $suffix,
                'description' => 'Премиум-ресурс ' . $profession['premium_label'] . $suffix,
                'group' => self::GROUP,
                'icon' => 'total_all',
                'stat' => self::statKeyPremium($code),
                'profession_code' => $code,
                'profession_stage' => $stage,
                'levels' => $levels,
            ],
        ];
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
