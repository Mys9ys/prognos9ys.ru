<?php
declare(strict_types=1);

/**
 * Диагностика ставок и кошельков по матчам ЧМ-2026.
 *
 *   php local/tools/diagnose_match_economy.php 22 23
 *   php local/tools/diagnose_match_economy.php --grant-missing
 *   php local/tools/diagnose_match_economy.php 23 --recalc
 */

$docRoot = dirname(__DIR__, 2);
$_SERVER['DOCUMENT_ROOT'] = $docRoot;
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['SERVER_NAME'] = $_SERVER['SERVER_NAME'] ?? 'localhost';
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require_once $docRoot . '/bitrix/modules/main/include/prolog_before.php';

\Bitrix\Main\Loader::includeModule('iblock');
\Bitrix\Main\Loader::includeModule('prognos9ys.main');

use Bitrix\Main\UserTable;
use Prognos9ys\Main\Model\Repository\GameEconomyRepository;
use Prognos9ys\Main\Service\Football\FootballMatchService;
use Prognos9ys\Main\Service\Game\BetService;
use Prognos9ys\Main\Service\Game\GameEventScopeService;
use Prognos9ys\Main\Service\Game\WalletService;

$argv = $_SERVER['argv'] ?? [];
$grantMissing = in_array('--grant-missing', $argv, true);
$doRecalc = in_array('--recalc', $argv, true);
$numbers = [];
foreach ($argv as $arg) {
    if (is_numeric($arg)) {
        $numbers[] = (int)$arg;
    }
}
if (!$numbers) {
    $numbers = [22, 23];
}

$eventId = 63849;
$repo = new GameEconomyRepository();
$scope = new GameEventScopeService();
$matchSvc = new FootballMatchService();
$betSvc = new BetService();
$walletSvc = new WalletService($repo);

echo "=== HL wallet / bets ===\n";
try {
    $repo->getWalletDataClass();
    $repo->getMatchBetDataClass();
    echo "OK highload tables compiled\n\n";
} catch (\Throwable $e) {
    echo "FAIL HL: {$e->getMessage()}\n\n";
}

if ($grantMissing) {
    echo "=== grantStarterPackIfMissing for @prognos9ys.ru ===\n";
    $granted = 0;
    $rs = UserTable::getList([
        'filter' => ['%EMAIL' => '@prognos9ys.ru', '=ACTIVE' => 'Y'],
        'select' => ['ID', 'EMAIL'],
        'order' => ['ID' => 'ASC'],
    ]);
    while ($row = $rs->fetch()) {
        if ($walletSvc->grantStarterPackIfMissing((int)$row['ID'])) {
            $granted++;
            echo "  granted #{$row['ID']} {$row['EMAIL']}\n";
        }
    }
    echo "Granted: {$granted}\n\n";
}

foreach ($numbers as $num) {
    $r = $matchSvc->getMatch((string)$eventId, (string)$num, null);
    if (($r['status'] ?? '') !== 'ok') {
        echo "Match #{$num}: NOT FOUND\n\n";
        continue;
    }

    $matchId = (int)$r['result']['id'];
    $teams = ($r['result']['home']['name'] ?? '?') . ' — ' . ($r['result']['guest']['name'] ?? '?');

    $row = \CIBlockElement::GetList(
        [],
        ['IBLOCK_ID' => 2, 'ID' => $matchId],
        false,
        false,
        ['ID', 'ACTIVE', 'PROPERTY_result', 'PROPERTY_goal_home', 'PROPERTY_goal_guest']
    )->GetNext();

    echo "=== Match #{$num} id={$matchId} {$teams} ===\n";
    echo 'active=' . ($r['result']['active'] ?? '?')
        . ' score=' . ($row['PROPERTY_GOAL_HOME_VALUE'] ?? '?') . ':' . ($row['PROPERTY_GOAL_GUEST_VALUE'] ?? '?')
        . ' result=' . ($row['PROPERTY_RESULT_VALUE'] ?? '(empty)')
        . ' inScope=' . ($scope->isMatchInScope($eventId, $num) ? 'yes' : 'no') . "\n";

    $prognosisIbId = (int)(\CIBlock::GetList([], ['CODE' => 'prognosis'])->Fetch()['ID'] ?? 0);
    $prognosisCount = $prognosisIbId > 0
        ? (int)\CIBlockElement::GetList([], ['IBLOCK_ID' => $prognosisIbId, 'PROPERTY_match_id' => $matchId], [])
        : 0;
    echo "prognoses: {$prognosisCount}\n";

    $bets = $repo->getMatchBetsByMatch($matchId);
    $byStatus = [];
    $totalPayout = 0.0;
    foreach ($bets as $bet) {
        $st = (string)($bet['UF_STATUS'] ?? '?');
        $byStatus[$st] = ($byStatus[$st] ?? 0) + 1;
        if ($st === 'won') {
            $totalPayout += (float)($bet['UF_PAYOUT'] ?? 0);
        }
    }
    echo 'bets=' . count($bets) . ' statuses=' . json_encode($byStatus, JSON_UNESCAPED_UNICODE)
        . " payout_total={$totalPayout}\n";

    $counts = $betSvc->getMatchBetCounts($matchId);
    echo 'bet_counts=' . json_encode($counts, JSON_UNESCAPED_UNICODE) . "\n";

    $noBonus = 0;
    $poor = 0;
    if ($prognosisIbId > 0) {
        $prs = \CIBlockElement::GetList(
            [],
            ['IBLOCK_ID' => $prognosisIbId, 'PROPERTY_match_id' => $matchId],
            false,
            false,
            ['PROPERTY_user_id']
        );
        while ($p = $prs->GetNext()) {
            $uid = (int)($p['PROPERTY_USER_ID_VALUE'] ?? 0);
            if ($uid <= 0) {
                continue;
            }
            if (!$repo->hasWalletTx($uid, 'registration_bonus', 'user', $uid)) {
                $noBonus++;
            }
            $w = $walletSvc->getWalletSummary($uid);
            if ((float)$w['prognobaks'] < 10) {
                $poor++;
            }
        }
    }
    echo "prognosis_users_without_starter_bonus={$noBonus} prognosis_users_wallet_lt_10={$poor}\n";

    if ($doRecalc) {
        echo "recalc...\n";
        new \CalcFootballPrognosisResult(['matchId' => $matchId]);
        $betsAfter = $repo->getMatchBetsByMatch($matchId);
        $wonAfter = 0;
        $payoutAfter = 0.0;
        foreach ($betsAfter as $bet) {
            if (($bet['UF_STATUS'] ?? '') === 'won') {
                $wonAfter++;
                $payoutAfter += (float)($bet['UF_PAYOUT'] ?? 0);
            }
        }
        echo "after recalc: bets=" . count($betsAfter) . " won={$wonAfter} payout_total={$payoutAfter}\n";
    }

    echo "\n";
}

echo "Done.\n";
