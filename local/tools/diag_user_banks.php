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

$userId = (int)($_SERVER['argv'][1] ?? 0);
$matchId = (int)($_SERVER['argv'][2] ?? 0);

if ($userId <= 0) {
    echo "Usage: php diag_user_banks.php <userId> [matchId]\n";
    echo "  matchId — опционально, прогнать BankSettlementService::onMatchSettled\n";
    exit(1);
}

$repo = new \Prognos9ys\Main\Model\Repository\GameEconomyRepository();
$bankService = new \Prognos9ys\Main\Service\Game\UserBankService();
$depositService = new \Prognos9ys\Main\Service\Game\BankDepositService();
$loanService = new \Prognos9ys\Main\Service\Game\BankLoanService();
$walletService = new \Prognos9ys\Main\Service\Game\WalletService();

echo "=== wallet user {$userId} ===\n";
echo json_encode($walletService->getWalletSummary($userId), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n";

echo "=== my bank ===\n";
$myBank = $bankService->getMyBank($userId);
echo json_encode($myBank, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n";

echo "=== my contracts ===\n";
echo json_encode([
    'deposits' => $depositService->getMyContracts($userId),
    'loans' => $loanService->getMyContracts($userId),
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n";

echo "=== active banks (top 10) ===\n";
echo json_encode($bankService->listBanks(10), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n";

foreach (['user_bank', 'bank_deposit', 'bank_loan'] as $label) {
    try {
        if ($label === 'user_bank') {
            $method = 'getUserBankDataClass';
        } elseif ($label === 'bank_deposit') {
            $method = 'getBankDepositDataClass';
        } else {
            $method = 'getBankLoanDataClass';
        }
        $dc = $repo->$method();
        $count = 0;
        $rs = $dc::getList(['select' => ['ID']]);
        while ($rs->fetch()) {
            $count++;
        }
        echo "HL {$label} rows: {$count}\n";
    } catch (\Throwable $e) {
        echo "HL {$label} error: " . $e->getMessage() . "\n";
        echo "Run: php local/modules/prognos9ys.main/install_game_economy_hl.php\n";
    }
}

if ($matchId > 0) {
    echo "\n=== settle match {$matchId} ===\n";
    try {
        (new \Prognos9ys\Main\Service\Game\BankSettlementService())->onMatchSettled($matchId);
        echo "onMatchSettled OK\n";
        echo json_encode([
            'deposits' => $depositService->getMyContracts($userId),
            'loans' => $loanService->getMyContracts($userId),
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    } catch (\Throwable $e) {
        echo 'settle error: ' . $e->getMessage() . "\n";
    }
}
