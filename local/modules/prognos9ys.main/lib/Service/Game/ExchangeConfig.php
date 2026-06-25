<?php

namespace Prognos9ys\Main\Service\Game;

class ExchangeConfig
{
    public const CURRENCY = GameEconomyConfig::CURRENCY_PROGNOBAKS;

    public const COMMISSION_PERCENT = 20.0;
    public const SELLER_PRICE_CAP_MULTIPLIER = 1.03;

    public const LISTING_DAYS_DEFAULT = 3;
    public const LISTING_DAYS_PREMIUM = 7;

    public const MAX_LISTINGS_DEFAULT = 10;
    public const MAX_LISTINGS_PREMIUM = 20;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_FILLED = 'filled';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_MOD_REMOVED = 'mod_removed';

    public const KIND_CHEST = 'chest';
    public const KIND_PREMIUM_SCROLL = 'premium_scroll';
    public const KIND_LOOT = 'loot';
    public const KIND_PENNANT = 'pennant';

    public const ESCROW_REF_TYPE = 'exchange_listing';

    /** @var array<string, int> */
    public const PALLET_LIMITS = [
        self::KIND_CHEST => 5,
        self::KIND_PENNANT => 5,
        self::KIND_PREMIUM_SCROLL => 10,
        self::KIND_LOOT => 20,
    ];

    public const PALLET_LIMIT_XP_BANK = 10;
    public const PALLET_LIMIT_CERT = 5;

    public const OPEN_PENNANT_BASE = 20.0;
    public const OPEN_SCARF_BASE = 30.0;

    /** Нижняя / верхняя граница коэффициента команды для открытых вымпелов и шарфов. */
    public const TEAM_K_MIN = 0.85;
    public const TEAM_K_MAX = 1.15;
}
