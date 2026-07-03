<?php
declare(strict_types=1);

/**
 * Пересчёт комиссионки свитков премиума: выплата была в 🪙 вместо 💎.
 *
 *   php local/tools/fix_premium_scroll_consignment_currency.php --dry-run
 *   php local/tools/fix_premium_scroll_consignment_currency.php --confirm
 *   php local/tools/fix_premium_scroll_consignment_currency.php --confirm --user 42
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
use Prognos9ys\Main\Service\Game\BankConsignmentConfig;
use Prognos9ys\Main\Service\Game\ExchangeConfig;
use Prognos9ys\Main\Service\Game\GameEconomyConfig;
use Prognos9ys\Main\Service\Game\WalletService;

if (!\Bitrix\Main\Loader::includeModule('prognos9ys.main')) {
    echo "prognos9ys.main module not loaded\n";
    exit(1);
}

$dryRun = in_array('--dry-run', $argv ?? [], true);
$confirm = in_array('--confirm', $argv ?? [], true);
$userFilter = 0;

for ($i = 1; $i < $argc; $i++) {
    $arg = (string)$argv[$i];
    if ($arg === '--user' && isset($argv[$i + 1])) {
        $userFilter = (int)$argv[++$i];
    }
}

if (!$dryRun && !$confirm) {
    echo "Пересчёт комиссионки свитков премиума (🪙 → 💎).\n";
    echo "  php local/tools/fix_premium_scroll_consignment_currency.php --dry-run\n";
    echo "  php local/tools/fix_premium_scroll_consignment_currency.php --confirm [--user ID]\n";
    exit(1);
}

const REASON_FIX_CREDIT = 'bank_consignment_payout_currency_fix';
const REASON_FIX_DEBIT = 'bank_consignment_payout_currency_fix_revert';
const REASON_BANK_RESERVE_FIX = 'bank_consignment_reserve_currency_fix';
const REASON_SALE_BANK_FIX = 'exchange_sell_bank_currency_fix';

$repository = new GameEconomyRepository();
$walletService = new WalletService($repository);
$consignmentClass = $repository->getBankConsignmentDataClass();
$tradeClass = $repository->getExchangeTradeDataClass();

$stats = [
    'consignments_scanned' => 0,
    'consignments_fixed' => 0,
    'sales_fixed' => 0,
    'skipped_already_fixed' => 0,
    'skipped_no_wrong_tx' => 0,
    'errors' => 0,
];

/**
 * @return array<int, array<string, mixed>>
 */
function loadPremiumScrollConsignments(GameEconomyRepository $repository, int $userFilter): array
{
    $consignmentClass = $repository->getBankConsignmentDataClass();
    $filter = ['=UF_ITEM_KIND' => ExchangeConfig::KIND_PREMIUM_SCROLL];
    if ($userFilter > 0) {
        $filter['=UF_USER_ID'] = $userFilter;
    }

    $rows = [];
    $response = $consignmentClass::getList([
        'filter' => $filter,
        'order' => ['ID' => 'ASC'],
    ]);

    while ($row = $response->fetch()) {
        $rows[] = $row;
    }

    return $rows;
}

/**
 * @return array<int, array<string, mixed>>
 */
function loadTradesForListing(GameEconomyRepository $repository, int $listingId): array
{
    if ($listingId <= 0) {
        return [];
    }

    $tradeClass = $repository->getExchangeTradeDataClass();
    $rows = [];
    $response = $tradeClass::getList([
        'filter' => [
            '=UF_LISTING_ID' => $listingId,
            '=UF_ITEM_KIND' => ExchangeConfig::KIND_PREMIUM_SCROLL,
        ],
        'order' => ['ID' => 'ASC'],
    ]);

    while ($row = $response->fetch()) {
        $rows[] = $row;
    }

    return $rows;
}

function resolveLogin(GameEconomyRepository $repository, int $userId): string
{
    if ($userId <= 0) {
        return '?';
    }

    $row = \CUser::GetByID($userId)->Fetch();

    return $row ? (string)($row['LOGIN'] ?? ('user#' . $userId)) : ('user#' . $userId);
}

function formatScrollLabel(array $consignment): string
{
    $days = (int)($consignment['UF_ITEM_CODE'] ?? 0);
    $qty = (int)($consignment['UF_QTY'] ?? 0);

    return 'свиток ' . ($days > 0 ? $days . 'д' : '?') . ' ×' . $qty;
}

echo ($dryRun ? "[DRY-RUN] " : "[CONFIRM] ") . "Premium scroll consignment currency fix\n\n";

