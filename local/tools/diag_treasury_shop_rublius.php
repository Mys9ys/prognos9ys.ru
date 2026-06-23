<?php
declare(strict_types=1);

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

use Prognos9ys\Main\Model\Repository\GameEconomyRepository;
use Prognos9ys\Main\Service\Game\GameEconomyConfig;
use Prognos9ys\Main\Service\Game\WalletService;

$repo = new GameEconomyRepository();
$waveDc = $repo->getTreasuryShopWaveDataClass();
$txDc = $repo->getWalletTxDataClass();

echo "=== Duplicate wallets ===\n";
$dataClass = $repo->getWalletDataClass();
$byUser = [];
$rs = $dataClass::getList(['select' => ['ID', 'UF_USER_ID', 'UF_PROGNOBAKS', 'UF_RUBLIUS'], 'order' => ['UF_USER_ID' => 'ASC', 'ID' => 'ASC']]);
while ($row = $rs->fetch()) {
    $uid = (int)($row['UF_USER_ID'] ?? 0);
    if ($uid <= 0) {
        continue;
    }
    $byUser[$uid][] = $row;
}
foreach ($byUser as $uid => $rows) {
    if (count($rows) > 1) {
        echo "user {$uid}: " . count($rows) . " wallet rows\n";
        foreach ($rows as $r) {
            echo "  id={$r['ID']} p={$r['UF_PROGNOBAKS']} r={$r['UF_RUBLIUS']}\n";
        }
    }
}

echo "\n=== Waves: bought but no wallet tx ===\n";
$rs = $waveDc::getList(['select' => ['*'], 'order' => ['ID' => 'ASC']]);
while ($wave = $rs->fetch()) {
    $userId = (int)($wave['UF_USER_ID'] ?? 0);
    $waveId = (int)($wave['ID'] ?? 0);
    $milestone = (int)($wave['UF_MILESTONE'] ?? 0);
    $rBought = in_array($wave['UF_RUBLIUS_BOUGHT'] ?? false, [true, 1, '1', 'Y'], true);
    $pBought = in_array($wave['UF_PROGNOBAKS_BOUGHT'] ?? false, [true, 1, '1', 'Y'], true);
    $premiumBought = in_array($wave['UF_PREMIUM_BOUGHT'] ?? false, [true, 1, '1', 'Y'], true);

    $issues = [];
    if ($rBought && !$repo->hasWalletTx($userId, 'treasury_shop_chest', 'treasury_shop_wave', $waveId, GameEconomyConfig::CURRENCY_RUBLIUS)) {
        $issues[] = 'rublius_chest_no_tx';
    }
    if ($pBought && !$repo->hasWalletTx($userId, 'treasury_shop_chest', 'treasury_shop_wave', $waveId, GameEconomyConfig::CURRENCY_PROGNOBAKS)) {
        $issues[] = 'prognobaks_chest_no_tx';
    }
    if ($premiumBought && !$repo->hasWalletTx($userId, 'treasury_shop_premium', 'treasury_shop_wave', $waveId, GameEconomyConfig::CURRENCY_RUBLIUS)) {
        $issues[] = 'premium_no_tx';
    }

    if (!$issues) {
        continue;
    }

    $wallet = (new WalletService($repo))->getWalletSummary($userId);
    echo "user={$userId} wave={$waveId} milestone={$milestone} rublius={$wallet['rublius']} issues=" . implode(',', $issues) . "\n";

    $txRs = $txDc::getList([
        'filter' => [
            '=UF_USER_ID' => $userId,
            '=UF_CURRENCY' => GameEconomyConfig::CURRENCY_RUBLIUS,
        ],
        'order' => ['ID' => 'ASC'],
        'select' => ['UF_REASON', 'UF_AMOUNT', 'UF_BALANCE_AFTER', 'UF_REF_TYPE', 'UF_REF_ID'],
    ]);
    while ($tx = $txRs->fetch()) {
        echo "  tx: {$tx['UF_REASON']} amount={$tx['UF_AMOUNT']} after={$tx['UF_BALANCE_AFTER']} ref={$tx['UF_REF_TYPE']}#{$tx['UF_REF_ID']}\n";
    }
}

echo "\nDone.\n";
