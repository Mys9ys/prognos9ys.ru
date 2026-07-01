<?php

namespace Prognos9ys\Main\Service\Game;

/**
 * Коды профессий добычи/обработки и выпадающих материалов.
 */
class ProfessionMaterialConfig
{
    public const WORK_MODE_SELF = 'self';
    public const WORK_MODE_TREASURY = 'treasury';
    public const WORK_MODE_LABOR = 'labor';
    public const WORK_MODE_LABOR_POSTER = 'labor_poster';

    public const SESSION_STATUS_ACTIVE = 'active';
    public const SESSION_STATUS_COMPLETED = 'completed';
    public const SESSION_STATUS_CANCELLED = 'cancelled';

    public const STARTER_PROFESSION_SLOTS = 2;

    /** Минимум профессий при первом выборе. */
    public const MIN_STARTER_PROFESSION_SLOTS = 1;

    /**
     * Сколько профессий можно изучить одновременно.
     * MVP: только стартовые 2. Позже: +1 за уровень, сертификат из сундука.
     */
    public static function maxProfessionSlots(int $playerLevel = 0, int $certificateBonus = 0): int
    {
        unset($playerLevel);

        return self::STARTER_PROFESSION_SLOTS + max(0, $certificateBonus);
    }

    /**
     * @return array<string, array{
     *   code:string,
     *   label:string,
     *   type:string,
     *   output:string,
     *   output_label:string,
     *   premium:string,
     *   premium_label:string
     * }>
     */
    public static function gatheringProfessions(): array
    {
        return [
            'woodcutter' => self::professionRow(
                'woodcutter',
                'Лесоруб',
                'gather',
                'log',
                'Бревно',
                'amber',
                'Янтарь'
            ),
            'quarryman' => self::professionRow(
                'quarryman',
                'Каменщик',
                'gather',
                'stone',
                'Камень',
                'marble',
                'Мрамор'
            ),
            'miner' => self::professionRow(
                'miner',
                'Рудокоп',
                'gather',
                'ore',
                'Руда',
                'gold_nugget',
                'Самородок'
            ),
            'sandgatherer' => self::professionRow(
                'sandgatherer',
                'Песчаный карьер',
                'gather',
                'sand',
                'Песок',
                'quartz',
                'Кварц'
            ),
            'cottongatherer' => self::professionRow(
                'cottongatherer',
                'Хлопкороб',
                'gather',
                'cotton',
                'Хлопок',
                'silk',
                'Шёлк'
            ),
        ];
    }

    /**
     * @return array<string, array{
     *   code:string,
     *   label:string,
     *   type:string,
     *   output:string,
     *   output_label:string,
     *   premium:string,
     *   premium_label:string,
     *   input?:string,
     *   input_label?:string
     * }>
     */
    public static function processingProfessions(): array
    {
        return [
            'carpenter' => self::professionRow(
                'carpenter',
                'Столяр',
                'process',
                'plank',
                'Доска',
                'fine_plank',
                'Эбеновая доска',
                'log',
                'Бревно'
            ),
            'stonemason' => self::professionRow(
                'stonemason',
                'Каменотес',
                'process',
                'block',
                'Блок',
                'fine_block',
                'Гранитный блок',
                'stone',
                'Камень'
            ),
            'smelter' => self::professionRow(
                'smelter',
                'Плавильщик',
                'process',
                'ingot',
                'Слиток',
                'fine_ingot',
                'Закалённый слиток',
                'ore',
                'Руда'
            ),
            'glassblower' => self::professionRow(
                'glassblower',
                'Стеклодув',
                'process',
                'glass',
                'Стекло',
                'fine_glass',
                'Хрусталь',
                'sand',
                'Песок'
            ),
            'weaver' => self::professionRow(
                'weaver',
                'Ткач',
                'process',
                'cloth',
                'Ткань',
                'fine_cloth',
                'Парча',
                'cotton',
                'Хлопок'
            ),
        ];
    }

    /**
     * @return array<string, array{
     *   code:string,
     *   label:string,
     *   type:string,
     *   output:string,
     *   output_label:string,
     *   premium:string,
     *   premium_label:string
     * }>
     */
    public static function allProfessions(): array
    {
        return array_merge(self::gatheringProfessions(), self::processingProfessions());
    }

    private static function professionRow(
        string $code,
        string $label,
        string $type,
        string $output,
        string $outputLabel,
        string $premium,
        string $premiumLabel,
        string $input = '',
        string $inputLabel = ''
    ): array {
        $row = [
            'code' => $code,
            'label' => $label,
            'type' => $type,
            'output' => $output,
            'output_label' => $outputLabel,
            'premium' => $premium,
            'premium_label' => $premiumLabel,
        ];

        if ($input !== '') {
            $row['input'] = $input;
            $row['input_label'] = $inputLabel;
        }

        return $row;
    }

