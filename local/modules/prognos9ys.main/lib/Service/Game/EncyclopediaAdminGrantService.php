<?php

namespace Prognos9ys\Main\Service\Game;

use Prognos9ys\Main\Model\Repository\GameEconomyRepository;
use Prognos9ys\Main\Model\Repository\ProfessionRepository;
use Prognos9ys\Main\Service\Auth\ImpersonationConfig;

/**
 * Выдача предметов из энциклопедии — только админам (группа 1), только себе.
 * Типы добавляем по одному: сначала материалы, затем рецепты, экипировка, паки.
 */
class EncyclopediaAdminGrantService
{
    public const SECTION_MATERIALS = 'materials';
    public const SECTION_RECIPES = 'recipes';
    public const SECTION_EQUIPMENT = 'equipment';
    public const SECTION_PACKS = 'packs';

    /** @var string[] */
    public const SUPPORTED_SECTIONS = [
        self::SECTION_MATERIALS,
        self::SECTION_RECIPES,
        self::SECTION_EQUIPMENT,
        self::SECTION_PACKS,
    ];

    private GameEconomyRepository $economyRepository;
    private ProfessionRepository $professionRepository;

    public function __construct(
        ?GameEconomyRepository $economyRepository = null,
        ?ProfessionRepository $professionRepository = null
    ) {
        $this->economyRepository = $economyRepository ?? new GameEconomyRepository();
        $this->professionRepository = $professionRepository ?? new ProfessionRepository();
    }

    public function canGrant(int $userId): bool
    {
        return ImpersonationConfig::isAdminUser($userId);
    }

    /**
     * @return array{
     *   section:string,
     *   code:string,
     *   label:string,
     *   qty:int,
     *   is_premium:bool,
     *   qty_after:int
     * }
     */
    public function grant(
        int $userId,
        string $section,
        string $code,
        int $qty = 1,
        bool $isPremium = false
    ): array {
        if (!$this->canGrant($userId)) {
            throw new \RuntimeException('Выдача доступна только администраторам');
        }

        $section = trim($section);
        $code = trim($code);
        $qty = max(1, $qty);

        if (!in_array($section, self::SUPPORTED_SECTIONS, true)) {
            throw new \InvalidArgumentException('Раздел пока не поддерживается для выдачи');
        }

        if ($code === '') {
            throw new \InvalidArgumentException('Не указан код предмета');
        }

        $label = '';
        $qtyAfter = 0;

        if ($section === self::SECTION_MATERIALS) {
            [$label, $qtyAfter] = $this->grantMaterial($userId, $code, $qty, $isPremium);
        } elseif ($section === self::SECTION_RECIPES) {
            [$label, $qtyAfter] = $this->grantRecipe($userId, $code, $qty);
        } elseif ($section === self::SECTION_EQUIPMENT) {
            [$label, $qtyAfter] = $this->grantEquipment($userId, $code, $qty);
        } else {
            [$label, $qtyAfter] = $this->grantPack($userId, $code, $qty);
        }

        $this->economyRepository->logEncyclopediaGrant(
            $userId,
            $userId,
            $section,
            $code,
            $qty,
            $isPremium
        );

        return [
            'section' => $section,
            'code' => $code,
            'label' => $label,
            'qty' => $qty,
            'is_premium' => $isPremium,
            'qty_after' => $qtyAfter,
        ];
    }

    /**
     * @return array{0:string,1:int}
     */
    private function grantMaterial(int $userId, string $code, int $qty, bool $isPremium): array
    {
        $catalog = ProfessionMaterialConfig::materialCatalog();
        if (!isset($catalog[$code])) {
            throw new \InvalidArgumentException('Неизвестный материал: ' . $code);
        }

        $row = $catalog[$code];
        $catalogPremium = (bool)($row['is_premium'] ?? false);
        if ($catalogPremium !== $isPremium) {
            throw new \InvalidArgumentException(
                $isPremium
                    ? 'Этот материал не является премиум-сырьём'
                    : 'Для премиум-материала нужен флаг isPremium'
            );
        }

        $this->professionRepository->addUserMaterialQty($userId, $code, $qty, $isPremium);
        $qtyAfter = $this->professionRepository->getUserMaterialQty($userId, $code, $isPremium);

        return [(string)($row['label'] ?? $code), $qtyAfter];
    }

    /**
     * @return array{0:string,1:int}
     */
    private function grantRecipe(int $userId, string $code, int $qty): array
    {
        if (!ProfessionRecipeConfig::isKnownRecipe($code)) {
            throw new \InvalidArgumentException('Неизвестный рецепт: ' . $code);
        }

        $this->economyRepository->incrementLootItem(
            $userId,
            ChestLootConfig::LOOT_EVENT_GLOBAL,
            $code,
            ChestLootConfig::CATEGORY_RECIPE,
            $qty,
            'N'
        );

        $qtyAfter = $this->countLootItemQty($userId, $code);

        return [ProfessionRecipeConfig::getRecipeLabel($code), $qtyAfter];
    }

    /**
     * @return array{0:string,1:int}
     */
    private function grantEquipment(int $userId, string $code, int $qty): array
    {
        if (!EquipmentConfig::isCaftanCode($code)) {
            throw new \InvalidArgumentException('Неизвестная экипировка: ' . $code);
        }

        $this->economyRepository->incrementLootItem(
            $userId,
            ChestLootConfig::LOOT_EVENT_GLOBAL,
            $code,
            ChestLootConfig::CATEGORY_EQUIPMENT,
            $qty,
            'N'
        );

        $qtyAfter = $this->countLootItemQty($userId, $code);

        return [EquipmentConfig::getCaftanLabel($code), $qtyAfter];
    }

    /**
     * @return array{0:string,1:int}
     */
    private function grantPack(int $userId, string $code, int $qty): array
    {
        $label = ChestLootConfig::getLabel($code);
        if ($label === $code && !PackOpenConfig::isSupported($code)) {
            throw new \InvalidArgumentException('Неизвестный пак: ' . $code);
        }

        $this->economyRepository->incrementLootItem(
            $userId,
            ChestLootConfig::LOOT_EVENT_GLOBAL,
            $code,
            ChestLootConfig::CATEGORY_PACK,
            $qty,
            'Y'
        );

        $qtyAfter = $this->countLootItemQty($userId, $code);

        return [$label, $qtyAfter];
    }

    private function countLootItemQty(int $userId, string $code): int
    {
        $total = 0;
        foreach ($this->economyRepository->getLootItemStacksForUser($userId, ChestLootConfig::LOOT_EVENT_GLOBAL) as $stack) {
            if (($stack['code'] ?? '') === $code) {
                $total += (int)($stack['count'] ?? 0);
            }
        }

        return $total;
    }
}
