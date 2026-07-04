<?php

namespace Prognos9ys\Main\Service\Game;

/**
 * Экипировка: кафтаны ткача — бонусы к комбо и премиум-дропу на ферме.
 */
class EquipmentConfig
{
    /**
     * @return array<string, array{
     *   code:string,
     *   label:string,
     *   combo_x2_bonus:float,
     *   combo_x3_bonus:float,
     *   premium_bonus:float
     * }>
     */
    public static function caftans(): array
    {
        return [
            'caftan_basic' => [
                'code' => 'caftan_basic',
                'label' => 'Кафтан (обычный)',
                'combo_x2_bonus' => 0.03,
                'combo_x3_bonus' => 0.01,
                'premium_bonus' => 0.002,
            ],
            'caftan_embroidered' => [
                'code' => 'caftan_embroidered',
                'label' => 'Кафтан (расшитый)',
                'combo_x2_bonus' => 0.06,
                'combo_x3_bonus' => 0.02,
                'premium_bonus' => 0.004,
            ],
            'caftan_grand' => [
                'code' => 'caftan_grand',
                'label' => 'Кафтан (великолепный)',
                'combo_x2_bonus' => 0.10,
                'combo_x3_bonus' => 0.04,
                'premium_bonus' => 0.008,
            ],
        ];
    }

    public static function isCaftanCode(string $code): bool
    {
        $code = trim($code);

        return $code !== '' && isset(self::caftans()[$code]);
    }

    /**
     * @return array{combo_x2_bonus:float,combo_x3_bonus:float,premium_bonus:float}|null
     */
    public static function getCaftanBonus(string $code): ?array
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }

        $def = self::caftans()[$code] ?? null;
        if (!$def) {
            return null;
        }

        return [
            'combo_x2_bonus' => (float)$def['combo_x2_bonus'],
            'combo_x3_bonus' => (float)$def['combo_x3_bonus'],
            'premium_bonus' => (float)$def['premium_bonus'],
        ];
    }

    public static function getCaftanLabel(string $code): string
    {
        $code = trim($code);

        return (string)(self::caftans()[$code]['label'] ?? ProfessionCraftedItemConfig::getLabel($code));
    }

    /**
     * Шансы с учётом кафтана (для UI и расчёта смены).
     *
     * @return array{combo_x2:float,combo_x3:float,premium:float,premium_min_level:int}
     */
    public static function chancesForLevel(int $level, ?string $equippedCaftanCode = null): array
    {
        $level = max(1, $level);
        $p2 = ProfessionEconomyConfig::comboDoubleChance($level);
        $p3 = ProfessionEconomyConfig::comboTripleChance($level);
        $premium = ProfessionEconomyConfig::premiumDropChance($level);

        $bonus = self::getCaftanBonus((string)$equippedCaftanCode);
        if ($bonus) {
            $p2 = min(0.95, $p2 + $bonus['combo_x2_bonus']);
            $p3 = min(0.50, $p3 + $bonus['combo_x3_bonus']);
            $premium = min(0.25, $premium + $bonus['premium_bonus']);
        }

        return [
            'combo_x2' => round($p2 * 100, 2),
            'combo_x3' => round($p3 * 100, 2),
            'premium' => round($premium * 100, 3),
            'premium_min_level' => ProfessionEconomyConfig::PREMIUM_DROP_MIN_LEVEL,
        ];
    }

    /**
     * @return array{
     *   equipped_caftan:?string,
     *   equipped_label:?string,
     *   combo_x2_bonus_pp:float,
     *   combo_x3_bonus_pp:float,
     *   premium_bonus_pp:float
     * }
     */
    public static function buildSummary(?string $equippedCaftanCode): array
    {
        $code = trim((string)$equippedCaftanCode);
        if ($code === '' || !self::isCaftanCode($code)) {
            return array_merge([
                'equipped_caftan' => null,
                'equipped_label' => null,
                'combo_x2_bonus_pp' => 0.0,
                'combo_x3_bonus_pp' => 0.0,
                'premium_bonus_pp' => 0.0,
            ], self::buildSlotPanel(null));
        }

        $bonus = self::getCaftanBonus($code) ?? [
            'combo_x2_bonus' => 0.0,
            'combo_x3_bonus' => 0.0,
            'premium_bonus' => 0.0,
        ];

        return array_merge([
            'equipped_caftan' => $code,
            'equipped_label' => self::getCaftanLabel($code),
            'combo_x2_bonus_pp' => round($bonus['combo_x2_bonus'] * 100, 1),
            'combo_x3_bonus_pp' => round($bonus['combo_x3_bonus'] * 100, 1),
            'premium_bonus_pp' => round($bonus['premium_bonus'] * 100, 2),
        ], self::buildSlotPanel($code));
    }

    /**
     * Слоты экипировки персонажа (RPG). enabled=false — зарезервировано под крафт позже.
     *
     * @return array<string, array{id:string,label:string,enabled:bool,slot_group:string}>
     */
    public static function slotDefinitions(): array
    {
        return [
            'head' => ['id' => 'head', 'label' => 'Голова', 'enabled' => false, 'slot_group' => 'armor'],
            'amulet' => ['id' => 'amulet', 'label' => 'Амулет', 'enabled' => false, 'slot_group' => 'accessory'],
            'cloak' => ['id' => 'cloak', 'label' => 'Плащ', 'enabled' => false, 'slot_group' => 'armor'],
            'body' => ['id' => 'body', 'label' => 'Тело', 'enabled' => true, 'slot_group' => 'armor'],
            'gloves' => ['id' => 'gloves', 'label' => 'Перчатки', 'enabled' => false, 'slot_group' => 'armor'],
            'belt' => ['id' => 'belt', 'label' => 'Пояс', 'enabled' => false, 'slot_group' => 'armor'],
            'boots' => ['id' => 'boots', 'label' => 'Обувь', 'enabled' => false, 'slot_group' => 'armor'],
            'ring_left' => ['id' => 'ring_left', 'label' => 'Кольцо', 'enabled' => false, 'slot_group' => 'ring'],
            'ring_right' => ['id' => 'ring_right', 'label' => 'Кольцо', 'enabled' => false, 'slot_group' => 'ring'],
        ];
    }

    /**
     * @return array{slots: array<int, array<string, mixed>>}
     */
    public static function buildSlotPanel(?string $equippedCaftanCode): array
    {
        $equippedCaftanCode = trim((string)$equippedCaftanCode);
        $slots = [];

        foreach (self::slotDefinitions() as $slotId => $definition) {
            $equippedCode = null;
            $equippedLabel = null;

            if ($slotId === 'body' && $equippedCaftanCode !== '' && self::isCaftanCode($equippedCaftanCode)) {
                $equippedCode = $equippedCaftanCode;
                $equippedLabel = self::getCaftanLabel($equippedCaftanCode);
            }

            $slots[] = [
                'id' => $slotId,
                'label' => (string)$definition['label'],
                'enabled' => (bool)$definition['enabled'],
                'slot_group' => (string)$definition['slot_group'],
                'equipped_code' => $equippedCode,
                'equipped_label' => $equippedLabel,
            ];
        }

        return ['slots' => $slots];
    }
}
