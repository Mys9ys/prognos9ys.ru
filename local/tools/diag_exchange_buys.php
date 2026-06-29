<?php
declare(strict_types=1);

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../..');
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Prognos9ys\Main\Model\Repository\GameEconomyRepository;
use Prognos9ys\Main\Service\Game\ExchangeBuyAchievementConfig;

$repo = new GameEconomyRepository();
$dataClass = $repo->getExchangeTradeDataClass();

$byBuyer = [];
$byStat = ExchangeBuyAchievementConfig::emptyStatsTemplate();
$totalQty = 0;
$totalTrades = 0;

$response = $dataClass::getList([
    'select' => [
        'UF_BUYER_ID',
        'UF_ITEM_KIND',
        'UF_ITEM_CODE',
        'UF_ITEM_CATEGORY',
        'UF_QTY',
        'UF_TOTAL_PRICE',
        'UF_CREATED_AT',
    ],
    'order' => ['ID' => 'ASC'],
]);

while ($row = $response->fetch()) {
    $buyerId = (int)($row['UF_BUYER_ID'] ?? 0);
    $qty = (int)($row['UF_QTY'] ?? 0);
    if ($buyerId <= 0 || $qty <= 0) {
        continue;
    }

    $statKey = ExchangeBuyAchievementConfig::resolveBuyStatKey(
        (string)($row['UF_ITEM_KIND'] ?? ''),
        (string)($row['UF_ITEM_CODE'] ?? ''),
        (string)($row['UF_ITEM_CATEGORY'] ?? '')
    );
    if ($statKey === '') {
        continue;
    }

    $totalTrades++;
    $totalQty += $qty;
    $byStat[$statKey] = ($byStat[$statKey] ?? 0) + $qty;

    if (!isset($byBuyer[$buyerId])) {
        $byBuyer[$buyerId] = ExchangeBuyAchievementConfig::emptyStatsTemplate();
    }
    $byBuyer[$buyerId][$statKey] = ($byBuyer[$buyerId][$statKey] ?? 0) + $qty;
}

echo "Exchange buy stats (for achievement tuning)\n";
echo "==========================================\n";
echo "Trades: {$totalTrades}\n";
echo "Units bought: {$totalQty}\n";
echo 'Buyers: ' . count($byBuyer) . "\n\n";

echo "By stat key (all users):\n";
foreach ($byStat as $key => $qty) {
    if ($qty <= 0) {
        continue;
    }
    echo sprintf("  %-32s %6d\n", $key, $qty);
}

echo "\nTop buyers per stat:\n";
foreach ($byStat as $statKey => $total) {
    if ($total <= 0) {
        continue;
    }

    $rows = [];
    foreach ($byBuyer as $userId => $stats) {
        $q = (int)($stats[$statKey] ?? 0);
        if ($q > 0) {
            $rows[$userId] = $q;
        }
    }
    arsort($rows);
    $top = array_slice($rows, 0, 5, true);
    echo "\n{$statKey}:\n";
    foreach ($top as $userId => $qty) {
        echo "  user {$userId}: {$qty}\n";
    }
}
