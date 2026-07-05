<?php

namespace Prognos9ys\Main\Service\Game;

/**
 * Крафтовые предметы профессий: номиналы, подписи, тип хранения.
 */
class ProfessionCraftedItemConfig
{
    public const STORAGE_MATERIAL = 'material';
    public const STORAGE_EQUIPMENT = 'equipment';

    /**
     * @return array<string, array{code:string,label:string,nominal:float,storage:string}>
     */
    public static function all(): array
    {
        return array_merge([
            'clean_scroll' => self::row('clean_scroll', 'Чистый свиток', 5.0, self::STORAGE_MATERIAL),
            'nails' => self::row('nails', 'Гвозди', 1.0, self::STORAGE_MATERIAL),
            'hinge' => self::row('hinge', 'Петли', 2.5, self::STORAGE_MATERIAL),
            'handle' => self::row('handle', 'Ручка', 5.0, self::STORAGE_MATERIAL),
            'beam' => self::row('beam', 'Брус', 25.0, self::STORAGE_MATERIAL),
            'small_frame' => self::row('small_frame', 'Малая рама', 16.0, self::STORAGE_MATERIAL),
            'frame' => self::row('frame', 'Рама', 34.0, self::STORAGE_MATERIAL),
            'tile' => self::row('tile', 'Плитка', 5.0, self::STORAGE_MATERIAL),
            'rope' => self::row('rope', 'Верёвка', 10.0, self::STORAGE_MATERIAL),
            'burlap' => self::row('burlap', 'Мешковина', 40.0, self::STORAGE_MATERIAL),
            'window_small' => self::row('window_small', 'Окно малое', 36.0, self::STORAGE_MATERIAL),
            'latch' => self::row('latch', 'Защёлка', 12.5, self::STORAGE_MATERIAL),
            'bracket' => self::row('bracket', 'Скоба', 7.5, self::STORAGE_MATERIAL),
            'foundation_block' => self::row('foundation_block', 'Фундаментный блок', 20.0, self::STORAGE_MATERIAL),
            'threshold' => self::row('threshold', 'Порог', 40.0, self::STORAGE_MATERIAL),
            'window_regular' => self::row('window_regular', 'Окно обычное', 64.0, self::STORAGE_MATERIAL),
            'door' => self::row('door', 'Дверь', 111.0, self::STORAGE_MATERIAL),
            'arch_lintel' => self::row('arch_lintel', 'Арка/перемычка', 70.0, self::STORAGE_MATERIAL),
            'fence_panel' => self::row('fence_panel', 'Секция плетня', 40.0, self::STORAGE_MATERIAL),
            'wall_section_fence' => self::row('wall_section_fence', 'Секция забора', 55.0, self::STORAGE_MATERIAL),
            'wall_section' => self::row('wall_section', 'Секция стены', 71.0, self::STORAGE_MATERIAL),
            'wall_section_corner' => self::row('wall_section_corner', 'Угол стены', 59.0, self::STORAGE_MATERIAL),
            'wall_section_window' => self::row('wall_section_window', 'Стена под окно', 78.0, self::STORAGE_MATERIAL),
            'wall_section_door' => self::row('wall_section_door', 'Секция с проёмом', 133.0, self::STORAGE_MATERIAL),
            'roof_bundle' => self::row('roof_bundle', 'Пакет крыши', 235.0, self::STORAGE_MATERIAL),
            'roof_bundle_light' => self::row('roof_bundle_light', 'Лёгкое покрытие крыши', 140.0, self::STORAGE_MATERIAL),
        ], CaftanRecipeConfig::craftedItemEntries());
    }

    public static function isKnownItem(string $code): bool
    {
        return isset(self::all()[trim($code)]);
    }

    public static function getLabel(string $code): string
    {
        $code = trim($code);

        return (string)(self::all()[$code]['label'] ?? $code);
    }

    public static function getNominal(string $code): float
    {
        $code = trim($code);

        return (float)(self::all()[$code]['nominal'] ?? 10.0);
    }

    public static function getStorage(string $code): string
    {
        $code = trim($code);

        return (string)(self::all()[$code]['storage'] ?? self::STORAGE_MATERIAL);
    }

    /**
     * @return array<string, array{code:string,label:string,nominal:float,is_premium:bool,emoji:string}>
     */
    public static function materialCatalogRows(): array
    {
        $rows = [];
        foreach (self::all() as $item) {
            if (($item['storage'] ?? '') !== self::STORAGE_MATERIAL) {
                continue;
            }

            $code = (string)$item['code'];
            $rows[$code] = [
                'code' => $code,
                'label' => (string)$item['label'],
                'nominal' => (float)$item['nominal'],
                'is_premium' => false,
                'emoji' => ProfessionMaterialConfig::materialEmoji($code),
            ];
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $item
     * @return array{code:string,label:string,nominal:float,storage:string}
     */
    private static function row(string $code, string $label, float $nominal, string $storage): array
    {
        return [
            'code' => $code,
            'label' => $label,
            'nominal' => $nominal,
            'storage' => $storage,
        ];
    }
}
