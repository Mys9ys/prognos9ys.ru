<?php
declare(strict_types=1);

/**
 * Откат ошибочной выплаты процентов по вкладу.
 * Идемпотентно: повторный запуск безопасен.
 *
 *   php rollback_deposit_interest.php <depositId> [--dry-run]
 *
 * Пример: php rollback_deposit_interest.php 4
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
use Prognos9ys\Main\Service\Game\BankDepositService;

if (!\Bitrix\Main\Loader::includeModule('prognos9ys.main')) {
    echo "prognos9ys.main module not loaded\n";
    exit(1);
}

$depositId = 0;
$dryRun = false;

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--dry-run') {
        $dryRun = true;
        continue;
    }

    if (ctype_digit($arg)) {
        $depositId = (int)$arg;
    }
}

if ($depositId <= 0) {
    echo "Usage: php rollback_deposit_interest.php <depositId> [--dry-run]\n";
    exit(1);
}

$service = new BankDepositService(new GameEconomyRepository());
$result = $service->rollbackInterestPayment($depositId, $dryRun);

echo ($dryRun ? '[DRY-RUN] ' : '') . 'deposit #' . $depositId . ' => ' . ($result['status'] ?? 'unknown');
if (isset($result['reason'])) {
    echo ' (' . $result['reason'] . ')';
}
if (isset($result['interest'])) {
    echo ' interest=' . $result['interest'];
}
echo PHP_EOL;

exit(($result['status'] ?? '') === 'error' ? 1 : 0);
