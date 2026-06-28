<?php

namespace Prognos9ys\Main\Service\Game;

/**
 * Таблицы лута сундуков: ЧМ-26 (match / shop / ачивка ЧМ2026) и generic (level / ачивки).
 */
class ChestLootConfig
{
    public const BLOCK2_CHANCE_PERCENT = 70;
    public const BLOCK3_CHANCE_PERCENT = 65;

    public const CATEGORY_XP_BANK = 'xp_bank';
    public const CATEGORY_CERT = 'cert';
    public const CATEGORY_PACK = 'pack';

    /** Лут без привязки к событию (паки level / классические ачивки). */
    public const LOOT_EVENT_GLOBAL = 0;

    /** @var string[] */
    public const WC26_OPENABLE_CHEST_TYPES = [
        TreasureService::CHEST_TYPE_MATCH,
        TreasureService::CHEST_TYPE_SHOP_WC26,
        TreasureService::CHEST_TYPE_WC26_ACHIEVEMENT,
    ];

    /** @var string[] */
    public const GENERIC_OPENABLE_CHEST_TYPES = [
        TreasureService::CHEST_TYPE_LEVEL,
        TreasureService::CHEST_TYPE_ACHIEVEMENT,
        TreasureService::CHEST_TYPE_PROFESSION,
    ];

    /**
     * @return array<int, array{code:string,weight:int,kind:string,currency?:string,amount?:float,category?:string,label:string}>
     */
    public static function getBlock1Table(): array
    {
        return [
            ['code' => 'rublius_1', 'weight' => 36, 'kind' => 'currency', 'currency' => GameEconomyConfig::CURRENCY_RUBLIUS, 'amount' => 1.0, 'label' => '1 рублиус'],
            ['code' => 'rublius_3', 'weight' => 18, 'kind' => 'currency', 'currency' => GameEconomyConfig::CURRENCY_RUBLIUS, 'amount' => 3.0, 'label' => '3 рублиуса'],
            ['code' => 'rublius_5', 'weight' => 5, 'kind' => 'currency', 'currency' => GameEconomyConfig::CURRENCY_RUBLIUS, 'amount' => 5.0, 'label' => '5 рублиусов'],
            ['code' => 'prognobaks_10', 'weight' => 36, 'kind' => 'currency', 'currency' => GameEconomyConfig::CURRENCY_PROGNOBAKS, 'amount' => 10.0, 'label' => '10 прогнобаксов'],
            ['code' => 'prognobaks_25', 'weight' => 18, 'kind' => 'currency', 'currency' => GameEconomyConfig::CURRENCY_PROGNOBAKS, 'amount' => 25.0, 'label' => '25 прогнобаксов'],
            ['code' => 'prognobaks_50', 'weight' => 5, 'kind' => 'currency', 'currency' => GameEconomyConfig::CURRENCY_PROGNOBAKS, 'amount' => 50.0, 'label' => '50 прогнобаксов'],
        ];
    }

    /**
     * @return array<int, array{code:string,weight:int,kind:string,category:string,label:string}>
     */
    public static function getBlock2Table(): array
    {
        return [
            ['code' => 'xp_bank_player_25', 'weight' => 42, 'kind' => 'item', 'category' => self::CATEGORY_XP_BANK, 'label' => 'Банка XP игрока (25)'],
            ['code' => 'xp_bank_player_50', 'weight' => 24, 'kind' => 'item', 'category' => self::CATEGORY_XP_BANK, 'label' => 'Банка XP игрока (50)'],
            ['code' => 'xp_bank_mining_25', 'weight' => 14, 'kind' => 'item', 'category' => self::CATEGORY_XP_BANK, 'label' => 'Банка XP добычи (25)'],
            ['code' => 'xp_bank_crafting_25', 'weight' => 11, 'kind' => 'item', 'category' => self::CATEGORY_XP_BANK, 'label' => 'Банка XP крафта (25)'],
            ['code' => 'cert_profession', 'weight' => 6, 'kind' => 'item', 'category' => self::CATEGORY_CERT, 'label' => 'Сертификат на профессию'],
            ['code' => 'cert_estate', 'weight' => 3, 'kind' => 'item', 'category' => self::CATEGORY_CERT, 'label' => 'Сертификат на усадьбу'],
        ];
    }

    /**
     * @return array<int, array{code:string,weight:int,kind:string,category:string,label:string}>
     */
    public static function getBlock3Table(): array
    {
        return self::getWc26Block3Table();
    }

