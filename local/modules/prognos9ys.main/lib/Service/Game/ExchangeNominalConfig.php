<?php

namespace Prognos9ys\Main\Service\Game;

/**
 * Стартовые номиналы биржи (🪙) и паллеты. Плавание — в ExchangeNominalService (позже).
 */
class ExchangeNominalConfig
{
    /** @var array<string, float> */
    private const CHEST_NOMINALS = [
        TreasureService::CHEST_TYPE_LEVEL => 50.0,
        TreasureService::CHEST_TYPE_ACHIEVEMENT => 50.0,
        TreasureService::CHEST_TYPE_MATCH => 60.0,
        TreasureService::CHEST_TYPE_WC26_ACHIEVEMENT => 60.0,
        TreasureService::CHEST_TYPE_SHOP_WC26 => 60.0,
        ExchangeConfig::CHEST_CODE_WC26 => 60.0,
    ];

    /** @var array<int, float> */
    private const PREMIUM_SCROLL_NOMINALS = [
        1 => 5.0,
        3 => 15.0,
        5 => 25.0,
    ];

    /** @var array<string, float> */
    private const PACK_NOMINALS = [
        'pack_pennant' => 15.0,
        'pack_pennant_wc26' => 15.0,
        'pack_scarf' => 25.0,
        'pack_scarf_wc26' => 25.0,
        'pack_field_action' => 30.0,
        'pack_field_action_wc26' => 30.0,
        'pack_coach_action' => 35.0,
        'pack_coach_action_wc26' => 35.0,
        'pack_player' => 40.0,
        'pack_player_wc26' => 40.0,
        'pack_team' => 45.0,
        'pack_team_wc26' => 45.0,
        'pack_actions' => 45.0,
        'pack_actions_wc26' => 45.0,
        'pack_formation' => 50.0,
        'pack_formation_wc26' => 50.0,
        'pack_coach' => 60.0,
        'pack_coach_wc26' => 60.0,
    ];

    /** @var array<string, float> */
    private const PENNANT_NOMINALS = [
        'site' => 15.0,
        'chm2026' => 25.0,
    ];

    /** @var array<string, float> */
    private const CERT_NOMINALS = [
        'cert_profession' => 150.0,
        'cert_estate' => 200.0,
    ];

    public static function getChestNominal(string $chestType): float
    {
        return (float)(self::CHEST_NOMINALS[$chestType] ?? 50.0);
    }

    public static function getPremiumScrollNominal(int $days): float
    {
        return (float)(self::PREMIUM_SCROLL_NOMINALS[$days] ?? 30.0);
    }

    public static function getPackNominal(string $itemCode): float
    {
        return (float)(self::PACK_NOMINALS[$itemCode] ?? 15.0);
    }

    public static function getPennantNominal(string $pennantCode): float
    {
        return (float)(self::PENNANT_NOMINALS[$pennantCode] ?? ExchangeConfig::OPEN_PENNANT_BASE);
    }

    public static function getLootNominal(string $itemCode, string $category, ?string $teamCode = null): float
    {
        if ($category === ChestLootConfig::CATEGORY_CERT) {
            return (float)(self::CERT_NOMINALS[$itemCode] ?? 100.0);
        }

        if ($category === ChestLootConfig::CATEGORY_PACK) {
            return self::getPackNominal($itemCode);
        }

        if ($category === ChestLootConfig::CATEGORY_XP_BANK) {
            return self::getXpBankNominal($itemCode);
        }

        if ($category === ChestLootConfig::CATEGORY_PENNANT) {
            $teamSlug = Wc26CollectibleConfig::extractTeamSlugFromCollectibleCode($itemCode);

            return $teamSlug !== null
                ? self::getOpenTeamItemNominal(ExchangeConfig::OPEN_PENNANT_BASE, $teamSlug)
                : ExchangeConfig::OPEN_PENNANT_BASE;
        }

        if ($category === ChestLootConfig::CATEGORY_SCARF) {
            $teamSlug = Wc26CollectibleConfig::extractTeamSlugFromCollectibleCode($itemCode);

            return $teamSlug !== null
                ? self::getOpenTeamItemNominal(ExchangeConfig::OPEN_SCARF_BASE, $teamSlug)
                : ExchangeConfig::OPEN_SCARF_BASE;
        }

        if ($category === ChestLootConfig::CATEGORY_ALBUM) {
            return AlbumConfig::ALBUM_NOMINAL;
        }

        if ($category === ChestLootConfig::CATEGORY_RECIPE) {
            return ProfessionRecipeConfig::getRecipeNominal($itemCode);
        }

        if ($teamCode !== null && $teamCode !== '') {
            if (self::isScarfCode($itemCode)) {
                return self::getOpenTeamItemNominal(ExchangeConfig::OPEN_SCARF_BASE, $teamCode);
            }
            if (self::isPennantCode($itemCode)) {
                return self::getOpenTeamItemNominal(ExchangeConfig::OPEN_PENNANT_BASE, $teamCode);
            }
        }

        return 10.0;
    }

