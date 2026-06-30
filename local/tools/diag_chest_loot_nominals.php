<?php
declare(strict_types=1);

/**
 * Средний номинал лута с сундуков по журналу открытий (HL prognos9ys_chest_open_log).
 *
 * Usage: php local/tools/diag_chest_loot_nominals.php
 */

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../..');
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Prognos9ys\Main\Model\Repository\GameEconomyRepository;
use Prognos9ys\Main\Service\Game\ChestLootConfig;
use Prognos9ys\Main\Service\Game\ExchangeNominalConfig;
use Prognos9ys\Main\Service\Game\GameEconomyConfig;
use Prognos9ys\Main\Service\Game\TreasureService;

if (!\Bitrix\Main\Loader::includeModule('prognos9ys.main')) {
    echo "module prognos9ys.main not loaded\n";
    exit(1);
}

$repo = new GameEconomyRepository();
$dataClass = $repo->getChestOpenLogDataClass();

/** @var array<string, array{label:string,opens:int,total:float,block1:float,block2:float,block3:float,items:array<string,array{count:int,nominal:float}>}> */
$byType = [];
$grandOpens = 0;
$grandTotal = 0.0;
$skippedJson = 0;

$response = $dataClass::getList([
    'select' => ['ID', 'UF_CHEST_TYPE', 'UF_LOOT_JSON', 'UF_CREATED_AT'],
    'order' => ['ID' => 'ASC'],
]);

while ($row = $response->fetch()) {
    $type = trim((string)($row['UF_CHEST_TYPE'] ?? ''));
    if ($type === '') {
        $type = 'unknown';
    }

    $loot = json_decode((string)($row['UF_LOOT_JSON'] ?? ''), true);
    if (!is_array($loot)) {
        $skippedJson++;
        continue;
    }

    $value = valueLootInPrognobaks($loot);

    if (!isset($byType[$type])) {
        $byType[$type] = [
            'label' => chestTypeLabel($type),
            'opens' => 0,
            'total' => 0.0,
            'block1' => 0.0,
            'block2' => 0.0,
            'block3' => 0.0,
            'items' => [],
        ];
    }

    $byType[$type]['opens']++;
    $byType[$type]['total'] = round($byType[$type]['total'] + $value['total'], 1);
    $byType[$type]['block1'] = round($byType[$type]['block1'] + $value['block1'], 1);
    $byType[$type]['block2'] = round($byType[$type]['block2'] + $value['block2'], 1);
    $byType[$type]['block3'] = round($byType[$type]['block3'] + $value['block3'], 1);

    foreach ($value['item_lines'] as $line) {
        $key = $line['key'];
        if (!isset($byType[$type]['items'][$key])) {
            $byType[$type]['items'][$key] = ['count' => 0, 'nominal' => 0.0, 'label' => $line['label']];
        }
        $byType[$type]['items'][$key]['count']++;
        $byType[$type]['items'][$key]['nominal'] = round(
            $byType[$type]['items'][$key]['nominal'] + $line['nominal'],
            1
        );
    }

    $grandOpens++;
    $grandTotal = round($grandTotal + $value['total'], 1);
}

echo "Chest loot nominal stats (🪙 equivalent, exchange reference prices)\n";
echo str_repeat('=', 72) . "\n";
echo "Log rows parsed: {$grandOpens}\n";
if ($skippedJson > 0) {
    echo "Skipped (bad JSON): {$skippedJson}\n";
}
echo 'Rublius rate: 1 💎 = ' . GameEconomyConfig::RUBLIUS_TO_PROGNOBAKS . " 🪙\n\n";

if ($grandOpens === 0) {
    echo "No chest open log entries yet.\n";
    exit(0);
}

uasort($byType, static function (array $a, array $b): int {
    return $b['opens'] <=> $a['opens'];
});

