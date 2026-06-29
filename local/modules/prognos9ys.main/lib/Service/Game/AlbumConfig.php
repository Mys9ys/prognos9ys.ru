<?php

namespace Prognos9ys\Main\Service\Game;

/**
 * Универсальный альбом коллекции: крафт, вклейка вымпелов/шарфов ЧМ-26.
 */
class AlbumConfig
{
    public const ITEM_CODE = 'album_universal';
    public const RECIPE_ITEM_CODE = 'recipe_album';
    public const CATEGORY = 'album';

    public const RECIPE_DROP_CHANCE_PERCENT = 5;
    public const RECIPE_NOMINAL = 10.0;

    public const COLLECTION_UNIVERSAL = '';
    public const COLLECTION_PENNANT_WC26 = 'pennant_wc26';
    public const COLLECTION_SCARF_WC26 = 'scarf_wc26';

    public const RECIPE_PLANK = 2;
    public const RECIPE_CLOTH = 7;
    public const CRAFT_OUTPUT_COUNT = 2;
    public const CRAFT_XP_GAIN = 10;
    public const SLOT_COUNT = 48;

    /** @var string[] */
    public const CRAFT_PROFESSION_CODES = ['carpenter', 'weaver'];

    /** @var int[] */
    public const MEGA_THRESHOLDS = [16, 32, 48];

    public static function itemLabel(): string
    {
        return 'Универсальный альбом';
    }

    public static function recipeLabel(): string
    {
        return 'Рецепт альбома коллекции';
    }

    public static function collectionLabel(string $collection): string
    {
        if ($collection === self::COLLECTION_PENNANT_WC26) {
            return 'Вымпелы ЧМ-26';
        }
        if ($collection === self::COLLECTION_SCARF_WC26) {
            return 'Шарфы ЧМ-26';
        }

        return 'Универсальный (выберите первую вклейку)';
    }

    public static function collectionForItemCode(string $itemCode): ?string
    {
        if (Wc26CollectibleConfig::parsePennantSlug($itemCode) !== null) {
            return self::COLLECTION_PENNANT_WC26;
        }
        if (Wc26CollectibleConfig::parseScarfSlug($itemCode) !== null) {
            return self::COLLECTION_SCARF_WC26;
        }

        return null;
    }

    public static function isSupportedCollectible(string $itemCode): bool
    {
        return self::collectionForItemCode($itemCode) !== null;
    }
}
