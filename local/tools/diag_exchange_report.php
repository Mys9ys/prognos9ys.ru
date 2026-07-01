<?php
declare(strict_types=1);

/**
 * Отчёт по бирже за период (сделки, лоты, ликвидность).
 *
 *   php local/tools/diag_exchange_report.php
 *   php local/tools/diag_exchange_report.php --hours=24
 *   php local/tools/diag_exchange_report.php --since=2026-06-29
 */

$docRoot = dirname(__DIR__, 2);
$_SERVER['DOCUMENT_ROOT'] = $docRoot;
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['SERVER_NAME'] = $_SERVER['SERVER_NAME'] ?? 'localhost';

define('NO_KEEP_STATISTIC', true);
define('NO_AGENT_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

require_once $docRoot . '/bitrix/modules/main/include/prolog_before.php';

if (!\Bitrix\Main\Loader::includeModule('prognos9ys.main')) {
    echo "module prognos9ys.main not loaded\n";
    exit(1);
}

use Bitrix\Main\Type\DateTime;
use Prognos9ys\Main\Model\Repository\GameEconomyRepository;
use Prognos9ys\Main\Service\Game\ExchangeConfig;
use Prognos9ys\Main\Service\Game\MacroEconomyService;

$hours = 24;
$sinceDate = null;

foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--hours=(\d+)$/', $arg, $m)) {
        $hours = max(1, (int)$m[1]);
    } elseif (preg_match('/^--since=(\d{4}-\d{2}-\d{2})$/', $arg, $m)) {
        $sinceDate = $m[1];
    }
}

if ($sinceDate !== null) {
    $cutoff = new DateTime($sinceDate . ' 00:00:00', 'Y-m-d H:i:s');
} else {
    $cutoff = DateTime::createFromTimestamp(time() - $hours * 3600);
}

$repo = new GameEconomyRepository();
$tradeClass = $repo->getExchangeTradeDataClass();
$listingClass = $repo->getExchangeListingDataClass();

function dtTs($value): int
{
    if ($value instanceof DateTime) {
        return $value->getTimestamp();
    }

    return 0;
}

function skuLabel(array $row): string
{
    $kind = (string)($row['UF_ITEM_KIND'] ?? $row['kind'] ?? '');
    $code = (string)($row['UF_ITEM_CODE'] ?? $row['code'] ?? '');
    $category = (string)($row['UF_ITEM_CATEGORY'] ?? $row['category'] ?? '');
    $team = (string)($row['UF_TEAM_CODE'] ?? $row['team_code'] ?? '');

    $label = $kind . ':' . $code;
    if ($category !== '') {
        $label .= '/' . $category;
    }
    if ($team !== '') {
        $label .= '@' . $team;
    }

    return $label;
}

// --- Trades in period ---
$tradeStats = [
    'trades' => 0,
    'units' => 0,
    'volume' => 0.0,
    'commission' => 0.0,
    'seller_net' => 0.0,
    'buyers' => [],
    'sellers' => [],
    'by_sku' => [],
    'by_hour' => [],
];

$tradeResponse = $tradeClass::getList([
    'select' => [
        'UF_BUYER_ID',
        'UF_SELLER_ID',
        'UF_ITEM_KIND',
        'UF_ITEM_CODE',
        'UF_ITEM_CATEGORY',
        'UF_TEAM_CODE',
        'UF_QTY',
        'UF_TOTAL_PRICE',
        'UF_COMMISSION',
        'UF_SELLER_NET',
        'UF_PRICE_PER_UNIT',
        'UF_CREATED_AT',
    ],
    'order' => ['ID' => 'ASC'],
]);

