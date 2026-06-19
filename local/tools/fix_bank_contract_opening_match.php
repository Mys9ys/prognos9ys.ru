<?php
declare(strict_types=1);

/**
 * Исправить матч открытия контракта (ID/номер последнего завершённого матча на момент запуска).
 *
 *   php fix_bank_contract_opening_match.php loan <loanId>
 *   php fix_bank_contract_opening_match.php deposit <depositId>
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
use Prognos9ys\Main\Service\Game\GameEventScopeService;

if (!\Bitrix\Main\Loader::includeModule('prognos9ys.main')) {
    echo "prognos9ys.main module not loaded\n";
    exit(1);
}

$type = isset($argv[1]) ? strtolower((string)$argv[1]) : '';
$id = isset($argv[2]) ? (int)$argv[2] : 0;

if (!in_array($type, ['loan', 'deposit'], true) || $id <= 0) {
    echo "Usage: php fix_bank_contract_opening_match.php <loan|deposit> <id>\n";
    exit(1);
}

$repository = new GameEconomyRepository();
$scope = new GameEventScopeService();

if ($type === 'loan') {
    $row = $repository->getBankLoanById($id);
    $update = static fn(array $fields) => $repository->updateBankLoan($id, $fields);
} else {
    $row = $repository->getBankDepositById($id);
    $update = static fn(array $fields) => $repository->updateBankDeposit($id, $fields);
}

if (!$row) {
    echo ucfirst($type) . " #{$id} not found\n";
    exit(1);
}

$eventId = (int)($row['UF_EVENT_ID'] ?? 0);
$lastSettled = $scope->getLastSettledMatchForEvent($eventId);

$update([
    'UF_OPENING_MATCH_ID' => $lastSettled['id'],
    'UF_OPENING_MATCH_NUMBER' => $lastSettled['number'],
]);

echo ucfirst($type) . " #{$id} updated: opening_match_id={$lastSettled['id']}, opening_match_number={$lastSettled['number']}\n";
