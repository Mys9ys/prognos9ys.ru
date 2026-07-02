<?php

namespace Prognos9ys\Main\Service\Game;

class ProfessionRecipeConfig
{
    public const RECIPE_ALBUM = 'recipe_album';
    public const RECIPE_DOOR = 'recipe_door';
    public const RECIPE_WINDOW_SMALL = 'recipe_window_small';
    public const RECIPE_WINDOW_REGULAR = 'recipe_window_regular';
    public const RECIPE_NAILS = 'recipe_nails';
    public const RECIPE_HINGE = 'recipe_hinge';
    public const RECIPE_CLEAN_SCROLL = 'recipe_clean_scroll';
    public const RECIPE_CAFTAN_BASIC = 'recipe_caftan_basic';
    public const RECIPE_CAFTAN_EMBROIDERED = 'recipe_caftan_embroidered';
    public const RECIPE_CAFTAN_GRAND = 'recipe_caftan_grand';

    /**
     * @return array<string, array{code:string,label:string,profession:string,nominal:float}>
     */
    public static function all(): array
    {
        return [
            self::RECIPE_ALBUM => [
                'code' => self::RECIPE_ALBUM,
                'label' => 'Рецепт альбома коллекции',
                'profession' => 'weaver',
                'nominal' => 10.0,
            ],
            self::RECIPE_DOOR => [
                'code' => self::RECIPE_DOOR,
                'label' => 'Рецепт двери',
                'profession' => 'carpenter',
                'nominal' => 40.0,
            ],
            self::RECIPE_WINDOW_SMALL => [
                'code' => self::RECIPE_WINDOW_SMALL,
                'label' => 'Рецепт окна малого',
                'profession' => 'glassblower',
                'nominal' => 20.0,
            ],
            self::RECIPE_WINDOW_REGULAR => [
                'code' => self::RECIPE_WINDOW_REGULAR,
                'label' => 'Рецепт окна обычного',
                'profession' => 'glassblower',
                'nominal' => 30.0,
            ],
            self::RECIPE_NAILS => [
                'code' => self::RECIPE_NAILS,
                'label' => 'Рецепт гвоздей',
                'profession' => 'smelter',
                'nominal' => 15.0,
            ],
            self::RECIPE_HINGE => [
                'code' => self::RECIPE_HINGE,
                'label' => 'Рецепт петель',
                'profession' => 'smelter',
                'nominal' => 20.0,
            ],
            self::RECIPE_CLEAN_SCROLL => [
                'code' => self::RECIPE_CLEAN_SCROLL,
                'label' => 'Рецепт чистого свитка',
                'profession' => 'weaver',
                'nominal' => 5.0,
            ],
            self::RECIPE_CAFTAN_BASIC => [
                'code' => self::RECIPE_CAFTAN_BASIC,
                'label' => 'Рецепт кафтана (обычный)',
                'profession' => 'weaver',
                'nominal' => 35.0,
            ],
            self::RECIPE_CAFTAN_EMBROIDERED => [
                'code' => self::RECIPE_CAFTAN_EMBROIDERED,
                'label' => 'Рецепт кафтана (расшитый)',
                'profession' => 'weaver',
                'nominal' => 55.0,
            ],
            self::RECIPE_CAFTAN_GRAND => [
                'code' => self::RECIPE_CAFTAN_GRAND,
                'label' => 'Рецепт кафтана (великолепный)',
                'profession' => 'weaver',
                'nominal' => 85.0,
            ],
        ];
    }

    public static function isKnownRecipe(string $recipeCode): bool
    {
        return isset(self::all()[trim($recipeCode)]);
    }

    public static function getRecipeLabel(string $recipeCode): string
    {
        $recipeCode = trim($recipeCode);
        if ($recipeCode === '') {
            return '';
        }

        return (string)(self::all()[$recipeCode]['label'] ?? $recipeCode);
    }

    public static function getRecipeNominal(string $recipeCode): float
    {
        $recipeCode = trim($recipeCode);
        if ($recipeCode === '') {
            return 10.0;
        }

        return (float)(self::all()[$recipeCode]['nominal'] ?? 10.0);
    }

    /**
     * @return array<int, array{code:string,weight:int,kind:string,category:string,label:string}>
     */
    public static function professionChestRecipeDrops(): array
    {
        return [
            ['code' => self::RECIPE_CLEAN_SCROLL, 'weight' => 220, 'kind' => 'item', 'category' => ChestLootConfig::CATEGORY_RECIPE, 'label' => self::getRecipeLabel(self::RECIPE_CLEAN_SCROLL)],
            ['code' => self::RECIPE_NAILS, 'weight' => 200, 'kind' => 'item', 'category' => ChestLootConfig::CATEGORY_RECIPE, 'label' => self::getRecipeLabel(self::RECIPE_NAILS)],
            ['code' => self::RECIPE_HINGE, 'weight' => 180, 'kind' => 'item', 'category' => ChestLootConfig::CATEGORY_RECIPE, 'label' => self::getRecipeLabel(self::RECIPE_HINGE)],
            ['code' => self::RECIPE_WINDOW_SMALL, 'weight' => 160, 'kind' => 'item', 'category' => ChestLootConfig::CATEGORY_RECIPE, 'label' => self::getRecipeLabel(self::RECIPE_WINDOW_SMALL)],
            ['code' => self::RECIPE_WINDOW_REGULAR, 'weight' => 120, 'kind' => 'item', 'category' => ChestLootConfig::CATEGORY_RECIPE, 'label' => self::getRecipeLabel(self::RECIPE_WINDOW_REGULAR)],
            ['code' => self::RECIPE_DOOR, 'weight' => 100, 'kind' => 'item', 'category' => ChestLootConfig::CATEGORY_RECIPE, 'label' => self::getRecipeLabel(self::RECIPE_DOOR)],
            ['code' => self::RECIPE_ALBUM, 'weight' => 60, 'kind' => 'item', 'category' => ChestLootConfig::CATEGORY_RECIPE, 'label' => self::getRecipeLabel(self::RECIPE_ALBUM)],
        ];
    }
}