foreach (loadPremiumScrollConsignments($repository, $userFilter) as $consignment) {
    $stats['consignments_scanned']++;

    $consignmentId = (int)($consignment['ID'] ?? 0);
    $userId = (int)($consignment['UF_USER_ID'] ?? 0);
    $bankId = (int)($consignment['UF_BANK_ID'] ?? 0);
    $listingId = (int)($consignment['UF_LISTING_ID'] ?? 0);
    $instantPaid = round((float)($consignment['UF_INSTANT_PAID'] ?? 0), 1);
    $status = (string)($consignment['UF_STATUS'] ?? '');

    if ($consignmentId <= 0 || $userId <= 0 || $instantPaid <= 0) {
        continue;
    }

    $alreadyFixed = $repository->getWalletTxByRefs(
        'bank_consignment',
        [$consignmentId],
        [REASON_FIX_CREDIT]
    );
    if ($alreadyFixed !== []) {
        $stats['skipped_already_fixed']++;
        continue;
    }

    $payoutTxs = $repository->getWalletTxByRefs(
        'bank_consignment',
        [$consignmentId],
        ['bank_consignment_payout']
    );

    $wrongPayout = null;
    foreach ($payoutTxs as $tx) {
        if ((string)($tx['UF_CURRENCY'] ?? '') !== GameEconomyConfig::CURRENCY_PROGNOBAKS) {
            continue;
        }
        if ((float)($tx['UF_AMOUNT'] ?? 0) <= 0) {
            continue;
        }
        $wrongPayout = $tx;
        break;
    }

    if ($wrongPayout === null) {
        $stats['skipped_no_wrong_tx']++;
        continue;
    }

    $amount = round((float)($wrongPayout['UF_AMOUNT'] ?? 0), 1);
    if ($amount <= 0) {
        $stats['skipped_no_wrong_tx']++;
        continue;
    }

    if (abs($amount - $instantPaid) > 0.05) {
        echo "WARN consignment #{$consignmentId}: payout {$amount} != instant {$instantPaid}, using payout tx amount\n";
    }

    $bank = $repository->getUserBankById($bankId);
    $bankOwnerId = (int)($bank['UF_OWNER_ID'] ?? 0);
    $login = resolveLogin($repository, $userId);

    echo sprintf(
        "FIX consign #%d user %s (#%d): %s, instant=%.1f 💎 (was %.1f 🪙), bank #%d, listing #%d, status=%s\n",
        $consignmentId,
        $login,
        $userId,
        formatScrollLabel($consignment),
        $amount,
        $amount,
        $bankId,
        $listingId,
        $status
    );

    if ($dryRun) {
        $stats['consignments_fixed']++;
    } else {
        try {
            $walletService->debit(
                $userId,
                GameEconomyConfig::CURRENCY_PROGNOBAKS,
                $amount,
                REASON_FIX_DEBIT,
                'bank_consignment',
                $consignmentId
            );
            $walletService->credit(
                $userId,
                GameEconomyConfig::CURRENCY_RUBLIUS,
                $amount,
                REASON_FIX_CREDIT,
                'bank_consignment',
                $consignmentId
            );

            if ($bankId > 0) {
                $repository->adjustUserBankLiquid($bankId, $amount);
            }

            if ($bankOwnerId > 0) {
                $walletService->debit(
                    $bankOwnerId,
                    GameEconomyConfig::CURRENCY_RUBLIUS,
                    $amount,
                    REASON_BANK_RESERVE_FIX,
                    'bank_consignment',
                    $consignmentId
                );
            }

            $stats['consignments_fixed']++;
        } catch (\Throwable $exception) {
            $stats['errors']++;
            echo "  ERROR consign #{$consignmentId}: {$exception->getMessage()}\n";
            continue;
        }
    }

    if ($status !== BankConsignmentConfig::STATUS_SOLD || $listingId <= 0) {
        continue;
    }

    $saleAlreadyFixed = $repository->getWalletTxByRefs(
        'exchange_listing',
        [$listingId],
        [REASON_SALE_BANK_FIX]
    );
    if ($saleAlreadyFixed !== []) {
        continue;
    }

    foreach (loadTradesForListing($repository, $listingId) as $trade) {
        $tradeId = (int)($trade['ID'] ?? 0);
        $sellerNet = round((float)($trade['UF_SELLER_NET'] ?? 0), 1);
        if ($tradeId <= 0 || $sellerNet <= 0 || $bankId <= 0) {
            continue;
        }

        $bankRow = $repository->getUserBankById($bankId);
        $ownerId = (int)($bankRow['UF_OWNER_ID'] ?? 0);
        if ($ownerId <= 0) {
            echo "  WARN sale trade #{$tradeId}: bank owner missing\n";
            continue;
        }

        echo sprintf(
            "  FIX sale trade #%d listing #%d: bank liquid -%.1f 🪙, owner +%.1f 💎\n",
            $tradeId,
            $listingId,
            $sellerNet,
            $sellerNet
        );

        if ($dryRun) {
            $stats['sales_fixed']++;
            continue;
        }

        try {
            $repository->adjustUserBankLiquid($bankId, -$sellerNet);
            $walletService->credit(
                $ownerId,
                GameEconomyConfig::CURRENCY_RUBLIUS,
                $sellerNet,
                REASON_SALE_BANK_FIX,
                'exchange_listing',
                $listingId
            );
            $stats['sales_fixed']++;
        } catch (\Throwable $exception) {
            $stats['errors']++;
            echo "  ERROR sale trade #{$tradeId}: {$exception->getMessage()}\n";
        }
    }
}

echo "\nSummary:\n";
foreach ($stats as $key => $value) {
    echo "  {$key}: {$value}\n";
}

if ($dryRun) {
    echo "\nRe-run with --confirm to apply.\n";
}
