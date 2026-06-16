<?php
declare(strict_types=1);

/**
 * Обнулить накопленный опыт у всех пользователей.
 * Кошельки и ставки не трогает.
 *
 *   php reset_user_xp.php --dry-run
 *   php reset_user_xp.php --confirm
 *
 * Делает:
 *  - UF_XP = 0 в user_progress
 *  - все claimed pending_xp → pending (можно снова забрать за матчи)
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

if (!\Bitrix\Main\Loader::includeModule('prognos9ys.main')) {
    echo "prognos9ys.main module not loaded\n";
    exit(1);
}

$dryRun = in_array('--dry-run', $argv ?? [], true);
$confirm = in_array('--confirm', $argv ?? [], true);

if (!$dryRun && !$confirm) {
    echo "Resets accumulated XP for all users (wallets unchanged).\n";
    echo "Dry run:  php reset_user_xp.php --dry-run\n";
    echo "Execute:  php reset_user_xp.php --confirm\n";
    exit(1);
}

$repository = new GameEconomyRepository();

$progressDataClass = $repository->getUserProgressDataClass();
$pendingDataClass = $repository->getPendingXpDataClass();

$withXp = 0;
$progressRows = 0;
$pendingTotal = 0;
$pendingClaimed = 0;
$pendingOpen = 0;

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

echo ($dryRun ? '[DRY RUN] ' : '') . "Reset user XP\n\n";
echo "user_progress rows: {$progressRows}\n";
echo "  with XP > 0: {$withXp}\n";
echo "pending_xp rows: {$pendingTotal}\n";
echo "  claimed (will reopen): {$pendingClaimed}\n";
echo "  already pending: {$pendingOpen}\n\n";

if ($dryRun) {
    echo "Run with --confirm to apply.\n";
    exit(0);
}

$resetXp = $repository->resetAllUserProgressXp();
$reopened = $repository->reopenClaimedPendingXp();

echo "Done.\n";
echo "  progress zeroed: {$resetXp}\n";
echo "  pending reopened: {$reopened}\n";
