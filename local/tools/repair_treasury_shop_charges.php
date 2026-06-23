<?php
declare(strict_types=1);

/**
 * Починка лавки казны: дубли кошельков + пропущенные списания рублиусов/прогнобаксов.
 *
 * Usage:
 *   php repair_treasury_shop_charges.php           # dry-run
 *   php repair_treasury_shop_charges.php --apply # списать по факту
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

use Prognos9ys\Main\Model\Repository\GameEconomyRepository;
use Prognos9ys\Main\Service\Game\TreasuryShopService;
use Prognos9ys\Main\Service\Game\WalletService;

$apply = in_array('--apply', $argv ?? [], true);
$repo = new GameEconomyRepository();
$walletService = new WalletService($repo);

echo 'Mode: ' . ($apply ? 'APPLY' : 'DRY-RUN') . "\n\n";

echo "=== Duplicate wallets ===\n";
$mergedUsers = 0;
$seenUsers = [];
$dataClass = $repo->getWalletDataClass();
$rs = $dataClass::getList(['select' => ['UF_USER_ID'], 'order' => ['UF_USER_ID' => 'ASC']]);
while ($row = $rs->fetch()) {
    $userId = (int)($row['UF_USER_ID'] ?? 0);
    if ($userId <= 0 || isset($seenUsers[$userId])) {
        continue;
    }
    $seenUsers[$userId] = true;

    $rows = $repo->getWalletRowsByUserId($userId);
    if (count($rows) <= 1) {
        continue;
    }

    echo "user #{$userId}: " . count($rows) . " wallet rows\n";
    if ($apply) {
        $repo->mergeWalletDuplicatesForUser($userId, $rows);
        $wallet = $walletService->getWalletSummary($userId);
        echo "  merged → p={$wallet['prognobaks']} r={$wallet['rublius']}\n";
    }
    $mergedUsers++;
}

echo $mergedUsers ? "\n" : "none\n";

echo "\n=== Missing shop charges ===\n";
$repair = (new TreasuryShopService($repo))->repairMissingShopCharges(!$apply);
echo 'to fix: ' . $repair['fixed'] . "\n";
echo 'already ok: ' . $repair['skipped'] . "\n";

if ($repair['errors']) {
    echo "errors:\n";
    foreach ($repair['errors'] as $error) {
        echo "  - {$error}\n";
    }
}

if (!$apply) {
    echo "\nRun with --apply to execute fixes.\n";
}

echo "\nDone.\n";
