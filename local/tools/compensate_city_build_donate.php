<?php
declare(strict_types=1);

/**
 * Разовая компенсация за сдачу в госстройку до внедрения выплат (city_build_donate).
 *
 *   php local/tools/compensate_city_build_donate.php <login|userId> <qty> <componentCode> [citySlug] [--apply]
 *
 * Пример (20 окон в Кабовердянск):
 *   php local/tools/compensate_city_build_donate.php Mys9ysilii 20 window_regular cpv --apply
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
use Prognos9ys\Main\Service\Game\EstateRecipesConfig;
use Prognos9ys\Main\Service\Game\GameEconomyConfig;
use Prognos9ys\Main\Service\Game\ProfessionCraftedItemConfig;
use Prognos9ys\Main\Service\Game\WalletService;

if (!\Bitrix\Main\Loader::includeModule('prognos9ys.main')) {
    echo "prognos9ys.main module not loaded\n";
    exit(1);
}

$args = array_slice($argv, 1);
$apply = false;
$args = array_values(array_filter($args, static function (string $arg) use (&$apply): bool {
    if ($arg === '--apply') {
        $apply = true;

        return false;
    }

    return true;
}));

if (count($args) < 3) {
    echo "Usage: php local/tools/compensate_city_build_donate.php <login|userId> <qty> <componentCode> [citySlug] [--apply]\n";
    exit(1);
}

[$userArg, $qtyArg, $componentCode] = $args;
$citySlug = strtolower(trim((string)($args[3] ?? 'cpv')));
$qty = (int)$qtyArg;
$componentCode = trim($componentCode);

if ($qty <= 0 || $componentCode === '') {
    echo "Invalid qty or component code\n";
    exit(1);
}

$userId = ctype_digit($userArg) ? (int)$userArg : 0;
if ($userId <= 0) {
    $row = \CUser::GetByLogin($userArg)->Fetch();
    if (!$row) {
        $row = \CUser::GetList('id', 'asc', ['LOGIN' => $userArg], ['FIELDS' => ['ID', 'LOGIN']])->Fetch();
    }
    if (!$row) {
        echo "User not found: {$userArg}\n";
        exit(1);
    }
    $userId = (int)$row['ID'];
    $login = (string)$row['LOGIN'];
} else {
    $row = \CUser::GetByID($userId)->Fetch();
    if (!$row) {
        echo "User not found: {$userId}\n";
        exit(1);
    }
    $login = (string)($row['LOGIN'] ?? $userId);
}

if (!ProfessionCraftedItemConfig::isKnownItem($componentCode)) {
    echo "Unknown component: {$componentCode}\n";
    exit(1);
}

$payout = EstateRecipesConfig::calcComponentDonationPayout($componentCode, $qty);
$refId = crc32($userId . ':' . $citySlug . ':' . $componentCode . ':' . $qty);
$reason = 'city_build_donate_retro';

$repo = new GameEconomyRepository();
$wallet = (new WalletService($repo))->getWalletSummary($userId);

echo "User: {$login} (#{$userId})\n";
echo "City: {$citySlug}\n";
echo "Component: {$componentCode} (" . ProfessionCraftedItemConfig::getLabel($componentCode) . ") ×{$qty}\n";
echo "Payout: {$payout} prognobaks\n";
echo "Current balance: {$wallet['prognobaks']} prognobaks\n";

if ($repo->hasWalletTx($userId, $reason, 'city_build_retro', $refId, GameEconomyConfig::CURRENCY_PROGNOBAKS)) {
    echo "Already compensated (ref {$refId}). Nothing to do.\n";
    exit(0);
}

if (!$apply) {
    echo "Dry run. Pass --apply to credit wallet.\n";
    exit(0);
}

$newWallet = (new WalletService($repo))->credit(
    $userId,
    GameEconomyConfig::CURRENCY_PROGNOBAKS,
    $payout,
    $reason,
    'city_build_retro',
    $refId
);

echo "Credited {$payout} prognobaks. New balance: {$newWallet['prognobaks']}\n";
