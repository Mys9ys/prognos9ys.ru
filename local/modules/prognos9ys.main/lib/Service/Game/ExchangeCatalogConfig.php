<?php

namespace Prognos9ys\Main\Service\Game;

/**
 * Вкладки каталога биржи и категории приёма комиссионки банка.
 */
class ExchangeCatalogConfig
{
    public const TAB_ALL = 'all';
    public const TAB_CHEST = 'chest';
    public const TAB_PREMIUM_SCROLL = 'premium_scroll';
    /** @deprecated ID сохранён для флагов комиссионки; в UI — «ККИ» (паки) */
    public const TAB_LOOT = 'loot';
    public const TAB_SOUVENIR = 'souvenir';
    public const TAB_XP_BANK = 'xp_bank';
    public const TAB_CERT = 'cert';
    /** @deprecated вкладка объединена с TAB_SOUVENIR */
    public const TAB_PENNANT = 'pennant';
    public const TAB_MATERIAL = 'material';
    public const TAB_RUBLIUS = 'rublius';
    public const TAB_RECIPE = 'recipe';

    /**
     * @return array<int, array{id: string, label: string}>
     */
    public static function getTabs(): array
    {
        return [
            ['id' => self::TAB_ALL, 'label' => 'Все'],
            ['id' => self::TAB_CHEST, 'label' => 'Сундуки'],
            ['id' => self::TAB_PREMIUM_SCROLL, 'label' => 'Премиум'],
            ['id' => self::TAB_LOOT, 'label' => 'ККИ'],
            ['id' => self::TAB_SOUVENIR, 'label' => 'Сувениры'],
            ['id' => self::TAB_RECIPE, 'label' => 'Рецепты'],
            ['id' => self::TAB_XP_BANK, 'label' => 'Банки XP'],
            ['id' => self::TAB_CERT, 'label' => 'Лицензии'],
            ['id' => self::TAB_MATERIAL, 'label' => 'Материалы'],
            ['id' => self::TAB_RUBLIUS, 'label' => 'Рублиусы'],
        ];
    }

