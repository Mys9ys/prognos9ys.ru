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

\Bitrix\Main\Loader::includeModule('prognos9ys.main');

use Bitrix\Main\UserTable;
use Prognos9ys\Main\Model\Repository\GameEconomyRepository;

const TEST_PROGNOBAKS = 100.0;
const TEST_RUBLIUS = 1.0;

$repository = new GameEconomyRepository();
$processed = 0;
$updated = 0;
$created = 0;
$failed = 0;
$lastId = 0;

while (true) {
    $rows = UserTable::getList([
        'select' => ['ID'],
        'filter' => [
            '>ID' => $lastId,
            '=ACTIVE' => 'Y',
        ],
        'order' => ['ID' => 'ASC'],
        'limit' => 500,
    ])->fetchAll();

    if (!$rows) {
        break;
    }

    foreach ($rows as $row) {
        $userId = (int)($row['ID'] ?? 0);
        $lastId = $userId;
        if ($userId <= 0) {
            continue;
        }

        $processed++;

        try {
            $wallet = $repository->getWalletByUserId($userId);
            if ($wallet) {
                $repository->updateWallet((int)$wallet['ID'], [
                    'UF_PROGNOBAKS' => TEST_PROGNOBAKS,
                    'UF_RUBLIUS' => TEST_RUBLIUS,
                ]);
                $updated++;
            } else {
                $repository->addWallet([
                    'UF_USER_ID' => $userId,
                    'UF_PROGNOBAKS' => TEST_PROGNOBAKS,
                    'UF_RUBLIUS' => TEST_RUBLIUS,
                ]);
                $created++;
            }
        } catch (\Throwable $exception) {
            $failed++;
        }
    }
}

echo "Test wallets set to " . TEST_PROGNOBAKS . " prognobaks / " . TEST_RUBLIUS . " rublius\n";
echo "  processed: {$processed}\n";
echo "  updated: {$updated}\n";
echo "  created: {$created}\n";
echo "  failed: {$failed}\n";