foreach ($byType as $type => $stat) {
    $avg = round($stat['total'] / $stat['opens'], 1);
    $avg1 = round($stat['block1'] / $stat['opens'], 1);
    $avg2 = round($stat['block2'] / $stat['opens'], 1);
    $avg3 = round($stat['block3'] / $stat['opens'], 1);
    $chestNominal = ExchangeNominalConfig::getChestNominal($type);

    echo "{$stat['label']} [{$type}]\n";
    echo str_repeat('-', 72) . "\n";
    echo sprintf("  Opens:           %d\n", $stat['opens']);
    echo sprintf("  Avg loot total:  %.1f 🪙\n", $avg);
    echo sprintf("  Avg block1 $/💎: %.1f 🪙\n", $avg1);
    echo sprintf("  Avg block2 item: %.1f 🪙\n", $avg2);
    echo sprintf("  Avg block3 pack: %.1f 🪙\n", $avg3);
    if ($chestNominal > 0) {
        echo sprintf("  Chest sell ref:  %.1f 🪙 (exchange nominal for sealed chest)\n", $chestNominal);
        echo sprintf("  Loot / chest ref: %.0f%%\n", $chestNominal > 0 ? round(100 * $avg / $chestNominal) : 0);
    }

    $items = $stat['items'];
    uasort($items, static function (array $a, array $b): int {
        return $b['count'] <=> $a['count'];
    });

    echo "  Top drops:\n";
    $shown = 0;
    foreach ($items as $item) {
        if ($shown >= 8) {
            break;
        }
        $pct = round(100 * $item['count'] / $stat['opens']);
        echo sprintf(
            "    %-28s %4d× (%3d%%)  avg %.1f 🪙\n",
            mb_strimwidth($item['label'], 0, 28, '…'),
            $item['count'],
            $pct,
            $item['count'] > 0 ? round($item['nominal'] / $item['count'], 1) : 0
        );
        $shown++;
    }
    echo "\n";
}

$grandAvg = round($grandTotal / $grandOpens, 1);
echo str_repeat('=', 72) . "\n";
echo sprintf("ALL TYPES: %d opens, avg %.1f 🪙 per chest\n", $grandOpens, $grandAvg);

/**
 * @param array<string, mixed> $loot
 * @return array{
 *   total:float,
 *   block1:float,
 *   block2:float,
 *   block3:float,
 *   item_lines: array<int, array{key:string,label:string,nominal:float}>
 * }
 */
function valueLootInPrognobaks(array $loot): array
{
    $block1 = valueCurrencyBlock($loot['block1'] ?? null);
    $block2 = valueItemBlock($loot['block2'] ?? null);
    $block3 = valueItemBlock($loot['block3'] ?? null);

    $itemLines = [];
    foreach ([$block2, $block3] as $block) {
        if ($block['line'] !== null) {
            $itemLines[] = $block['line'];
        }
    }

    return [
        'total' => round($block1 + $block2['nominal'] + $block3['nominal'], 1),
        'block1' => $block1,
        'block2' => $block2['nominal'],
        'block3' => $block3['nominal'],
        'item_lines' => $itemLines,
    ];
}

function valueCurrencyBlock($block): float
{
    if (!is_array($block) || ($block['kind'] ?? '') !== 'currency') {
        return 0.0;
    }

    $amount = (float)($block['amount'] ?? 0);
    $currency = (string)($block['currency'] ?? '');

    if ($amount <= 0) {
        return 0.0;
    }

    if ($currency === GameEconomyConfig::CURRENCY_PROGNOBAKS) {
        return round($amount, 1);
    }

    if ($currency === GameEconomyConfig::CURRENCY_RUBLIUS) {
        return round($amount * GameEconomyConfig::RUBLIUS_TO_PROGNOBAKS, 1);
    }

    return 0.0;
}

/**
 * @return array{nominal:float,line:?array{key:string,label:string,nominal:float}}
 */
function valueItemBlock($block): array
{
    if (!is_array($block) || ($block['kind'] ?? '') !== 'item') {
        return ['nominal' => 0.0, 'line' => null];
    }

    $code = (string)($block['code'] ?? '');
    if ($code === '') {
        return ['nominal' => 0.0, 'line' => null];
    }

    $category = (string)($block['category'] ?? ChestLootConfig::getItemCategory($code));
    $nominal = ExchangeNominalConfig::getLootNominal($code, $category);
    $label = ChestLootConfig::getLabel($code);

    return [
        'nominal' => round($nominal, 1),
        'line' => [
            'key' => $category . '|' . $code,
            'label' => $label,
            'nominal' => round($nominal, 1),
        ],
    ];
}

function chestTypeLabel(string $type): string
{
    $map = [
        TreasureService::CHEST_TYPE_MATCH => 'Сундук за матч',
        TreasureService::CHEST_TYPE_SHOP_WC26 => 'Сундук из лавки (ЧМ-26)',
        TreasureService::CHEST_TYPE_WC26_ACHIEVEMENT => 'Сундук ачивки ЧМ-26',
        TreasureService::CHEST_TYPE_LEVEL => 'Сундук за уровень',
        TreasureService::CHEST_TYPE_ACHIEVEMENT => 'Сундук за ачивку',
        TreasureService::CHEST_TYPE_PROFESSION => 'Сундук профессии',
    ];

    return $map[$type] ?? $type;
}