    public static function isProcessingProfession(?array $definition): bool
    {
        return is_array($definition) && ($definition['type'] ?? '') === 'process';
    }

    public static function getProfessionInput(string $professionCode): ?string
    {
        $definition = self::getProfession($professionCode);
        if (!$definition || !self::isProcessingProfession($definition)) {
            return null;
        }

        $input = (string)($definition['input'] ?? '');

        return $input !== '' ? $input : null;
    }

    public static function getProfession(string $code): ?array
    {
        return self::allProfessions()[$code] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public static function materialEmojiMap(): array
    {
        return [
            'log' => '🪵',
            'stone' => '🪨',
            'ore' => '⛏️',
            'sand' => '🏖️',
            'cotton' => '🧵',
            'amber' => '🟠',
            'marble' => '⚪',
            'gold_nugget' => '🥇',
            'quartz' => '🔮',
            'silk' => '🎀',
            'plank' => '🪚',
            'block' => '🧱',
            'ingot' => '🔩',
            'glass' => '🫙',
            'cloth' => '🧶',
            'fine_plank' => '🌲',
            'fine_block' => '🏛️',
            'fine_ingot' => '✨',
            'fine_glass' => '🥂',
            'fine_cloth' => '👑',
        ];
    }

    public static function materialEmoji(string $code): string
    {
        return self::materialEmojiMap()[$code] ?? '📦';
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    public static function buildInventoryStacksFromRows(array $rows): array
    {
        $catalog = self::materialCatalog();
        $items = [];

        foreach ($rows as $row) {
            $code = (string)($row['UF_MATERIAL_CODE'] ?? '');
            $qty = (int)($row['UF_QTY'] ?? 0);
            if ($code === '' || $qty <= 0) {
                continue;
            }

            $isPremium = ($row['UF_IS_PREMIUM'] ?? '') === 'Y';
            $meta = $catalog[$code] ?? null;
            $items[] = [
                'code' => $code,
                'category' => 'material',
                'count' => $qty,
                'label' => $meta['label'] ?? $code,
                'type_caption' => $isPremium ? '★' : 'Сырьё',
                'emoji' => $meta['emoji'] ?? self::materialEmoji($code),
                'is_premium' => $isPremium,
            ];
        }

        return $items;
    }

    /**
     * @return array<string, array{code:string,label:string,nominal:float,is_premium:bool,emoji:string}>
     */
    public static function materialCatalog(): array
    {
        $catalog = [];
        $premiumGather = ProfessionEconomyConfig::premiumMaterialsGathering();
        $premiumKeyMap = [
            'woodcutter' => 'wood',
            'quarryman' => 'stone',
            'miner' => 'ore',
            'sandgatherer' => 'sand',
            'cottongatherer' => 'cotton',
        ];

        foreach (self::allProfessions() as $profession) {
            $isGather = ($profession['type'] ?? '') === 'gather';
            $catalog[$profession['output']] = [
                'code' => $profession['output'],
                'label' => $profession['output_label'],
                'nominal' => $isGather
                    ? ProfessionEconomyConfig::NOMINAL_RAW
                    : ProfessionEconomyConfig::NOMINAL_PROCESSED,
                'is_premium' => false,
                'emoji' => self::materialEmoji($profession['output']),
            ];

            $premiumKey = $premiumKeyMap[$profession['code']] ?? '';
            if ($premiumKey && isset($premiumGather[$premiumKey])) {
                $row = $premiumGather[$premiumKey];
                $catalog[$row['code']] = [
                    'code' => $row['code'],
                    'label' => $row['label'],
                    'nominal' => (float)$row['nominal'],
                    'is_premium' => true,
                    'emoji' => self::materialEmoji($row['code']),
                ];
            } else {
                $catalog[$profession['premium']] = [
                    'code' => $profession['premium'],
                    'label' => $profession['premium_label'],
                    'nominal' => 80.0,
                    'is_premium' => true,
                    'emoji' => self::materialEmoji($profession['premium']),
                ];
            }
        }

        return $catalog;
    }

    public static function getMaterialLabel(string $code): string
    {
        return self::materialCatalog()[$code]['label'] ?? $code;
    }

    /**
     * Базовые переработанные материалы (доска, блок, слиток, стекло, ткань).
     *
     * @return string[]
     */
    public static function basicProcessedMaterialCodes(): array
    {
        return ['plank', 'block', 'ingot', 'glass', 'cloth'];
    }

    public static function isBasicProcessedMaterial(string $code): bool
    {
        return in_array($code, self::basicProcessedMaterialCodes(), true);
    }
}