    /**
     * @return array<int, array{code:string,weight:int,kind:string,category:string,label:string}>
     */
    public static function getWc26Block3Table(): array
    {
        return [
            ['code' => 'pack_pennant_wc26', 'weight' => 5000, 'kind' => 'item', 'category' => self::CATEGORY_PACK, 'label' => 'Пак ЧМ-26: вымпел'],
            ['code' => 'pack_scarf_wc26', 'weight' => 3000, 'kind' => 'item', 'category' => self::CATEGORY_PACK, 'label' => 'Пак ЧМ-26: шарф'],
            ['code' => 'pack_coach_action_wc26', 'weight' => 143, 'kind' => 'item', 'category' => self::CATEGORY_PACK, 'label' => 'Пак ЧМ-26: действие тренера'],
            ['code' => 'pack_field_action_wc26', 'weight' => 143, 'kind' => 'item', 'category' => self::CATEGORY_PACK, 'label' => 'Пак ЧМ-26: действие на поле'],
            ['code' => 'pack_formation_wc26', 'weight' => 143, 'kind' => 'item', 'category' => self::CATEGORY_PACK, 'label' => 'Пак ЧМ-26: расстановка'],
            ['code' => 'pack_coach_wc26', 'weight' => 143, 'kind' => 'item', 'category' => self::CATEGORY_PACK, 'label' => 'Пак ЧМ-26: тренер'],
            ['code' => 'pack_player_wc26', 'weight' => 286, 'kind' => 'item', 'category' => self::CATEGORY_PACK, 'label' => 'Пак ЧМ-26: игрок'],
            ['code' => 'pack_team_wc26', 'weight' => 571, 'kind' => 'item', 'category' => self::CATEGORY_PACK, 'label' => 'Пак ЧМ-26: команда'],
            ['code' => 'pack_actions_wc26', 'weight' => 571, 'kind' => 'item', 'category' => self::CATEGORY_PACK, 'label' => 'Пак ЧМ-26: действия'],
        ];
    }

    /**
     * Паки без привязки к событию (level / классические ачивки).
     *
     * @return array<int, array{code:string,weight:int,kind:string,category:string,label:string}>
     */
    public static function getGenericBlock3Table(): array
    {
        return [
            ['code' => 'pack_pennant', 'weight' => 5000, 'kind' => 'item', 'category' => self::CATEGORY_PACK, 'label' => 'Пак: вымпел'],
            ['code' => 'pack_scarf', 'weight' => 3000, 'kind' => 'item', 'category' => self::CATEGORY_PACK, 'label' => 'Пак: шарф'],
            ['code' => 'pack_coach_action', 'weight' => 143, 'kind' => 'item', 'category' => self::CATEGORY_PACK, 'label' => 'Пак: действие тренера'],
            ['code' => 'pack_field_action', 'weight' => 143, 'kind' => 'item', 'category' => self::CATEGORY_PACK, 'label' => 'Пак: действие на поле'],
            ['code' => 'pack_formation', 'weight' => 143, 'kind' => 'item', 'category' => self::CATEGORY_PACK, 'label' => 'Пак: расстановка'],
            ['code' => 'pack_coach', 'weight' => 143, 'kind' => 'item', 'category' => self::CATEGORY_PACK, 'label' => 'Пак: тренер'],
            ['code' => 'pack_player', 'weight' => 286, 'kind' => 'item', 'category' => self::CATEGORY_PACK, 'label' => 'Пак: игрок'],
            ['code' => 'pack_team', 'weight' => 571, 'kind' => 'item', 'category' => self::CATEGORY_PACK, 'label' => 'Пак: команда'],
            ['code' => 'pack_actions', 'weight' => 571, 'kind' => 'item', 'category' => self::CATEGORY_PACK, 'label' => 'Пак: действия'],
        ];
    }

    public static function getLabel(string $code): string
    {
        foreach ([
            self::getBlock1Table(),
            self::getBlock2Table(),
            self::getWc26Block3Table(),
            self::getGenericBlock3Table(),
        ] as $table) {
            foreach ($table as $row) {
                if (($row['code'] ?? '') === $code) {
                    return (string)($row['label'] ?? $code);
                }
            }
        }

        return $code;
    }

    public static function isXpBankCode(string $code): bool
    {
        return self::parseXpBankCode($code) !== null;
    }

    /** Лут без привязки к событию в каталоге биржи (стаки могут лежать с event_id 0 или якорным). */
    public static function isEventAgnosticLootCategory(string $category): bool
    {
        $category = trim($category);

        return $category === self::CATEGORY_XP_BANK
            || $category === self::CATEGORY_CERT;
    }

