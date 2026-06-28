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
    public const TAB_PENNANT = 'pennant';
    public const TAB_MATERIAL = 'material';
    public const TAB_LOOT = 'loot';
    public const TAB_XP_BANK = 'xp_bank';
    public const TAB_CERT = 'cert';

    /**
     * @return array<int, array{id: string, label: string}>
     */
    public static function getTabs(): array
    {
        return [
            ['id' => self::TAB_ALL, 'label' => 'Все'],
            ['id' => self::TAB_CHEST, 'label' => 'Сундуки'],
            ['id' => self::TAB_PREMIUM_SCROLL, 'label' => 'Премиум'],
            ['id' => self::TAB_LOOT, 'label' => 'Лут'],
            ['id' => self::TAB_XP_BANK, 'label' => 'Банки XP'],
            ['id' => self::TAB_CERT, 'label' => 'Лицензии'],
            ['id' => self::TAB_PENNANT, 'label' => 'Вымпелы'],
            ['id' => self::TAB_MATERIAL, 'label' => 'Материалы'],
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
            self::TAB_XP_BANK,
            self::TAB_CERT,
            self::TAB_PENNANT,
            self::TAB_MATERIAL,
        ];
    }

    public static function resolveTab(string $kind, string $category = ''): string
    {
        $kind = trim($kind);
        $category = trim($category);

        if ($kind === ExchangeConfig::KIND_LOOT) {
            if ($category === ChestLootConfig::CATEGORY_XP_BANK) {
                return self::TAB_XP_BANK;
            }
            if ($category === ChestLootConfig::CATEGORY_CERT) {
                return self::TAB_CERT;
            }

            return self::TAB_LOOT;
        }

        if (in_array($kind, self::consignmentTabIds(), true)) {
            return $kind;
        }

        return $kind;
    }

    public static function matchesTab(string $tab, string $kind, string $category = ''): bool
    {
        $tab = trim($tab);
        if ($tab === '' || $tab === self::TAB_ALL) {
            return true;
        }

        return self::resolveTab($kind, $category) === $tab;
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

        // Миграция: старый единый «loot» → xp_bank + cert
        if (array_key_exists(self::TAB_LOOT, $decoded)) {
            if (!array_key_exists(self::TAB_XP_BANK, $decoded)) {
                $decoded[self::TAB_XP_BANK] = $decoded[self::TAB_LOOT];
            }
            if (!array_key_exists(self::TAB_CERT, $decoded)) {
                $decoded[self::TAB_CERT] = $decoded[self::TAB_LOOT];
            }
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
