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

use Prognos9ys\Main\Model\Repository\GameEconomyRepository;
use Prognos9ys\Main\Service\Game\ExchangeConfig;
use Prognos9ys\Main\Service\Game\ExchangeService;
use Prognos9ys\Main\Service\Game\MacroEconomyService;
use Prognos9ys\Main\Service\Game\WalletService;

$mode = (string)($argv[1] ?? 'survey');
$repo = new GameEconomyRepository();
$walletService = new WalletService();

function printBankSnapshot(GameEconomyRepository $repo, int $bankId): void
{
    $bank = $repo->getUserBankById($bankId);
    if (!$bank) {
        echo "  bank #{$bankId}: not found\n";
        return;
    }

    echo sprintf(
        "  bank #%d owner=%d liquid=%.1f reserved=%.1f\n",
        $bankId,
        (int)($bank['UF_OWNER_ID'] ?? 0),
        (float)($bank['UF_LIQUID'] ?? 0),
        (float)($bank['UF_RESERVED'] ?? 0)
    );
}

function printWallet(WalletService $walletService, int $userId, string $label): void
{
    $wallet = $walletService->getWalletSummary($userId);
    echo sprintf(
        "  %s user #%d: prognobaks=%.1f rublius=%.1f\n",
        $label,
        $userId,
        (float)($wallet['prognobaks'] ?? 0),
        (float)($wallet['rublius'] ?? 0)
    );
}