    public static function getXpBankNominal(string $itemCode): float
    {
        if (preg_match('/_50$/', $itemCode)) {
            return 25.0;
        }

        if (preg_match('/_25$/', $itemCode)) {
            return 12.5;
        }

        return 12.5;
    }

    public static function getOpenTeamItemNominal(float $base, string $teamCode): float
    {
        $percentile = self::resolveTeamPercentile($teamCode);

        return round(
            $base * self::teamMultiplier($percentile),
            1
        );
    }

    public static function getMaxSellerPrice(float $nominal): float
    {
        return round($nominal * ExchangeConfig::SELLER_PRICE_CAP_MULTIPLIER, 1);
    }

    public static function getMaterialNominal(string $materialCode): float
    {
        $catalog = ProfessionMaterialConfig::materialCatalog();

        return (float)($catalog[$materialCode]['nominal'] ?? ProfessionEconomyConfig::NOMINAL_RAW);
    }

    public static function getPalletLimit(string $kind, string $itemCode = '', string $category = ''): int
    {
        if ($kind === ExchangeConfig::KIND_CHEST || $kind === ExchangeConfig::KIND_PENNANT) {
            return ExchangeConfig::PALLET_LIMITS[$kind];
        }

        if ($kind === ExchangeConfig::KIND_PREMIUM_SCROLL) {
            return ExchangeConfig::PALLET_LIMITS[$kind];
        }

        if ($kind === ExchangeConfig::KIND_LOOT) {
            if ($category === ChestLootConfig::CATEGORY_XP_BANK) {
                return ExchangeConfig::PALLET_LIMIT_XP_BANK;
            }
            if ($category === ChestLootConfig::CATEGORY_CERT) {
                return ExchangeConfig::PALLET_LIMIT_CERT;
            }

            return ExchangeConfig::PALLET_LIMITS[$kind];
        }

        if ($kind === ExchangeConfig::KIND_MATERIAL) {
            return ExchangeConfig::PALLET_LIMITS[$kind];
        }

        return 5;
    }

    /**
     * Стабильный percentile 0..1 по коду команды (пока без рейтинга из iblock).
     */
    public static function resolveTeamPercentile(string $teamCode): float
    {
        $teamCode = strtolower(trim($teamCode));
        if ($teamCode === '') {
            return 0.5;
        }

        $unsigned = (int)sprintf('%u', crc32($teamCode));

        return ($unsigned % 1000) / 1000.0;
    }

    public static function teamMultiplier(float $percentile): float
    {
        $percentile = max(0.0, min(1.0, $percentile));
        $range = ExchangeConfig::TEAM_K_MAX - ExchangeConfig::TEAM_K_MIN;

        return ExchangeConfig::TEAM_K_MIN + $range * $percentile;
    }

    private static function isScarfCode(string $itemCode): bool
    {
        return strpos($itemCode, 'scarf') !== false;
    }

    private static function isPennantCode(string $itemCode): bool
    {
        return strpos($itemCode, 'pennant') !== false;
    }
}
