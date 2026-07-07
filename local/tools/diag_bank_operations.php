<?php

declare(strict_types=1);

$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Prognos9ys\Main\Model\Repository\GameEconomyRepository;
use Prognos9ys\Main\Service\Game\BankOperationsService;

$login = $argv[1] ?? '';
if ($login === '') {
    fwrite(STDERR, "Usage: php local/tools/diag_bank_operations.php <login>\n");
    exit(1);
}

$user = \Bitrix\Main\UserTable::getList([
    'filter' => ['=LOGIN' => $login],
    'select' => ['ID', 'LOGIN', 'NAME'],
    'limit' => 1,
])->fetch();

if (!$user) {
    fwrite(STDERR, "User not found: {$login}\n");
    exit(1);
}

$userId = (int)$user['ID'];
$repository = new GameEconomyRepository();
$bank = $repository->getUserBankByOwnerId($userId);

echo "User #{$userId} ({$user['LOGIN']})\n";
echo 'Bank: ' . ($bank ? ('#' . (int)$bank['ID']) : 'none') . "\n";

$walletTx = $repository->getBankWalletTxByUserId($userId, 10);
echo 'Wallet bank txs: ' . count($walletTx) . "\n";
foreach ($walletTx as $row) {
    echo '  - ' . ($row['UF_REASON'] ?? '') . ' ' . ($row['UF_AMOUNT'] ?? '') . ' at ' . ($row['UF_CREATED_AT'] ?? '') . "\n";
}

if ($bank) {
    $bankId = (int)$bank['ID'];
    $deposits = $repository->getDepositsByBankId($bankId);
    $loans = $repository->getLoansByBankId($bankId);
    $consignments = $repository->getConsignmentsByBankId($bankId, 10);
    echo 'Deposits in bank: ' . count($deposits) . "\n";
    echo 'Loans in bank: ' . count($loans) . "\n";
    echo 'Consignments sample: ' . count($consignments) . "\n";

    $t0 = microtime(true);
    $trades = $repository->getExchangeTradesForBankId($bankId, 30);
    $t1 = microtime(true);
    echo 'Exchange trades (30): ' . count($trades) . ' in ' . round(($t1 - $t0) * 1000) . " ms\n";
}

$t0 = microtime(true);
$ops = (new BankOperationsService($repository))->getForUser($userId, 30);
$t1 = microtime(true);
echo 'Operations returned: ' . count($ops) . ' in ' . round(($t1 - $t0) * 1000) . " ms\n";
foreach (array_slice($ops, 0, 5) as $op) {
    echo '  - [' . ($op['at'] ?? '') . '] ' . ($op['label'] ?? '') . ' ' . ($op['amount'] ?? '') . "\n";
}
