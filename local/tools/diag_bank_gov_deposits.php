<?php
declare(strict_types=1);

/**
 * Сводка по гос. вкладам банка и учёту процентов.
 *
 *   php local/tools/diag_bank_gov_deposits.php [bank_id|owner_user_id]
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
use Prognos9ys\Main\Service\Game\BankOperationsService;
use Prognos9ys\Main\Service\Game\GameEconomyConfig;
use Prognos9ys\Main\Service\Game\GovSupportDepositService;

if (!\Bitrix\Main\Loader::includeModule('prognos9ys.main')) {
    echo "prognos9ys.main module not loaded\n";
    exit(1);
}

$arg = (int)($argv[1] ?? 0);
$repository = new GameEconomyRepository();

$bank = null;
if ($arg > 0) {
    $bank = $repository->getUserBankById($arg) ?: $repository->getUserBankByOwnerId($arg);
}

if (!$bank) {
    echo "Bank not found for arg={$arg}. Pass bank_id or owner user_id.\n";
    exit(1);
}

$bankId = (int)$bank['ID'];
$ownerId = (int)($bank['UF_OWNER_ID'] ?? 0);
$lifetime = (new BankOperationsService($repository))->getLifetimeTotalsForBank($bankId);

echo "Bank #{$bankId}, owner user #{$ownerId}\n";
echo "liquid: " . ($bank['UF_LIQUID'] ?? 0) . "\n\n";
echo "Lifetime totals:\n";
echo "  loan interest earned: " . $lifetime['total_loan_interest_earned'] . "\n";
echo "  deposit principal returned: " . $lifetime['total_deposit_principal_returned'] . "\n";
echo "  deposit interest paid: " . $lifetime['total_deposit_interest_paid'] . " (includes gov interest to treasury)\n";
echo "  note: gov principal return to treasury is NOT in principal returned stat\n\n";

$govCount = 0;
$govInterestExpected = 0.0;
$govOpenBody = 0.0;

foreach ($repository->getDepositsByBankId($bankId) as $deposit) {
    if (!GovSupportDepositService::isGovSupportDeposit($deposit)) {
        continue;
    }

    $govCount++;
    $id = (int)$deposit['ID'];
    $status = (string)($deposit['UF_STATUS'] ?? '');
    $principal = round((float)($deposit['UF_PRINCIPAL'] ?? 0), 1);
    $interest = GameEconomyConfig::calculateGovSupportInterest($principal);
    $moderatorId = (int)($deposit['UF_USER_ID'] ?? 0);

    echo "#{$id} status={$status} principal={$principal} interest={$interest} opened_by={$moderatorId}\n";

    if (in_array($status, [
        GameEconomyConfig::CONTRACT_STATUS_INTEREST_PAID,
        GameEconomyConfig::CONTRACT_STATUS_CLOSED,
    ], true)) {
        $govInterestExpected = round($govInterestExpected + $interest, 1);
    }

    if (in_array($status, [
        GameEconomyConfig::CONTRACT_STATUS_ACTIVE,
        GameEconomyConfig::CONTRACT_STATUS_EXTENDED,
        GameEconomyConfig::CONTRACT_STATUS_INTEREST_PAID,
    ], true)) {
        $govOpenBody = round($govOpenBody + $principal, 1);
    }
}

echo "\nGov deposits: {$govCount}\n";
echo "Gov interest expected in lifetime stat: {$govInterestExpected}\n";
echo "Gov body still in bank: {$govOpenBody}\n";
