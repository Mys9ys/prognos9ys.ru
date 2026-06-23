<?php
declare(strict_types=1);

/**
 * Откат наград за ачивки (claims + рублиусы + сундуки/вымпелы из ачивок).
 *
 *   php reset_achievement_rewards.php --dry-run
 *   php reset_achievement_rewards.php --confirm
 *   php reset_achievement_rewards.php --confirm --from 2 --to 30
 */

$docRoot = dirname(__DIR__, 2);
$_SERVER['DOCUMENT_ROOT'] = $docRoot;
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['SERVER_NAME'] = $_SERVER['SERVER_NAME'] ?? 'localhost';

define('NO_KEEP_STATISTIC', true);
define('NO_AGENT_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

require_once $docRoot . '/bitrix/modules/main/include/prolog_before.php';

use Prognos9ys\Main\Model\Repository\GameEconomyRepository;
use Prognos9ys\Main\Service\Game\GameEconomyConfig;
use Prognos9ys\Main\Service\Game\TreasureService;

if (!\Bitrix\Main\Loader::includeModule('prognos9ys.main')) {
    echo "prognos9ys.main module not loaded\n";
    exit(1);
}

$dryRun = in_array('--dry-run', $argv ?? [], true);
$confirm = in_array('--confirm', $argv ?? [], true);
$from = 0;
$to = 0;

for ($i = 1; $i < $argc; $i++) {
    $arg = $argv[$i];
    if ($arg === '--from' && isset($argv[$i + 1])) {
        $from = (int)$argv[++$i];
        continue;
    }
    if ($arg === '--to' && isset($argv[$i + 1])) {
        $to = (int)$argv[++$i];
        continue;
    }
}

if (!$dryRun && !$confirm) {
    echo "Откат наград за ачивки.\n";
    echo "  php reset_achievement_rewards.php --dry-run\n";
    echo "  php reset_achievement_rewards.php --confirm [--from N --to M]\n";
    exit(1);
}

$repository = new GameEconomyRepository();
$claimClass = $repository->getAchievementClaimDataClass();
$txClass = $repository->getWalletTxDataClass();
$chestClass = $repository->getTreasureChestDataClass();

$userFilter = static function (int $userId) use ($from, $to): bool {
    if ($userId <= 0) {
        return false;
    }
    if ($from > 0 && $userId < $from) {
        return false;
    }
    if ($to > 0 && $userId > $to) {
        return false;
    }

    return true;
};

$isAchievementTx = static function (array $row): bool {
    $reason = (string)($row['UF_REASON'] ?? '');
    $refType = (string)($row['UF_REF_TYPE'] ?? '');

    return $refType === 'achievement' || strpos($reason, 'achievement_') === 0;
};

$stats = [
    'claims' => 0,
    'wallet_tx' => 0,
    'rublius_back' => 0.0,
    'chests' => 0,
    'pennants' => 0,
    'wallets_adjusted' => 0,
];

/** @var array<int, float> */
$rubliusByUser = [];

/** @var list<int> */
$claimIds = [];
/** @var list<int> */
$txIds = [];
/** @var list<int> */
$chestIds = [];

$claimRs = $claimClass::getList(['select' => ['ID', 'UF_USER_ID']]);
while ($row = $claimRs->fetch()) {
    $userId = (int)($row['UF_USER_ID'] ?? 0);
    if (!$userFilter($userId)) {
        continue;
    }
    $claimIds[] = (int)$row['ID'];
    $stats['claims']++;
}

$txRs = $txClass::getList([
    'select' => ['ID', 'UF_USER_ID', 'UF_CURRENCY', 'UF_AMOUNT', 'UF_REASON', 'UF_REF_TYPE'],
]);
while ($row = $txRs->fetch()) {
    if (!$isAchievementTx($row)) {
        continue;
    }
    $userId = (int)($row['UF_USER_ID'] ?? 0);
    if (!$userFilter($userId)) {
        continue;
    }

    $txIds[] = (int)$row['ID'];
    $stats['wallet_tx']++;

    if ((string)($row['UF_CURRENCY'] ?? '') === GameEconomyConfig::CURRENCY_RUBLIUS) {
        $amount = (float)($row['UF_AMOUNT'] ?? 0);
        $stats['rublius_back'] += $amount;
        $rubliusByUser[$userId] = ($rubliusByUser[$userId] ?? 0.0) + $amount;
    }
}

$chestRs = $chestClass::getList(['select' => ['ID', 'UF_USER_ID', 'UF_TYPE']]);
while ($row = $chestRs->fetch()) {
    $userId = (int)($row['UF_USER_ID'] ?? 0);
    if (!$userFilter($userId)) {
        continue;
    }

    $type = (string)($row['UF_TYPE'] ?? '');
    if ($type === TreasureService::CHEST_TYPE_ACHIEVEMENT) {
        $chestIds[] = (int)$row['ID'];
        $stats['chests']++;
        continue;
    }
    if ($type === TreasureService::CHEST_TYPE_PENNANT) {
        $chestIds[] = (int)$row['ID'];
        $stats['pennants']++;
    }
}

$rangeLabel = ($from > 0 || $to > 0) ? "users {$from}..{$to}" : 'all users';
echo ($dryRun ? '[DRY RUN] ' : '') . "Reset achievement rewards ({$rangeLabel})\n\n";
echo "  claims to delete:     {$stats['claims']}\n";
echo "  wallet_tx to delete:  {$stats['wallet_tx']}\n";
echo "  rublius to subtract:  " . round($stats['rublius_back'], 1) . "\n";
echo "  achievement chests:   {$stats['chests']}\n";
echo "  pennants:             {$stats['pennants']}\n\n";

if ($dryRun) {
    echo "Done (dry run).\n";
    exit(0);
}

foreach ($claimIds as $id) {
    $claimClass::delete($id);
}
foreach ($txIds as $id) {
    $txClass::delete($id);
}
foreach ($chestIds as $id) {
    $chestClass::delete($id);
}

foreach ($rubliusByUser as $userId => $subtract) {
    $wallet = $repository->getWalletByUserId($userId);
    if (!$wallet) {
        continue;
    }

    $rublius = round((float)($wallet['UF_RUBLIUS'] ?? 0) - $subtract, 1);
    if ($rublius < 0) {
        $rublius = 0.0;
    }

    $repository->updateWallet((int)$wallet['ID'], [
        'UF_RUBLIUS' => $rublius,
    ]);
    $stats['wallets_adjusted']++;
    echo "  user {$userId}: rublius -" . round($subtract, 1) . " => {$rublius}\n";
}

echo "\nWallets adjusted: {$stats['wallets_adjusted']}\n";
echo "Done.\n";
