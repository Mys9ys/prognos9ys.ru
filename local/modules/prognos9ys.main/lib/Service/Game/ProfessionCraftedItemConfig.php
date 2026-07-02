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
        return [
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
            'caftan_basic' => self::row('caftan_basic', 'Кафтан (обычный)', 60.0, self::STORAGE_EQUIPMENT),
            'caftan_embroidered' => self::row('caftan_embroidered', 'Кафтан (расшитый)', 170.0, self::STORAGE_EQUIPMENT),
            'caftan_grand' => self::row('caftan_grand', 'Кафтан (великолепный)', 420.0, self::STORAGE_EQUIPMENT),
        ];
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