while ($row = $tradeResponse->fetch()) {
    $createdTs = dtTs($row['UF_CREATED_AT'] ?? null);
    if ($createdTs < $cutoff->getTimestamp()) {
        continue;
    }

    $qty = (int)($row['UF_QTY'] ?? 0);
    if ($qty <= 0) {
        continue;
    }

    $tradeStats['trades']++;
    $tradeStats['units'] += $qty;
    $tradeStats['volume'] = round($tradeStats['volume'] + (float)($row['UF_TOTAL_PRICE'] ?? 0), 1);
    $tradeStats['commission'] = round($tradeStats['commission'] + (float)($row['UF_COMMISSION'] ?? 0), 1);
    $tradeStats['seller_net'] = round($tradeStats['seller_net'] + (float)($row['UF_SELLER_NET'] ?? 0), 1);

    $buyerId = (int)($row['UF_BUYER_ID'] ?? 0);
    $sellerId = (int)($row['UF_SELLER_ID'] ?? 0);
    if ($buyerId > 0) {
        $tradeStats['buyers'][$buyerId] = ($tradeStats['buyers'][$buyerId] ?? 0) + $qty;
    }
    if ($sellerId > 0) {
        $tradeStats['sellers'][$sellerId] = ($tradeStats['sellers'][$sellerId] ?? 0) + $qty;
    }

    $sku = skuLabel($row);
    if (!isset($tradeStats['by_sku'][$sku])) {
        $tradeStats['by_sku'][$sku] = ['qty' => 0, 'volume' => 0.0, 'trades' => 0];
    }
    $tradeStats['by_sku'][$sku]['qty'] += $qty;
    $tradeStats['by_sku'][$sku]['volume'] = round(
        $tradeStats['by_sku'][$sku]['volume'] + (float)($row['UF_TOTAL_PRICE'] ?? 0),
        1
    );
    $tradeStats['by_sku'][$sku]['trades']++;

    $hourKey = date('Y-m-d H:00', $createdTs);
    if (!isset($tradeStats['by_hour'][$hourKey])) {
        $tradeStats['by_hour'][$hourKey] = 0;
    }
    $tradeStats['by_hour'][$hourKey]++;
}

uasort($tradeStats['by_sku'], static fn(array $a, array $b): int => $b['qty'] <=> $a['qty']);

// --- Listings created in period ---
$listingCreated = [
    'total' => 0,
    'by_status' => [],
    'by_sku' => [],
    'consignment' => 0,
    'bank' => 0,
    'user' => 0,
];

$listingResponse = $listingClass::getList([
    'select' => [
        'UF_STATUS',
        'UF_ITEM_KIND',
        'UF_ITEM_CODE',
        'UF_ITEM_CATEGORY',
        'UF_TEAM_CODE',
        'UF_QTY_TOTAL',
        'UF_QTY_REMAINING',
        'UF_PRICE_PER_UNIT',
        'UF_NOMINAL_SNAPSHOT',
        'UF_SELLER_BANK_ID',
        'UF_CONSIGNMENT_ID',
        'UF_CREATED_AT',
    ],
    'order' => ['ID' => 'ASC'],
]);

$priceVsNominal = [];

while ($row = $listingResponse->fetch()) {
    $createdTs = dtTs($row['UF_CREATED_AT'] ?? null);
    if ($createdTs < $cutoff->getTimestamp()) {
        continue;
    }

    $listingCreated['total']++;
    $status = (string)($row['UF_STATUS'] ?? 'unknown');
    $listingCreated['by_status'][$status] = ($listingCreated['by_status'][$status] ?? 0) + 1;

    if ((int)($row['UF_CONSIGNMENT_ID'] ?? 0) > 0) {
        $listingCreated['consignment']++;
    }
    if ((int)($row['UF_SELLER_BANK_ID'] ?? 0) > 0) {
        $listingCreated['bank']++;
    } else {
        $listingCreated['user']++;
    }

    $sku = skuLabel($row);
    $listingCreated['by_sku'][$sku] = ($listingCreated['by_sku'][$sku] ?? 0) + 1;

    $nominal = (float)($row['UF_NOMINAL_SNAPSHOT'] ?? 0);
    $price = (float)($row['UF_PRICE_PER_UNIT'] ?? 0);
    if ($nominal > 0 && $price > 0) {
        $kind = (string)($row['UF_ITEM_KIND'] ?? '');
        if (!isset($priceVsNominal[$kind])) {
            $priceVsNominal[$kind] = ['sum_ratio' => 0.0, 'count' => 0];
        }
        $priceVsNominal[$kind]['sum_ratio'] += $price / $nominal;
        $priceVsNominal[$kind]['count']++;
    }
}

arsort($listingCreated['by_sku']);