    /**
     * @return array{kind:string,xp:float,label:string}|null
     */
    public static function parseXpBankCode(string $code): ?array
    {
        $code = trim($code);
        if (!preg_match('/^xp_bank_(player|mining|crafting)_(\d+)$/', $code, $matches)) {
            return null;
        }

        $xp = round((float)$matches[2], 1);
        if ($xp <= 0) {
            return null;
        }

        return [
            'kind' => (string)$matches[1],
            'xp' => $xp,
            'label' => self::getLabel($code),
        ];
    }

    /**
     * Подпись награды для лога/UI (без emoji — HL/JSON в БД их не держит).
     *
     * @param array<string, mixed>|null $block
     */
    public static function formatBlockLabel(?array $block): string
    {
        if (!is_array($block)) {
            return '';
        }

        $code = (string)($block['code'] ?? '');
        if ($code !== '') {
            return self::getLabel($code);
        }

        if (($block['kind'] ?? '') === 'currency') {
            $amount = (float)($block['amount'] ?? 0);
            $currency = (string)($block['currency'] ?? '');

            if ($currency === GameEconomyConfig::CURRENCY_RUBLIUS) {
                return self::formatRubliusAmount($amount);
            }
            if ($currency === GameEconomyConfig::CURRENCY_PROGNOBAKS) {
                return self::formatPrognobaksAmount($amount);
            }
        }

        return trim((string)($block['label'] ?? ''));
    }

    public static function formatRubliusAmount(float $amount): string
    {
        $n = (int)round($amount);
        if ($n <= 0) {
            return '0 рублиусов';
        }

        $mod10 = $n % 10;
        $mod100 = $n % 100;
        if ($mod10 === 1 && $mod100 !== 11) {
            return $n . ' рублиус';
        }
        if ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14)) {
            return $n . ' рублиуса';
        }

        return $n . ' рублиусов';
    }

    public static function formatPrognobaksAmount(float $amount): string
    {
        $n = (int)round($amount);
        if ($n <= 0) {
            return '0 прогнобаксов';
        }

        $mod10 = $n % 10;
        $mod100 = $n % 100;
        if ($mod10 === 1 && $mod100 !== 11) {
            return $n . ' прогнобакс';
        }
        if ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14)) {
            return $n . ' прогнобакса';
        }

        return $n . ' прогнобаксов';
    }

    /**
     * Лут сундука профессии: валюта + банки XP / сертификаты, без карт и вымпелов.
     *
     * @return array{block1:array|null,block2:array|null,block3:array|null}
     */
    public static function rollProfessionLoot(): array
    {
        $block1 = self::rollFromTable(self::getBlock1Table());

        $block2 = null;
        if (random_int(1, 100) <= self::BLOCK2_CHANCE_PERCENT) {
            $block2 = self::rollFromTable(self::getProfessionBlock2Table());
        }

        return [
            'block1' => $block1,
            'block2' => $block2,
            'block3' => null,
        ];
    }

    /**
     * @return array<int, array{code:string,weight:int,kind:string,category:string,label:string}>
     */
    public static function getProfessionBlock2Table(): array
    {
        return self::getBlock2Table();
    }

    public static function getItemCategory(string $code): string
    {
        foreach ([self::getBlock2Table(), self::getWc26Block3Table(), self::getGenericBlock3Table()] as $table) {
            foreach ($table as $row) {
                if (($row['code'] ?? '') === $code) {
                    return (string)($row['category'] ?? self::CATEGORY_PACK);
                }
            }
        }

        return self::CATEGORY_PACK;
    }

    /**
     * Короткая подпись типа пака для слота инвентаря (шарф, игрок, …).
     */
    public static function getPackTypeCaption(string $code): string
    {
        $label = self::getLabel($code);
        if (preg_match('/:\s*(.+)$/u', $label, $matches)) {
            return (string)$matches[1];
        }

        return 'Пак';
    }

    /**
     * @param array<int, array{weight:int}> $table
     * @return array<string, mixed>|null
     */
    public static function rollFromTable(array $table): ?array
    {
        $total = 0;
        foreach ($table as $row) {
            $total += (int)($row['weight'] ?? 0);
        }

        if ($total <= 0) {
            return null;
        }

        $roll = random_int(1, $total);
        $cursor = 0;

        foreach ($table as $row) {
            $cursor += (int)($row['weight'] ?? 0);
            if ($roll <= $cursor) {
                return $row;
            }
        }

        return $table[array_key_last($table)] ?? null;
    }
}
