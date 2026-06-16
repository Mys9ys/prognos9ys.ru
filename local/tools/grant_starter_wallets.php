<?php
declare(strict_types=1);

/**
 * Стартовый пакет 100/1 для пользователей без registration_bonus.
 * Для аккаунтов, созданных вручную в админке Bitrix.
 *
 *   php grant_starter_wallets.php --dry-run
 *   php grant_starter_wallets.php --confirm
 *   php grant_starter_wallets.php --confirm 12345   # один userId
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
use Prognos9ys\Main\Service\Game\WalletService;

if (!\Bitrix\Main\Loader::includeModule('prognos9ys.main')) {
    echo "prognos9ys.main module not loaded\n";
    exit(1);
}

$dryRun = in_array('--dry-run', $argv ?? [], true);
$confirm = in_array('--confirm', $argv ?? [], true);
$targetUserId = 0;

foreach ($argv ?? [] as $arg) {
    if (is_numeric($arg) && (int)$arg > 0) {
        $targetUserId = (int)$arg;
    }
}

if (!$dryRun && !$confirm) {
    echo "Grant starter wallets (100/1) to users without registration_bonus.\n";
    echo "Dry run:  php grant_starter_wallets.php --dry-run\n";
    echo "Execute:  php grant_starter_wallets.php --confirm\n";
    echo "One user: php grant_starter_wallets.php --confirm 12345\n";
    exit(1);
}

$walletService = new WalletService();
$repository = new GameEconomyRepository();
$starterP = GameEconomyConfig::START_PROGNOBAKS;
$starterR = GameEconomyConfig::START_RUBLIUS;

echo ($dryRun ? '[DRY RUN] ' : '') . "Grant starter wallets {$starterP}/{$starterR}\n\n";

if ($targetUserId > 0) {
    $user = UserTable::getById($targetUserId)->fetch();
    if (!$user) {
        echo "User {$targetUserId} not found\n";
        exit(1);
    }

    if (!isEligibleForStarter($targetUserId, $repository)) {
        echo "Skip user {$targetUserId} (already has bonus or balance)\n";
        exit(0);
    }

    if ($dryRun) {
        echo "Would grant user {$targetUserId}\n";
        exit(0);
    }

    echo $walletService->grantStarterPackIfMissing($targetUserId)
        ? "Granted user {$targetUserId}\n"
        : "Skip user {$targetUserId}\n";
    exit(0);
}

$eligible = [];
$skipped = 0;
$lastId = 0;

while (true) {
    $rows = UserTable::getList([
        'select' => ['ID', 'LOGIN', 'NAME', 'LAST_NAME'],
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

        if (!isEligibleForStarter($userId, $repository)) {
            $skipped++;
            continue;
        }

        $name = trim((string)($row['LOGIN'] ?? '') . ' ' . (string)($row['NAME'] ?? ''));
        $eligible[] = ['id' => $userId, 'name' => $name];
    }
}

echo 'Eligible: ' . count($eligible) . "\n";
echo "Skipped (already have bonus or balance): {$skipped}\n\n";

foreach ($eligible as $user) {
    echo "  #{$user['id']} {$user['name']}\n";
}

if ($dryRun || !$eligible) {
    echo $dryRun ? "\nRun with --confirm to apply.\n" : "\nNothing to grant.\n";
    exit(0);
}

$granted = 0;

foreach ($eligible as $user) {
    if ($walletService->grantStarterPackIfMissing((int)$user['id'])) {
        $granted++;
    }
}

echo "\nGranted: {$granted}\n";

function isEligibleForStarter(int $userId, GameEconomyRepository $repository): bool
{
    if ($repository->hasWalletTx($userId, 'registration_bonus', 'user', $userId)) {
        return false;
    }

    $wallet = $repository->getWalletByUserId($userId);
    if (!$wallet) {
        return true;
    }

    $prognobaks = round((float)($wallet['UF_PROGNOBAKS'] ?? 0), 1);
    $rublius = round((float)($wallet['UF_RUBLIUS'] ?? 0), 1);

    return $prognobaks === 0.0 && $rublius === 0.0;
}
