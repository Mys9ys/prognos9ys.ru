<?php
declare(strict_types=1);

/**
 * Обнулить опыт и вернуть стартовые кошельки всем активным пользователям.
 *
 *   php reset_user_xp.php --dry-run
 *   php reset_user_xp.php --confirm
 *
 * Делает:
 *  - UF_XP = 0 в user_progress
 *  - все claimed pending_xp → pending (можно снова забрать за матчи)
 *  - кошельки: 100 💵 / 1 💎 (ставки и wallet_tx не трогает)
 */

$docRoot = dirname(__DIR__, 2);
$_SERVER['DOCUMENT_ROOT'] = $docRoot;
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['SERVER_NAME'] = $_SERVER['SERVER_NAME'] ?? 'localhost';

define('NO_KEEP_STATISTIC', true);
define('NO_AGENT_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

require_once $docRoot . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\UserTable;
use Prognos9ys\Main\Model\Repository\GameEconomyRepository;
use Prognos9ys\Main\Service\Game\GameEconomyConfig;

if (!\Bitrix\Main\Loader::includeModule('prognos9ys.main')) {
    echo "prognos9ys.main module not loaded\n";
    exit(1);
}

$dryRun = in_array('--dry-run', $argv ?? [], true);
$confirm = in_array('--confirm', $argv ?? [], true);

if (!$dryRun && !$confirm) {
    echo "Resets XP and sets starter wallets 100/1 for active users.\n";
    echo "Dry run:  php reset_user_xp.php --dry-run\n";
    echo "Execute:  php reset_user_xp.php --confirm\n";
    exit(1);
}

$repository = new GameEconomyRepository();
$starterP = GameEconomyConfig::START_PROGNOBAKS;
$starterR = GameEconomyConfig::START_RUBLIUS;

$progressDataClass = $repository->getUserProgressDataClass();
$pendingDataClass = $repository->getPendingXpDataClass();
$walletDataClass = $repository->getWalletDataClass();

$withXp = 0;
$progressRows = 0;
$pendingTotal = 0;
$pendingClaimed = 0;
$pendingOpen = 0;
$walletRows = 0;
$walletsNotStarter = 0;
$activeUsers = 0;

$rs = $progressDataClass::getList(['select' => ['ID', 'UF_XP']]);
while ($row = $rs->fetch()) {
    $progressRows++;
    if (round((float)($row['UF_XP'] ?? 0), 1) > 0) {
        $withXp++;
    }
}

$rs = $pendingDataClass::getList(['select' => ['ID', 'UF_STATUS']]);
while ($row = $rs->fetch()) {
    $pendingTotal++;
    if ((string)($row['UF_STATUS'] ?? '') === GameEconomyConfig::XP_STATUS_CLAIMED) {
        $pendingClaimed++;
    } else {
        $pendingOpen++;
    }
}

$rs = $walletDataClass::getList(['select' => ['ID', 'UF_PROGNOBAKS', 'UF_RUBLIUS']]);
while ($row = $rs->fetch()) {
    $walletRows++;
    $p = round((float)($row['UF_PROGNOBAKS'] ?? 0), 1);
    $r = round((float)($row['UF_RUBLIUS'] ?? 0), 1);
    if ($p !== $starterP || $r !== $starterR) {
        $walletsNotStarter++;
    }
}

$lastId = 0;
while (true) {
    $rows = UserTable::getList([
        'select' => ['ID'],
        'filter' => [
            '>ID' => $lastId,
            '=ACTIVE' => 'Y',
        ],
        'order' => ['ID' => 'ASC'],
        'limit' => 500,
    ])->fetchAll();

    if (!$rows) {
        break;
    }

    foreach ($rows as $row) {
        $userId = (int)($row['ID'] ?? 0);
        $lastId = $userId;
        if ($userId > 0) {
            $activeUsers++;
        }
    }
}

echo ($dryRun ? '[DRY RUN] ' : '') . "Reset user XP and wallets\n\n";
echo "Starter wallet: {$starterP} prognobaks / {$starterR} rublius\n\n";
echo "user_progress rows: {$progressRows}\n";
echo "  with XP > 0: {$withXp}\n";
echo "pending_xp rows: {$pendingTotal}\n";
echo "  claimed (will reopen): {$pendingClaimed}\n";
echo "  already pending: {$pendingOpen}\n";
echo "wallet rows: {$walletRows}\n";
echo "  not at starter balance: {$walletsNotStarter}\n";
echo "active users (wallets ensured): {$activeUsers}\n\n";

if ($dryRun) {
    echo "Run with --confirm to apply.\n";
    exit(0);
}

$resetXp = $repository->resetAllUserProgressXp();
$reopened = $repository->reopenClaimedPendingXp();
$walletsUpdated = $repository->resetAllWalletBalances($starterP, $starterR);
$walletsCreated = 0;

$lastId = 0;
while (true) {
    $rows = UserTable::getList([
        'select' => ['ID'],
        'filter' => [
            '>ID' => $lastId,
            '=ACTIVE' => 'Y',
        ],
        'order' => ['ID' => 'ASC'],
        'limit' => 500,
    ])->fetchAll();

    if (!$rows) {
        break;
    }

    foreach ($rows as $row) {
        $userId = (int)($row['ID'] ?? 0);
        $lastId = $userId;
        if ($userId <= 0) {
            continue;
        }

        if ($repository->getWalletByUserId($userId)) {
            continue;
        }

        $repository->addWallet([
            'UF_USER_ID' => $userId,
            'UF_PROGNOBAKS' => $starterP,
            'UF_RUBLIUS' => $starterR,
        ]);
        $walletsCreated++;
    }
}

echo "Done.\n";
echo "  progress zeroed: {$resetXp}\n";
echo "  pending reopened: {$reopened}\n";
echo "  wallets updated: {$walletsUpdated}\n";
echo "  wallets created: {$walletsCreated}\n";
