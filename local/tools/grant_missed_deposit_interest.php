<?php
declare(strict_types=1);

/**
 * Разовая выплата пропущенных процентов по вкладам (без изменения логики settlement).
 * Идемпотентно: повторный запуск безопасен.
 *
 *   php grant_missed_deposit_interest.php [bankId] [--dry-run]
 *
 * Без bankId — проверить все активные продлённые вклады во всех банках.
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

$bankId = 0;
$dryRun = false;

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--dry-run') {
        $dryRun = true;
        continue;
    }

    if (ctype_digit($arg)) {
        $bankId = (int)$arg;
    }
}

$service = new BankDepositService(new GameEconomyRepository());
$missed = $service->findMissedInterestDeposits($bankId > 0 ? $bankId : null);

echo ($dryRun ? '[DRY-RUN] ' : '') . 'Missed interest deposits: ' . count($missed) . PHP_EOL;

if (!$missed) {
    exit(0);
}

$paid = 0;
$skipped = 0;
$errors = 0;

foreach ($missed as $item) {
    $depositId = (int)($item['id'] ?? 0);
    $label = sprintf(
        'deposit #%d bank #%d user #%d principal %.1f interest %.1f',
        $depositId,
        (int)($item['bank_id'] ?? 0),
        (int)($item['user_id'] ?? 0),
        (float)($item['principal'] ?? 0),
        (float)($item['missed_interest'] ?? 0)
    );

    $result = $service->tryPayMissedInterest($depositId, $dryRun);
    $status = (string)($result['status'] ?? 'unknown');

    echo $label . ' => ' . $status;
    if (isset($result['reason'])) {
        echo ' (' . $result['reason'] . ')';
    }
    if (isset($result['interest'])) {
        echo ' interest=' . $result['interest'];
    }
    echo PHP_EOL;

    if ($status === 'paid' || $status === 'would_pay') {
        $paid++;
    } elseif ($status === 'error') {
        $errors++;
    } else {
        $skipped++;
    }
}

echo PHP_EOL . 'Summary: paid/would_pay=' . $paid . ', skipped=' . $skipped . ', errors=' . $errors . PHP_EOL;

exit($errors > 0 ? 1 : 0);
