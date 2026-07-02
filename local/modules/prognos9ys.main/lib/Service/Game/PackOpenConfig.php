<?php

namespace Prognos9ys\Main\Service\Game;

/**
 * Какие паки можно распаковать и что внутри.
 */
class PackOpenConfig
{
    public const REWARD_PENNANT = 'pennant';
    public const REWARD_SCARF = 'scarf';
    public const REWARD_RECIPE_BASIC = 'recipe_basic';
    public const REWARD_RECIPE_ADVANCED = 'recipe_advanced';
    public const REWARD_EQUIPMENT_WORK = 'equipment_work';

    /** @var array<string, string> pack_code => reward kind */
    private const OPENABLE = [
        'pack_pennant_wc26' => self::REWARD_PENNANT,
        'pack_scarf_wc26' => self::REWARD_SCARF,
        ProfessionRecipeConfig::PACK_RECIPE_BASIC => self::REWARD_RECIPE_BASIC,
        ProfessionRecipeConfig::PACK_RECIPE_ADVANCED => self::REWARD_RECIPE_ADVANCED,
        ProfessionRecipeConfig::PACK_EQUIPMENT_WORK => self::REWARD_EQUIPMENT_WORK,
    ];

    /** Generic-паки: пока только заглушка, без распаковки. */
    private const STUB = [
        'pack_pennant' => self::REWARD_PENNANT,
        'pack_scarf' => self::REWARD_SCARF,
    ];

    public static function isFullyOpenable(string $packCode): bool
    {
        return isset(self::OPENABLE[trim($packCode)]);
    }

    public static function isStubPack(string $packCode): bool
    {
        return isset(self::STUB[trim($packCode)]);
    }

    public static function isSupported(string $packCode): bool
    {
        return self::isFullyOpenable($packCode) || self::isStubPack($packCode);
    }

    public static function getStubMessage(string $packCode = ''): string
    {
        return 'Распаковка паков этой коллекции пока в разработке. Сейчас доступны только паки ЧМ-26.';
    }

    public static function getRewardKind(string $packCode): ?string
    {
        $packCode = trim($packCode);

        return self::OPENABLE[$packCode] ?? self::STUB[$packCode] ?? null;
    }

    /**
     * @return array{code:string,category:string,label:string,team_slug:string}
     */
    public static function rollReward(string $packCode): array
    {
        $kind = self::getRewardKind($packCode);
        if ($kind === null) {
            throw new \InvalidArgumentException('Этот пак пока нельзя распаковать');
        }

        $slug = Wc26CollectibleConfig::rollTeamSlug();

        if ($kind === self::REWARD_PENNANT) {
            $code = Wc26CollectibleConfig::pennantCode($slug);

            return [
                'code' => $code,
                'category' => ChestLootConfig::CATEGORY_PENNANT,
                'label' => Wc26CollectibleConfig::getPennantLabel($code),
                'team_slug' => $slug,
            ];
        }

        if ($kind === self::REWARD_RECIPE_BASIC) {
            $reward = ChestLootConfig::rollFromTable(ProfessionRecipeConfig::recipeBasicPackDrops());
            if (!is_array($reward)) {
                throw new \RuntimeException('В базовом паке рецептов не настроены награды');
            }

            return [
                'code' => (string)$reward['code'],
                'category' => (string)$reward['category'],
                'label' => (string)$reward['label'],
                'team_slug' => '',
            ];
        }

        if ($kind === self::REWARD_RECIPE_ADVANCED) {
            $reward = ChestLootConfig::rollFromTable(ProfessionRecipeConfig::recipeAdvancedPackDrops());
            if (!is_array($reward)) {
                throw new \RuntimeException('В продвинутом паке рецептов не настроены награды');
            }

            return [
                'code' => (string)$reward['code'],
                'category' => (string)$reward['category'],
                'label' => (string)$reward['label'],
                'team_slug' => '',
            ];
        }

        if ($kind === self::REWARD_EQUIPMENT_WORK) {
            $reward = ChestLootConfig::rollFromTable(ProfessionRecipeConfig::equipmentWorkPackDrops());
            if (!is_array($reward)) {
                throw new \RuntimeException('В паке рабочей экипировки не настроены награды');
            }

            return [
                'code' => (string)$reward['code'],
                'category' => (string)$reward['category'],
                'label' => (string)$reward['label'],
                'team_slug' => '',
            ];
        }

        $code = Wc26CollectibleConfig::scarfCode($slug);

        return [
            'code' => $code,
            'category' => ChestLootConfig::CATEGORY_SCARF,
            'label' => Wc26CollectibleConfig::getScarfLabel($code),
            'team_slug' => $slug,
        ];
    }

    public static function usesAnchorEvent(string $packCode): bool
    {
        return strpos($packCode, '_wc26') !== false;
    }

    public static function isSouvenirPack(string $packCode): bool
    {
        return self::isFullyOpenable($packCode);
    }

    /**
     * Бонусный «второй блок» при распаковке сувенирных паков.
     *
     * @return array{code:string,category:string,label:string,team_slug:string}|null
     */
    public static function rollAlbumRecipeBonus(): ?array
    {
        if (random_int(1, 100) > AlbumConfig::RECIPE_DROP_CHANCE_PERCENT) {
            return null;
        }

        return [
            'code' => AlbumConfig::RECIPE_ITEM_CODE,
            'category' => ChestLootConfig::CATEGORY_RECIPE,
            'label' => AlbumConfig::recipeLabel(),
            'team_slug' => '',
        ];
    }
}
