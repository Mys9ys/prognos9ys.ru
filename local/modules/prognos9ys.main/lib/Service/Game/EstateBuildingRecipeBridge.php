<?php

namespace Prognos9ys\Main\Service\Game;

/**
 * Связь компонентов построек (EstateRecipesConfig) с рецептами крафта (ProfessionRecipeConfig).
 */
class EstateBuildingRecipeBridge
{
    /**
     * @return array{
     *   recipe_code:?string,
     *   recipe_label:?string,
     *   profession:?string,
     *   profession_label:?string
     * }
     */
    public static function resolveComponentCraft(string $componentCode): array
    {
        $componentCode = trim($componentCode);
        if ($componentCode === '') {
            return self::emptyRow();
        }

        $recipeCode = ProfessionRecipeConfig::findRecipeCodeByOutputCode($componentCode);
        if ($recipeCode === null) {
            return self::emptyRow();
        }

        $profession = ProfessionRecipeConfig::getRecipeProfession($recipeCode);

        return [
            'recipe_code' => $recipeCode,
            'recipe_label' => ProfessionRecipeConfig::getRecipeLabel($recipeCode),
            'profession' => $profession !== '' ? $profession : null,
            'profession_label' => $profession !== ''
                ? (string)(ProfessionMaterialConfig::getProfession($profession)['label'] ?? $profession)
                : null,
        ];
    }

    /**
     * @param array<string, int> $components
     * @return array<int, array{
     *   code:string,
     *   label:string,
     *   qty:int,
     *   recipe_code:?string,
     *   recipe_label:?string,
     *   profession:?string,
     *   profession_label:?string
     * }>
     */
    public static function formatComponentList(array $components): array
    {
        $rows = [];
        foreach ($components as $code => $qty) {
            $qty = (int)$qty;
            if ($qty <= 0) {
                continue;
            }
            $code = (string)$code;
            $craft = self::resolveComponentCraft($code);
            $rows[] = array_merge([
                'code' => $code,
                'label' => ProfessionCraftedItemConfig::getLabel($code),
                'qty' => $qty,
                'order_pay_per_unit' => EstateRecipesConfig::calcComponentDonationUnitPayout($code),
            ], $craft);
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $buildingRow
     * @return array<string, mixed>
     */
    public static function enrichBuildingRow(array $buildingRow): array
    {
        $needed = (array)($buildingRow['needed'] ?? []);
        $remaining = (array)($buildingRow['remaining'] ?? []);

        $buildingRow['needed_items'] = self::formatComponentList($needed);
        $buildingRow['remaining_items'] = self::formatComponentList($remaining);

        return $buildingRow;
    }

    /**
     * @return array{recipe_code:?string,recipe_label:?string,profession:?string,profession_label:?string}
     */
    private static function emptyRow(): array
    {
        return [
            'recipe_code' => null,
            'recipe_label' => null,
            'profession' => null,
            'profession_label' => null,
        ];
    }
}