    /**
     * Категории комиссионки (без «Все»).
     *
     * @return array<int, string>
     */
    public static function consignmentTabIds(): array
    {
        return [
            self::TAB_CHEST,
            self::TAB_PREMIUM_SCROLL,
            self::TAB_LOOT,
            self::TAB_SOUVENIR,
            self::TAB_RECIPE,
            self::TAB_XP_BANK,
            self::TAB_CERT,
            self::TAB_MATERIAL,
            self::TAB_RUBLIUS,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function souvenirPackCodes(): array
    {
        return [
            'pack_pennant',
            'pack_pennant_wc26',
            'pack_scarf',
            'pack_scarf_wc26',
        ];
    }

    public static function isSouvenirPackCode(string $code): bool
    {
        return in_array(strtolower(trim($code)), self::souvenirPackCodes(), true);
    }

    /**
     * SQL-фильтр активных лотов для вкладки «Сувениры».
     * Не тянем весь loot (ККИ, XP, рецепты) — иначе limit 2000 отрезает шарфы до PHP-фильтра.
     *
     * @return array<int|string, mixed>
     */
    public static function buildSouvenirListingFilter(): array
    {
        return [
            'LOGIC' => 'OR',
            ['=UF_ITEM_KIND' => ExchangeConfig::KIND_PENNANT],
            [
                '=UF_ITEM_KIND' => ExchangeConfig::KIND_LOOT,
                '=UF_ITEM_CATEGORY' => ChestLootConfig::CATEGORY_SCARF,
            ],
            [
                '=UF_ITEM_KIND' => ExchangeConfig::KIND_LOOT,
                '=UF_ITEM_CATEGORY' => ChestLootConfig::CATEGORY_PENNANT,
            ],
            [
                '=UF_ITEM_KIND' => ExchangeConfig::KIND_LOOT,
                '=UF_ITEM_CATEGORY' => ChestLootConfig::CATEGORY_PACK,
                '@UF_ITEM_CODE' => self::souvenirPackCodes(),
            ],
        ];
    }

    public static function isSouvenirLootCode(string $code): bool
    {
        $code = strtolower(trim($code));
        if ($code === '' || self::isSouvenirPackCode($code)) {
            return false;
        }

        return strpos($code, 'scarf') !== false || strpos($code, 'pennant') !== false;
    }

    public static function resolveTab(string $kind, string $category = '', string $code = ''): string
    {
        $kind = trim($kind);
        $category = trim($category);
        $code = trim($code);

        if ($kind === ExchangeConfig::KIND_LOOT) {
            if ($category === ChestLootConfig::CATEGORY_XP_BANK) {
                return self::TAB_XP_BANK;
            }
            if ($category === ChestLootConfig::CATEGORY_CERT) {
                return self::TAB_CERT;
            }
            if ($category === ChestLootConfig::CATEGORY_RECIPE) {
                return self::TAB_RECIPE;
            }
            if ($category === ChestLootConfig::CATEGORY_ALBUM) {
                return self::TAB_RECIPE;
            }
            if ($category === ChestLootConfig::CATEGORY_PACK) {
                return self::isSouvenirPackCode($code) ? self::TAB_SOUVENIR : self::TAB_LOOT;
            }
            if (self::isSouvenirLootCode($code)) {
                return self::TAB_SOUVENIR;
            }

            return self::TAB_LOOT;
        }

        if ($kind === ExchangeConfig::KIND_MATERIAL) {
            return self::TAB_MATERIAL;
        }

        if ($kind === ExchangeConfig::KIND_RUBLIUS) {
            return self::TAB_RUBLIUS;
        }

        if ($kind === ExchangeConfig::KIND_PENNANT) {
            return self::TAB_SOUVENIR;
        }

        if (in_array($kind, self::consignmentTabIds(), true)) {
            return $kind;
        }

        return $kind;
    }

    public static function matchesTab(string $tab, string $kind, string $category = '', string $code = ''): bool
    {
        $tab = trim($tab);
        if ($tab === '' || $tab === self::TAB_ALL) {
            return true;
        }

        return self::resolveTab($kind, $category, $code) === $tab;
    }

    /**
     * @return array<string, bool>
     */
    public static function defaultConsignmentFlags(): array
    {
        $flags = [];
        foreach (self::consignmentTabIds() as $tabId) {
            $flags[$tabId] = true;
        }

        return $flags;
    }

    /**
     * @return array<string, bool>
     */
    public static function parseConsignmentFlags(?string $json): array
    {
        $flags = self::defaultConsignmentFlags();
        if ($json === null || trim($json) === '') {
            return $flags;
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return $flags;
        }

        // Миграция: старый единый «loot» → xp_bank + cert + сувениры
        if (array_key_exists(self::TAB_LOOT, $decoded)) {
            if (!array_key_exists(self::TAB_XP_BANK, $decoded)) {
                $decoded[self::TAB_XP_BANK] = $decoded[self::TAB_LOOT];
            }
            if (!array_key_exists(self::TAB_CERT, $decoded)) {
                $decoded[self::TAB_CERT] = $decoded[self::TAB_LOOT];
            }
            if (!array_key_exists(self::TAB_SOUVENIR, $decoded)) {
                $decoded[self::TAB_SOUVENIR] = $decoded[self::TAB_LOOT];
            }
        }

        if (array_key_exists(self::TAB_PENNANT, $decoded) && !array_key_exists(self::TAB_SOUVENIR, $decoded)) {
            $decoded[self::TAB_SOUVENIR] = $decoded[self::TAB_PENNANT];
        }

        foreach ($flags as $tabId => $defaultValue) {
            if (array_key_exists($tabId, $decoded)) {
                $flags[$tabId] = !empty($decoded[$tabId]);
            }
        }

        return $flags;
    }

    /**
     * @param array<string, bool> $flags
     */
    public static function encodeConsignmentFlags(array $flags): string
    {
        $normalized = self::defaultConsignmentFlags();
        foreach ($normalized as $tabId => $defaultValue) {
            if (array_key_exists($tabId, $flags)) {
                $normalized[$tabId] = !empty($flags[$tabId]);
            }
        }

        return json_encode($normalized, JSON_UNESCAPED_UNICODE);
    }
}
