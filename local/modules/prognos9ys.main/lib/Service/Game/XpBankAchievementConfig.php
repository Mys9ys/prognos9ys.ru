<?php

namespace Prognos9ys\Main\Service\Game;

/**
 * Ачивки за выпитые банки XP: игрок, добыча и крафт по специализациям.
 */
class XpBankAchievementConfig
{
    public const GROUP = 'potion';

    /** @var int[] */
    public const THRESHOLDS = [10, 25, 50];

    /** @var int[] */
    public const CHEST_REWARDS = [1, 2, 5];

    public static function statKeyPlayer25(): string
    {
        return 'xp_bank_drink_player_25';
    }

    public static function statKeyPlayer50(): string
    {
        return 'xp_bank_drink_player_50';
    }

    public static function statKeyMining(string $professionCode): string
    {
        return 'xp_bank_drink_mining_' . trim($professionCode);
    }

    public static function statKeyCrafting(string $professionCode): string
    {
        return 'xp_bank_drink_crafting_' . trim($professionCode);
    }

    public static function resolveStatKey(string $itemCode, string $kind, string $professionCode): string
    {
        $itemCode = trim($itemCode);
        $kind = trim($kind);
        $professionCode = trim($professionCode);

        if ($itemCode === 'xp_bank_player_25') {
            return self::statKeyPlayer25();
        }

        if ($itemCode === 'xp_bank_player_50') {
            return self::statKeyPlayer50();
        }

        if ($kind === 'mining' && $professionCode !== '') {
            return self::statKeyMining($professionCode);
        }

        if ($kind === 'crafting' && $professionCode !== '') {
            return self::statKeyCrafting($professionCode);
        }

        return '';
    }

    /**
     * @return array<string, int>
     */
    public static function emptyStatsTemplate(): array
    {
        $stats = [
            self::statKeyPlayer25() => 0,
            self::statKeyPlayer50() => 0,
        ];

        foreach (ProfessionMaterialConfig::gatheringProfessions() as $code => $profession) {
            $stats[self::statKeyMining($code)] = 0;
        }

        foreach (ProfessionMaterialConfig::processingProfessions() as $code => $profession) {
            $stats[self::statKeyCrafting($code)] = 0;
        }

        return $stats;
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
        $entries = [
            'potion_player_25' => self::buildEntry(
                'Банка XP игрока (25)',
                'Выпито банок XP игрока (25)',
                self::statKeyPlayer25()
            ),
            'potion_player_50' => self::buildEntry(
                'Банка XP игрока (50)',
                'Выпито банок XP игрока (50)',
                self::statKeyPlayer50()
            ),
        ];

        foreach (ProfessionMaterialConfig::gatheringProfessions() as $code => $profession) {
            $entries['potion_mining_' . $code] = self::buildEntry(
                $profession['label'] . ': банки добычи',
                'Выпито банок XP добычи для профессии «' . $profession['label'] . '»',
                self::statKeyMining($code)
            );
        }

        foreach (ProfessionMaterialConfig::processingProfessions() as $code => $profession) {
            $entries['potion_crafting_' . $code] = self::buildEntry(
                $profession['label'] . ': банки крафта',
                'Выпито банок XP крафта для профессии «' . $profession['label'] . '»',
                self::statKeyCrafting($code)
            );
        }

        return $entries;
    }

    /**
     * @return array{
     *   title:string,
     *   description:string,
     *   group:string,
     *   icon:string,
     *   stat:string,
     *   levels: array<int, array{threshold:int,reward:array}>
     * }
     */
    private static function buildEntry(string $title, string $description, string $stat): array
    {
        $levels = [];
        foreach (self::THRESHOLDS as $index => $threshold) {
            $levels[] = [
                'threshold' => $threshold,
                'reward' => [
                    'chests' => self::CHEST_REWARDS[$index] ?? 1,
                    'chest_type' => 'achievement',
                ],
            ];
        }

        return [
            'title' => $title,
            'description' => $description,
            'group' => self::GROUP,
            'icon' => 'total_all',
            'stat' => $stat,
            'levels' => $levels,
        ];
    }
}