// --- Output ---
echo "Exchange report\n";
echo "===============\n";
echo 'Since:    ' . $cutoff->format('d.m.Y H:i:s') . "\n";
echo 'Now:      ' . date('d.m.Y H:i:s') . "\n\n";

echo "=== Trades (period) ===\n";
echo 'Trades:       ' . $tradeStats['trades'] . "\n";
echo 'Units:        ' . $tradeStats['units'] . "\n";
echo 'Volume:       ' . $tradeStats['volume'] . " 🪙\n";
echo 'Commission:   ' . $tradeStats['commission'] . " 🪙\n";
echo 'Seller net:   ' . $tradeStats['seller_net'] . " 🪙\n";
echo 'Buyers:       ' . count($tradeStats['buyers']) . "\n";
echo 'Sellers:      ' . count($tradeStats['sellers']) . "\n\n";

if ($tradeStats['by_hour']) {
    echo "Trades by hour:\n";
    ksort($tradeStats['by_hour']);
    foreach ($tradeStats['by_hour'] as $hour => $count) {
        echo "  {$hour}  {$count}\n";
    }
    echo "\n";
}

echo "Top SKUs by traded qty:\n";
$i = 0;
foreach ($tradeStats['by_sku'] as $sku => $info) {
    if (++$i > 15) {
        break;
    }
    echo sprintf(
        "  %-40s qty=%4d  vol=%7.1f  trades=%d\n",
        $sku,
        $info['qty'],
        $info['volume'],
        $info['trades']
    );
}
echo "\n";

echo "=== Listings created (period) ===\n";
echo 'New listings: ' . $listingCreated['total'] . "\n";
echo '  user:        ' . $listingCreated['user'] . "\n";
echo '  bank:        ' . $listingCreated['bank'] . "\n";
echo '  consignment: ' . $listingCreated['consignment'] . "\n";
echo "By status:\n";
foreach ($listingCreated['by_status'] as $status => $count) {
    echo "  {$status}: {$count}\n";
}
echo "\nTop listed SKUs (new):\n";
$i = 0;
foreach ($listingCreated['by_sku'] as $sku => $count) {
    if (++$i > 12) {
        break;
    }
    echo "  {$sku}: {$count}\n";
}
echo "\n";

if ($priceVsNominal) {
    echo "Avg price/nominal (new listings):\n";
    foreach ($priceVsNominal as $kind => $data) {
        if ($data['count'] <= 0) {
            continue;
        }
        $ratio = round($data['sum_ratio'] / $data['count'], 3);
        echo sprintf("  %-16s x%.3f (%d lots)\n", $kind, $ratio, $data['count']);
    }
    echo "\n";
}

$macro = (new MacroEconomyService())->getSummary();
$market = $macro['exchange'] ?? [];

echo "=== Market now (all active) ===\n";
echo json_encode([
    'active_listings' => $market['active_listings'] ?? 0,
    'qty_on_sale' => $market['qty_on_sale'] ?? 0,
    'nominal_total' => $market['nominal_total'] ?? 0,
    'ask_total' => $market['ask_total'] ?? 0,
    'unique_sellers' => $market['unique_sellers'] ?? 0,
    'by_bucket' => $market['by_bucket'] ?? [],
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n";

echo "=== Hints ===\n";
if ($tradeStats['trades'] === 0 && $listingCreated['total'] > 0) {
    echo "- Есть лоты, но нет сделок: проверить цены (все на потолке?), пустые вкладки, UX покупки.\n";
}
foreach ($priceVsNominal as $kind => $data) {
    if ($data['count'] < 3) {
        continue;
    }
    $ratio = $data['sum_ratio'] / $data['count'];
    if ($ratio >= 1.025) {
        echo "- {$kind}: средняя цена близка к max (+3%) — возможен застой.\n";
    }
}
if (($listingCreated['by_status'][ExchangeConfig::STATUS_CANCELLED] ?? 0) > 5) {
    echo "- Много отменённых лотов — возможно, игроки тестируют или цены не устраивают.\n";
}
if ($tradeStats['trades'] > 0 && count($tradeStats['buyers']) <= 2) {
    echo "- Сделки у 1–2 покупателей — ликвидность узкая, смотреть номиналы и комиссию.\n";
}

echo "\nAlso: php local/tools/diag_exchange_purchase.php survey\n";
