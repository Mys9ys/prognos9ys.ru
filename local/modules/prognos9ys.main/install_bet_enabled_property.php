<?php

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../../..');
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

if (!\Bitrix\Main\Loader::includeModule('iblock')) {
    fwrite(STDERR, 'ERROR: iblock module not loaded' . PHP_EOL);
    exit(1);
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/migrations/Cs2MigrationIblock.php';

use Sprint\Migration\Cs2MigrationIblock;

$iblocks = [
    'prognosis' => 'Футбол — прогнозы',
    'prognoscs2' => 'CS2 — прогнозы',
];

$property = [
    'NAME' => 'Ставка на исход',
    'CODE' => 'bet_enabled',
    'PROPERTY_TYPE' => 'S',
    'ROW_COUNT' => '1',
    'COL_COUNT' => '5',
    'HINT' => 'Y — ставка, N — отказ. Пусто — legacy/бот (backfill при расчёте матча).',
];

foreach ($iblocks as $code => $label) {
    $iblockId = (int)(\CIBlock::GetList([], ['CODE' => $code], false)->Fetch()['ID'] ?? 0);
    if ($iblockId <= 0) {
        $iblockId = Cs2MigrationIblock::findId($code);
    }
    if ($iblockId <= 0 && $code === 'prognosis') {
        $iblockId = 6;
    }
    if ($iblockId <= 0) {
        echo "SKIP: {$code} not found" . PHP_EOL;
        continue;
    }

    $existing = \CIBlockProperty::GetList([], ['IBLOCK_ID' => $iblockId, 'CODE' => 'bet_enabled'])->Fetch();
    if ($existing) {
        echo "OK: bet_enabled already exists in {$label} (ID {$iblockId}, property {$existing['ID']})" . PHP_EOL;
        continue;
    }

    $fields = array_merge($property, ['IBLOCK_ID' => $iblockId]);
    $prop = new \CIBlockProperty();
    $propertyId = (int)$prop->Add($fields);
    if ($propertyId <= 0) {
        fwrite(STDERR, "ERROR: failed to add bet_enabled to {$code}: " . $prop->LAST_ERROR . PHP_EOL);
        exit(1);
    }

    echo "ADDED: bet_enabled → {$label} (iblock {$iblockId}, property {$propertyId})" . PHP_EOL;
}

echo PHP_EOL . 'Done.' . PHP_EOL;
