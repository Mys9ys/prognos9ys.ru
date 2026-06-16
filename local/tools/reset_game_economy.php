<?php
declare(strict_types=1);

/**
 * Полный сброс игровой экономики перед повторным пересчётом матчей.
 *
 *   php reset_game_economy.php --dry-run
 *   php reset_game_economy.php --confirm
 *
 * Делает:
 *  - удаляет все wallet_tx, match_bet, pending_xp, treasure_chest
 *  - обнуляет госбанк parimutuel
 *  - кошельки: 100 💵 / 1 💎 (старт) + 50/5 тем, у кого уровень ≥ 1
 *  - XP/уровни не трогает
 *
 * После сброса пересчитайте матчи:
 *   php recalc_matches_range.php 63849 1 N
 */

$docRoot = dirname(__DIR__, 2);
$_SERVER['DOCUMENT_ROOT'] = $docRoot;
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['SERVER_NAME'] = $_SERVER['SERVER_NAME'] ?? 'localhost';

define('NO_KEEP_STATISTIC', true);
define('NO_AGENT_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

require_once $docRoot . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\UserTable;
use Prognos9ys\Main\Model\Repository\GameEconomyRepository;
use Prognos9ys\Main\Service\Game\GameEconomyConfig;
use Prognos9ys\Main\Service\Game\UserProgressService;

if (!\Bitrix\Main\Loader::includeModule('prognos9ys.main')) {
    echo "prognos9ys.main module not loaded\n";
    exit(1);
}

$dryRun = in_array('--dry-run', $argv ?? [], true);
$confirm = in_array('--confirm', $argv ?? [], true);

if (!$dryRun && !$confirm) {
    echo "DANGER: this deletes all economy transactions and bets.\n";
    echo "Dry run:  php reset_game_economy.php --dry-run\n";
    echo "Execute:  php reset_game_economy.php --confirm\n";
    exit(1);
}

$repository = new GameEconomyRepository();
$progressService = new UserProgressService($repository);

$levelOneReward = GameEconomyConfig::getLevelUpReward(1);
$baseP = GameEconomyConfig::START_PROGNOBAKS;
$baseR = GameEconomyConfig::START_RUBLIUS;

echo ($dryRun ? '[DRY RUN] ' : '') . "Reset game economy\n";
echo "Base wallet: {$baseP} prognobaks / {$baseR} rublius\n";
echo "Level ≥1 bonus: +{$levelOneReward['prognobaks']} / +{$levelOneReward['rublius']}\n\n";

$stats = [
    'wallet_tx' => 0,
    'match_bets' => 0,
    'pending_xp' => 0,
    'chests' => 0,
    'wallets_updated' => 0,
    'wallets_created' => 0,
    'level1_bonus' => 0,
    'users' => 0,
];

if (!$dryRun) {
    $stats['wallet_tx'] = $repository->deleteAllWalletTx();
    $stats['match_bets'] = $repository->deleteAllMatchBets();
    $stats['pending_xp'] = $repository->deleteAllPendingXp();
    $stats['chests'] = $repository->deleteAllTreasureChests();
    $repository->resetGameBank(GameEconomyConfig::GAME_BANK_CODE_FOOTBALL_PARIMUTUEL);
} else {
    $stats['wallet_tx'] = countRows($repository->getWalletTxDataClass());
    $stats['match_bets'] = countRows($repository->getMatchBetDataClass());
    $stats['pending_xp'] = countRows($repository->getPendingXpDataClass());
    $stats['chests'] = countRows($repository->getTreasureChestDataClass());
}

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

        $stats['users']++;

        $summary = $progressService->getSummary($userId);
        $level = (int)($summary['level'] ?? 0);

        $prognobaks = $baseP;
        $rublius = $baseR;

        if ($level >= 1) {
            $prognobaks += $levelOneReward['prognobaks'];
            $rublius += $levelOneReward['rublius'];
            $stats['level1_bonus']++;
        }

        if ($dryRun) {
            continue;
        }

        $wallet = $repository->getWalletByUserId($userId);
        if ($wallet) {
            $repository->updateWallet((int)$wallet['ID'], [
                'UF_PROGNOBAKS' => $prognobaks,
                'UF_RUBLIUS' => $rublius,
            ]);
            $stats['wallets_updated']++;
        } else {
            $repository->addWallet([
                'UF_USER_ID' => $userId,
                'UF_PROGNOBAKS' => $prognobaks,
                'UF_RUBLIUS' => $rublius,
            ]);
            $stats['wallets_created']++;
        }
    }
}

echo "Deleted / found:\n";
echo "  wallet_tx: {$stats['wallet_tx']}\n";
echo "  match_bets: {$stats['match_bets']}\n";
echo "  pending_xp: {$stats['pending_xp']}\n";
echo "  treasure_chests: {$stats['chests']}\n";
echo "  game bank: reset to 0\n\n";

echo "Wallets (active users: {$stats['users']}):\n";
echo "  with level ≥1 bonus: {$stats['level1_bonus']}\n";

if ($dryRun) {
    echo "  (dry run — wallets not changed)\n";
} else {
    echo "  updated: {$stats['wallets_updated']}\n";
    echo "  created: {$stats['wallets_created']}\n";
}

echo "\nNext: php recalc_matches_range.php 63849 1 N\n";

function countRows(string $dataClass): int
{
    $count = 0;
    $response = $dataClass::getList(['select' => ['ID']]);
    while ($response->fetch()) {
        $count++;
    }

    return $count;
}
