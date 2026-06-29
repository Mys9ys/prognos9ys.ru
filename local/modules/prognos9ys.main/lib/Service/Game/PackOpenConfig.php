<?php

namespace Prognos9ys\Main\Service\Game;

/**
 * Какие паки можно распаковать и что внутри.
 */
class PackOpenConfig
{
    public const REWARD_PENNANT = 'pennant';
    public const REWARD_SCARF = 'scarf';

    /** @var array<string, string> pack_code => reward kind */
    private const SUPPORTED = [
        'pack_pennant_wc26' => self::REWARD_PENNANT,
        'pack_pennant' => self::REWARD_PENNANT,
        'pack_scarf_wc26' => self::REWARD_SCARF,
        'pack_scarf' => self::REWARD_SCARF,
    ];

    public static function isSupported(string $packCode): bool
    {
        return isset(self::SUPPORTED[trim($packCode)]);
    }

    public static function getRewardKind(string $packCode): ?string
    {
        $packCode = trim($packCode);

        return self::SUPPORTED[$packCode] ?? null;
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
}
