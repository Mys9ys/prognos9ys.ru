<?php
declare(strict_types=1);

/**
 * Сценарный тест частных банков (фаза C+).
 *
 *   php test_user_banks.php --dry-run --owner=1 --depositor=2 --borrower=3
 *   php test_user_banks.php --confirm --owner=1 --depositor=2 --borrower=3
 *   php test_user_banks.php --confirm --owner=1 --depositor=2 --borrower=3 --top-up
 *   php test_user_banks.php --confirm --owner=1 --settle-match=12345
 *
 * Без --owner/--depositor/--borrower: ищет трёх активных пользователей с кошельками.
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
use Prognos9ys\Main\Service\Game\BankDepositService;
use Prognos9ys\Main\Service\Game\BankLoanService;
use Prognos9ys\Main\Service\Game\BankSettlementService;
use Prognos9ys\Main\Service\Game\GameEconomyConfig;
use Prognos9ys\Main\Service\Game\GameEconomyHlInstaller;
use Prognos9ys\Main\Service\Game\UserBankService;
use Prognos9ys\Main\Service\Game\WalletService;

if (!\Bitrix\Main\Loader::includeModule('prognos9ys.main')) {
    echo "prognos9ys.main not loaded\n";
    exit(1);
}

$dryRun = in_array('--dry-run', $argv ?? [], true);
$confirm = in_array('--confirm', $argv ?? [], true);
$topUp = in_array('--top-up', $argv ?? [], true);
$installHl = in_array('--install-hl', $argv ?? [], true);

$ownerId = parseArgInt($argv ?? [], '--owner');
$depositorId = parseArgInt($argv ?? [], '--depositor');
$borrowerId = parseArgInt($argv ?? [], '--borrower');
$settleMatchId = parseArgInt($argv ?? [], '--settle-match');
$depositAmount = parseArgFloat($argv ?? [], '--deposit', GameEconomyConfig::DEPOSIT_MIN_AMOUNT_PROGNOBAKS);
$loanAmount = parseArgFloat($argv ?? [], '--loan', GameEconomyConfig::LOAN_MIN_AMOUNT_PROGNOBAKS);

if (!$dryRun && !$confirm) {
    echo "Сценарный тест частных банков\n\n";
    echo "Dry run:  php test_user_banks.php --dry-run [--owner=N --depositor=N --borrower=N]\n";
    echo "Execute:  php test_user_banks.php --confirm [--owner=N --depositor=N --borrower=N]\n";
    echo "Top-up:   добавьте --top-up (доначислить до 250/100/50 на кошельки)\n";
    echo "HL:       --install-hl (идемпотентно создать HL-таблицы)\n";
    echo "Settle:   --settle-match=ID (тик settlement для матча)\n";
    echo "Amounts:  --deposit=100 --loan=50 (по умолчанию минимумы из конфига)\n";
    exit(1);
}

$repo = new GameEconomyRepository();
$wallet = new WalletService($repo);
$bankService = new UserBankService($repo, $wallet);
$depositService = new BankDepositService($repo, $wallet);
$loanService = new BankLoanService($repo, $wallet);

echo ($dryRun ? '[DRY RUN] ' : '') . "test_user_banks\n";
echo 'Limits: deposit min ' . GameEconomyConfig::DEPOSIT_MIN_AMOUNT_PROGNOBAKS
    . ', loan min ' . GameEconomyConfig::LOAN_MIN_AMOUNT_PROGNOBAKS
    . ', term ' . GameEconomyConfig::BANK_TERM_MATCHES . " matches\n\n";

if ($installHl) {
    if ($dryRun) {
        echo "Would run GameEconomyHlInstaller::install()\n";
    } else {
        $hl = (new GameEconomyHlInstaller())->install();
        echo "HL installed:\n";
        foreach ($hl as $k => $v) {
            echo "  {$k}: {$v}\n";
        }
    }
    echo "\n";
}

try {
    $repo->getUserBankDataClass();
    echo "HL user_bank: OK\n";
} catch (\Throwable $e) {
    echo "HL user_bank: MISSING — run with --install-hl\n";
    if (!$dryRun && !$installHl) {
        exit(1);
    }
}

if ($settleMatchId > 0) {
    echo "\n=== settle match {$settleMatchId} ===\n";
    if ($dryRun) {
        echo "Would call BankSettlementService::onMatchSettled({$settleMatchId})\n";
    } else {
        (new BankSettlementService($repo))->onMatchSettled($settleMatchId);
        echo "onMatchSettled done\n";
    }
    exit(0);
}

[$ownerId, $depositorId, $borrowerId] = resolveUsers($ownerId, $depositorId, $borrowerId);

echo "Participants:\n";
printUserLine('owner', $ownerId, $wallet);
printUserLine('depositor', $depositorId, $wallet);
printUserLine('borrower', $borrowerId, $wallet);
echo "\n";

$needOwner = GameEconomyConfig::BANK_OPEN_MIN_WALLET_PROGNOBAKS;
$needDepositor = $depositAmount;
$needBorrower = 0.0;

if ($topUp) {
    topUpIfNeeded($dryRun, $wallet, $ownerId, $needOwner, 'test_bank_topup_owner');
    topUpIfNeeded($dryRun, $wallet, $depositorId, $needDepositor, 'test_bank_topup_depositor');
}

$existingBank = $repo->getUserBankByOwnerId($ownerId);
$bankId = $existingBank ? (int)$existingBank['ID'] : 0;

echo "=== Step 1: open bank (owner {$ownerId}) ===\n";
if ($existingBank) {
    echo "Bank already exists id={$bankId}\n";
} elseif ($dryRun) {
    echo "Would open bank (reserve " . GameEconomyConfig::BANK_RESERVED_CAPITAL_PROGNOBAKS . ")\n";
} else {
    $opened = $bankService->openBank($ownerId);
    $bankId = (int)$opened['id'];
    echo "Opened bank id={$bankId}\n";
}

if ($bankId <= 0 && $dryRun) {
    $bankId = 999999;
    echo "Using fake bankId for dry-run deposit/loan preview\n";
}

echo "\n=== Step 2: deposit {$depositAmount} (depositor {$depositorId} -> bank {$bankId}) ===\n";
$depositRow = null;
foreach ($depositService->getMyContracts($depositorId) as $c) {
    if ((int)$c['bank_id'] === $bankId && $c['status'] !== GameEconomyConfig::CONTRACT_STATUS_CLOSED) {
        $depositRow = $c;
        break;
    }
}
if ($depositRow) {
    echo "Active deposit already exists id={$depositRow['id']}\n";
} elseif ($dryRun) {
    echo "Would create deposit {$depositAmount}\n";
} else {
    $depositRow = $depositService->createDeposit($depositorId, $bankId, $depositAmount);
    echo 'Deposit id=' . $depositRow['id'] . ', matches_left=' . $depositRow['matches_left'] . "\n";
}

echo "\n=== Step 3: loan {$loanAmount} (borrower {$borrowerId} <- bank {$bankId}) ===\n";
$loanRow = null;
foreach ($loanService->getMyContracts($borrowerId) as $c) {
    if ((int)$c['bank_id'] === $bankId && $c['status'] !== GameEconomyConfig::CONTRACT_STATUS_CLOSED) {
        $loanRow = $c;
        break;
    }
}
if ($loanRow) {
    echo "Active loan already exists id={$loanRow['id']}\n";
} elseif ($dryRun) {
    echo "Would create loan {$loanAmount}\n";
} else {
    $loanRow = $loanService->takeLoan($borrowerId, $bankId, $loanAmount);
    echo 'Loan id=' . $loanRow['id'] . ', total_due=' . $loanRow['total_due'] . "\n";
}

echo "\n=== Summary ===\n";
if (!$dryRun) {
    echo json_encode([
        'bank' => $bankService->getMyBank($ownerId),
        'depositor_contracts' => $depositService->getMyContracts($depositorId),
        'borrower_contracts' => $loanService->getMyContracts($borrowerId),
        'wallets' => [
            'owner' => $wallet->getWalletSummary($ownerId),
            'depositor' => $wallet->getWalletSummary($depositorId),
            'borrower' => $wallet->getWalletSummary($borrowerId),
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
}

echo "\nNext: прогнать {$bankId} settlement на 5 матчах ЧМ:\n";
echo "  php local/tools/recalc_matches_range.php 63849 N N+4\n";
echo "или тик одного матча:\n";
echo "  php local/tools/test_user_banks.php --confirm --settle-match=MATCH_ID\n";
echo "диагностика:\n";
echo "  php local/tools/diag_user_banks.php {$ownerId}\n";

function parseArgInt(array $argv, string $prefix): int
{
    foreach ($argv as $arg) {
        if (strpos($arg, $prefix . '=') === 0) {
            return (int)substr($arg, strlen($prefix) + 1);
        }
    }

    return 0;
}

function parseArgFloat(array $argv, string $prefix, float $default): float
{
    foreach ($argv as $arg) {
        if (strpos($arg, $prefix . '=') === 0) {
            return round((float)substr($arg, strlen($prefix) + 1), 1);
        }
    }

    return $default;
}

function resolveUsers(int $ownerId, int $depositorId, int $borrowerId): array
{
    if ($ownerId > 0 && $depositorId > 0 && $borrowerId > 0) {
        if (count(array_unique([$ownerId, $depositorId, $borrowerId])) < 3) {
            throw new \InvalidArgumentException('owner, depositor, borrower must be three different users');
        }

        return [$ownerId, $depositorId, $borrowerId];
    }

    $ids = [];
    $rs = UserTable::getList([
        'filter' => ['=ACTIVE' => 'Y'],
        'select' => ['ID', 'LOGIN'],
        'order' => ['ID' => 'ASC'],
        'limit' => 50,
    ]);
    while ($row = $rs->fetch()) {
        $ids[] = (int)$row['ID'];
        if (count($ids) >= 3) {
            break;
        }
    }

    if (count($ids) < 3) {
        throw new \RuntimeException('Need at least 3 active users; pass --owner --depositor --borrower');
    }

    return [
        $ownerId > 0 ? $ownerId : $ids[0],
        $depositorId > 0 ? $depositorId : $ids[1],
        $borrowerId > 0 ? $borrowerId : $ids[2],
    ];
}

function printUserLine(string $role, int $userId, WalletService $wallet): void
{
    $user = UserTable::getById($userId)->fetch();
    $login = $user['LOGIN'] ?? ('#' . $userId);
    $w = $wallet->getWalletSummary($userId);
    echo "  {$role}: id={$userId} ({$login}) wallet={$w['prognobaks']} prognobaks\n";
}

function topUpIfNeeded(bool $dryRun, WalletService $wallet, int $userId, float $need, string $reason): void
{
    $current = $wallet->getWalletSummary($userId)['prognobaks'];
    if ($current >= $need) {
        return;
    }
    $delta = round($need - $current, 1);
    if ($dryRun) {
        echo "Would top-up user {$userId} +{$delta} ({$reason})\n";

        return;
    }
    $wallet->credit($userId, GameEconomyConfig::CURRENCY_PROGNOBAKS, $delta, $reason, 'user', $userId);
    echo "Topped up user {$userId} +{$delta}\n";
}
