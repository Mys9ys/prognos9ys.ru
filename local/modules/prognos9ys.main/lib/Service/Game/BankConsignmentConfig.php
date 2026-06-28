<?php

namespace Prognos9ys\Main\Service\Game;

class BankConsignmentConfig
{
    /** Доля цены, которую игрок получает мгновенно при сдаче (остальное — маржа банка). */
    public const INSTANT_PAYOUT_PERCENT = 80.0;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_SOLD = 'sold';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * @return array<string, bool>
     */
    public static function defaultCategoryFlags(): array
    {
        return ExchangeCatalogConfig::defaultConsignmentFlags();
    }

    /**
     * @return array<string, bool>
     */
    public static function parseCategoryFlags(?string $json): array
    {
        return ExchangeCatalogConfig::parseConsignmentFlags($json);
    }

    /**
     * @param array<string, mixed> $bank
     */
    public static function isConsignmentEnabled(array $bank): bool
    {
        $flag = $bank['UF_CONSIGNMENT_ENABLED'] ?? null;
        if ($flag === 'N' || $flag === 0 || $flag === '0' || $flag === false) {
            return false;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $bank
     */
    public static function isCategoryAccepted(array $bank, string $kind, string $category = ''): bool
    {
        if (!self::isConsignmentEnabled($bank)) {
            return false;
        }

        $tab = ExchangeCatalogConfig::resolveTab($kind, $category);
        $flags = self::parseCategoryFlags((string)($bank['UF_CONSIGNMENT_CATEGORIES'] ?? ''));

        return !empty($flags[$tab]);
    }

    /**
     * @param array<string, bool> $flags
     */
    public static function encodeCategoryFlags(array $flags): string
    {
        return ExchangeCatalogConfig::encodeConsignmentFlags($flags);
    }
}
