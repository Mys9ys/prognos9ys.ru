<?php
declare(strict_types=1);

/**
 * Баланс госбанка (паримутуель ЧМ).
 *
 *   php diag_game_bank.php
 */

$docRoot = dirname(__DIR__, 2);
$_SERVER['DOCUMENT_ROOT'] = $docRoot;
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['SERVER_NAME'] = $_SERVER['SERVER_NAME'] ?? 'localhost';

define('NO_KEEP_STATISTIC', true);
define('NO_AGENT_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

require_once $docRoot . '/bitrix/modules/main/include/prolog_before.php';

use Prognos9ys\Main\Service\Game\GameBankService;

if (!\Bitrix\Main\Loader::includeModule('prognos9ys.main')) {
    echo "prognos9ys.main module not loaded\n";
    exit(1);
}

$bank = (new GameBankService())->getSummary();

echo $bank['title'] . "\n";
echo 'code: ' . $bank['code'] . "\n";
echo 'prognobaks: ' . $bank['prognobaks'] . "\n";