function survey(GameEconomyRepository $repo): void
{
    $macro = (new MacroEconomyService())->getSummary();
    $exchange = $macro['exchange'] ?? [];

    echo "=== Exchange macro ===\n";
    echo json_encode($exchange, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n";

    $listingClass = $repo->getExchangeListingDataClass();
    $bankSamples = [];
    $userSamples = [];
    $bySku = [];

    $response = $listingClass::getList([
        'filter' => [
            '=UF_STATUS' => ExchangeConfig::STATUS_ACTIVE,
            '>UF_QTY_REMAINING' => 0,
        ],
        'select' => [
            'ID',
            'UF_SELLER_ID',
            'UF_SELLER_BANK_ID',
            'UF_CONSIGNMENT_ID',
            'UF_ITEM_KIND',
            'UF_ITEM_CODE',
            'UF_ITEM_CATEGORY',
            'UF_QTY_REMAINING',
            'UF_PRICE_PER_UNIT',
            'UF_NOMINAL_SNAPSHOT',
        ],
        'order' => ['ID' => 'ASC'],
    ]);

    while ($row = $response->fetch()) {
        $bankId = (int)($row['UF_SELLER_BANK_ID'] ?? 0);
        $listingId = (int)($row['ID'] ?? 0);
        $kind = (string)($row['UF_ITEM_KIND'] ?? '');
        $code = (string)($row['UF_ITEM_CODE'] ?? '');
        $category = (string)($row['UF_ITEM_CATEGORY'] ?? '');
        $skuKey = $kind . '|' . $code . '|' . $category;

        if (!isset($bySku[$skuKey])) {
            $bySku[$skuKey] = [
                'kind' => $kind,
                'code' => $code,
                'category' => $category,
                'listings' => 0,
                'qty' => 0,
                'sellers' => [],
            ];
        }
        $bySku[$skuKey]['listings']++;
        $bySku[$skuKey]['qty'] += (int)($row['UF_QTY_REMAINING'] ?? 0);
        $bySku[$skuKey]['sellers'][(int)($row['UF_SELLER_ID'] ?? 0)] = true;

        $sample = [
            'listing_id' => $listingId,
            'seller_id' => (int)($row['UF_SELLER_ID'] ?? 0),
            'bank_id' => $bankId,
            'consignment_id' => (int)($row['UF_CONSIGNMENT_ID'] ?? 0),
            'kind' => $kind,
            'code' => $code,
            'category' => $category,
            'qty' => (int)($row['UF_QTY_REMAINING'] ?? 0),
            'price' => (float)($row['UF_PRICE_PER_UNIT'] ?? 0),
            'nominal' => (float)($row['UF_NOMINAL_SNAPSHOT'] ?? 0),
        ];

        if ($bankId > 0 && count($bankSamples) < 8) {
            $bankSamples[] = $sample;
        } elseif ($bankId <= 0 && count($userSamples) < 5) {
            $userSamples[] = $sample;
        }
    }

    echo "=== Bank consignment listings (sample) ===\n";
    foreach ($bankSamples as $sample) {
        echo json_encode($sample, JSON_UNESCAPED_UNICODE) . "\n";
        printBankSnapshot($repo, (int)$sample['bank_id']);
    }

    echo "\n=== User listings (sample) ===\n";
    foreach ($userSamples as $sample) {
        echo json_encode($sample, JSON_UNESCAPED_UNICODE) . "\n";
    }

    $multiSeller = [];
    foreach ($bySku as $skuKey => $info) {
        if (count($info['sellers']) >= 2 && $info['qty'] >= 2) {
            $multiSeller[] = [
                'sku' => $skuKey,
                'listings' => $info['listings'],
                'qty' => $info['qty'],
                'sellers' => count($info['sellers']),
            ];
        }
    }
    usort($multiSeller, static fn(array $a, array $b): int => $b['qty'] <=> $a['qty']);

    echo "\n=== SKUs with multiple sellers (for batch buy test) ===\n";
    foreach (array_slice($multiSeller, 0, 10) as $row) {
        echo json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
    }

    echo "\n=== Top wallets (prognobaks) ===\n";
    $walletClass = $repo->getWalletDataClass();
    $wallets = $walletClass::getList([
        'select' => ['UF_USER_ID', 'UF_PROGNOBAKS'],
        'order' => ['UF_PROGNOBAKS' => 'DESC'],
        'limit' => 8,
    ]);
    while ($wallet = $wallets->fetch()) {
        echo sprintf(
            "  user #%d: %.1f 🪙\n",
            (int)($wallet['UF_USER_ID'] ?? 0),
            (float)($wallet['UF_PROGNOBAKS'] ?? 0)
        );
    }
}

function runBuy(
    GameEconomyRepository $repo,
    WalletService $walletService,
    int $buyerId,
    string $kind,
    string $code,
    int $qty,
    string $category = '',
    float $pricePerUnit = 0.0
): void {
    if ($buyerId <= 0 || $qty <= 0 || $kind === '' || $code === '') {
        echo "Usage: php diag_exchange_purchase.php buy <buyerId> <kind> <code> <qty> [category] [pricePerUnit]\n";
        exit(1);
    }

    $listingClass = $repo->getExchangeListingDataClass();
    $response = $listingClass::getList([
        'filter' => [
            '=UF_STATUS' => ExchangeConfig::STATUS_ACTIVE,
            '>UF_QTY_REMAINING' => 0,
            '=UF_ITEM_KIND' => $kind,
            '=UF_ITEM_CODE' => $code,
            '=UF_ITEM_CATEGORY' => $category,
        ],
        'select' => [
            'ID',
            'UF_SELLER_ID',
            'UF_SELLER_BANK_ID',
            'UF_QTY_REMAINING',
            'UF_PRICE_PER_UNIT',
        ],
        'order' => ['UF_PRICE_PER_UNIT' => 'ASC', 'ID' => 'ASC'],
    ]);

    $bankIds = [];
    $sellerIds = [];
    while ($row = $response->fetch()) {
        $bankId = (int)($row['UF_SELLER_BANK_ID'] ?? 0);
        if ($bankId > 0) {
            $bankIds[$bankId] = true;
        }
        $sellerId = (int)($row['UF_SELLER_ID'] ?? 0);
        if ($sellerId > 0 && $sellerId !== $buyerId) {
            $sellerIds[$sellerId] = true;
        }
    }

    echo "=== Before buy ===\n";
    printWallet($walletService, $buyerId, 'buyer');
    foreach (array_keys($bankIds) as $bankId) {
        printBankSnapshot($repo, (int)$bankId);
    }
    foreach (array_keys($sellerIds) as $sellerId) {
        printWallet($walletService, (int)$sellerId, 'seller');
    }

    $treasuryBefore = $repo->getGameBankByCode(\Prognos9ys\Main\Service\Game\GameEconomyConfig::GAME_BANK_CODE_STATE_TREASURY);
    echo sprintf(
        "  treasury prognobaks=%.1f\n",
        (float)($treasuryBefore['UF_PROGNOBAKS'] ?? 0)
    );

    try {
        $result = (new ExchangeService())->buy(
            $buyerId,
            $kind,
            $code,
            $qty,
            $category,
            0,
            '',
            $pricePerUnit
        );
    } catch (\Throwable $e) {
        echo "\nBUY FAILED: " . $e->getMessage() . "\n";
        exit(1);
    }

    echo "\n=== Buy result ===\n";
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";

    echo "\n=== After buy ===\n";
    printWallet($walletService, $buyerId, 'buyer');
    foreach (array_keys($bankIds) as $bankId) {
        printBankSnapshot($repo, (int)$bankId);
    }
    foreach (array_keys($sellerIds) as $sellerId) {
        printWallet($walletService, (int)$sellerId, 'seller');
    }

    $treasuryAfter = $repo->getGameBankByCode(\Prognos9ys\Main\Service\Game\GameEconomyConfig::GAME_BANK_CODE_STATE_TREASURY);
    echo sprintf(
        "  treasury prognobaks=%.1f (delta %.1f)\n",
        (float)($treasuryAfter['UF_PROGNOBAKS'] ?? 0),
        round((float)($treasuryAfter['UF_PROGNOBAKS'] ?? 0) - (float)($treasuryBefore['UF_PROGNOBAKS'] ?? 0), 1)
    );

    $tradeClass = $repo->getExchangeTradeDataClass();
    $trades = [];
    $tradeResponse = $tradeClass::getList([
        'filter' => ['=UF_BUYER_ID' => $buyerId],
        'select' => [
            'ID',
            'UF_LISTING_ID',
            'UF_SELLER_ID',
            'UF_QTY',
            'UF_TOTAL_PRICE',
            'UF_COMMISSION',
            'UF_SELLER_NET',
        ],
        'order' => ['ID' => 'DESC'],
        'limit' => 10,
    ]);
    while ($trade = $tradeResponse->fetch()) {
        $listingId = (int)($trade['UF_LISTING_ID'] ?? 0);
        $listing = $listingId > 0 ? $repo->getExchangeListingById($listingId) : null;
        $trades[] = [
            'trade_id' => (int)($trade['ID'] ?? 0),
            'listing_id' => $listingId,
            'seller_id' => (int)($trade['UF_SELLER_ID'] ?? 0),
            'bank_id' => (int)($listing['UF_SELLER_BANK_ID'] ?? 0),
            'qty' => (int)($trade['UF_QTY'] ?? 0),
            'total' => (float)($trade['UF_TOTAL_PRICE'] ?? 0),
            'commission' => (float)($trade['UF_COMMISSION'] ?? 0),
            'seller_net' => (float)($trade['UF_SELLER_NET'] ?? 0),
        ];
    }

    echo "\n=== Recent trades for buyer ===\n";
    echo json_encode($trades, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
}

switch ($mode) {
    case 'survey':
        survey($repo);
        break;
    case 'buy':
        $buyerId = (int)($argv[2] ?? 0);
        $kind = (string)($argv[3] ?? '');
        $code = (string)($argv[4] ?? '');
        $qty = (int)($argv[5] ?? 0);
        $category = (string)($argv[6] ?? '');
        $price = isset($argv[7]) ? (float)$argv[7] : 0.0;
        runBuy($repo, $walletService, $buyerId, $kind, $code, $qty, $category, $price);
        break;
    default:
        echo "Usage:\n";
        echo "  php diag_exchange_purchase.php survey\n";
        echo "  php diag_exchange_purchase.php buy <buyerId> <kind> <code> <qty> [category] [pricePerUnit]\n";
        exit(1);
}
