<?php
declare(strict_types=1);

$docRoot = dirname(__DIR__, 2);
$_SERVER['DOCUMENT_ROOT'] = $docRoot;
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require_once $docRoot . '/bitrix/modules/main/include/prolog_before.php';

\Bitrix\Main\Loader::includeModule('iblock');
\Bitrix\Main\Loader::includeModule('prognos9ys.main');

use Prognos9ys\Main\Model\Repository\GameEconomyRepository;
use Prognos9ys\Main\Service\Game\BetService;
use Prognos9ys\Main\Service\Game\GameEventScopeService;
use Prognos9ys\Main\Service\Football\FootballMatchService;

$eventId = 63849;
$numbers = array_map('intval', array_slice($argv, 1));
if (!$numbers) {
    $numbers = [22, 23];
}

$repo = new GameEconomyRepository();
$scope = new GameEventScopeService();
$matchSvc = new FootballMatchService();

foreach ($numbers as $num) {
    $r = $matchSvc->getMatch((string)$eventId, (string)$num, null);
    if (($r['status'] ?? '') !== 'ok') {
        echo "Match #{$num}: not found\n\n";
        continue;
    }

    $m = $r['result'];
    $matchId = (int)$m['id'];
    $teams = implode(' — ', array_column($m['teams'] ?? [], 'name'));

    $row = \CIBlockElement::GetList(
        [],
        ['IBLOCK_ID' => 2, 'ID' => $matchId],
        false,
        false,
        ['ID', 'ACTIVE', 'PROPERTY_result', 'PROPERTY_goal_home', 'PROPERTY_goal_guest', 'PROPERTY_number', 'PROPERTY_events']
    )->GetNext();

    $inScope = $scope->isMatchInScope($eventId, $num);
    $bets = $repo->getMatchBetsByMatch($matchId);
    $pending = $repo->getPendingMatchBetsByMatch($matchId);

    echo "=== Match #{$num} id={$matchId} {$teams} ===\n";
    echo "active={$m['active']} goals={$row['PROPERTY_GOAL_HOME_VALUE']}:{$row['PROPERTY_GOAL_GUEST_VALUE']} result={$row['PROPERTY_RESULT_VALUE']} inScope=" . ($inScope ? 'yes' : 'no') . "\n";
    echo "bets total=" . count($bets) . " pending=" . count($pending) . "\n";

    $byStatus = [];
    foreach ($bets as $bet) {
        $st = (string)($bet['UF_STATUS'] ?? '?');
        $byStatus[$st] = ($byStatus[$st] ?? 0) + 1;
    }
    if ($byStatus) {
        echo 'statuses: ' . json_encode($byStatus, JSON_UNESCAPED_UNICODE) . "\n";
    }

    $won = array_filter($bets, static fn($b) => ($b['UF_STATUS'] ?? '') === 'won');
    echo 'winners: ' . count($won) . ', total payout=' . array_sum(array_map(static fn($b) => (float)($b['UF_PAYOUT'] ?? 0), $won)) . "\n";

    $prognosisIbId = (int)(\CIBlock::GetList([], ['CODE' => 'prognosis'])->Fetch()['ID'] ?? 0);
    $prognosisCount = 0;
    if ($prognosisIbId > 0) {
        $prognosisCount = (int)\CIBlockElement::GetList(
            [],
            ['IBLOCK_ID' => $prognosisIbId, 'PROPERTY_MATCH_ID' => $matchId],
            []
        );
    }
    echo "prognoses: {$prognosisCount}\n";

    // sample first 5 bets
    foreach (array_slice($bets, 0, 5) as $bet) {
        echo sprintf(
            "  user=%d outcome=%s status=%s stake=%s payout=%s\n",
            (int)$bet['UF_USER_ID'],
            $bet['UF_OUTCOME'] ?? '',
            $bet['UF_STATUS'] ?? '',
            $bet['UF_STAKE'] ?? '',
            $bet['UF_PAYOUT'] ?? ''
        );
    }

    $betSvc = new BetService();
    $counts = $betSvc->getMatchBetCounts($matchId);
    echo 'bet counts: ' . json_encode($counts, JSON_UNESCAPED_UNICODE) . "\n\n";
}

// coach thomasson / tunisia if exists
$users = \CUser::GetList('id', 'asc', ['%LOGIN' => 'coachthom'], ['FIELDS' => ['ID', 'LOGIN', 'NAME']]);
while ($u = $users->Fetch()) {
    $uid = (int)$u['ID'];
    echo "=== User {$u['LOGIN']} id={$uid} name={$u['NAME']} ===\n";
    $wallet = (new \Prognos9ys\Main\Service\Game\WalletService($repo))->getWalletSummary($uid);
    echo 'wallet: ' . json_encode($wallet, JSON_UNESCAPED_UNICODE) . "\n";
    foreach ($numbers as $num) {
        $r = $matchSvc->getMatch((string)$eventId, (string)$num, null);
        if (($r['status'] ?? '') !== 'ok') {
            continue;
        }
        $mid = (int)$r['result']['id'];
        $bet = $repo->getMatchBet($uid, $mid);
        echo "  match #{$num} bet: " . ($bet ? json_encode([
            'outcome' => $bet['UF_OUTCOME'],
            'status' => $bet['UF_STATUS'],
            'payout' => $bet['UF_PAYOUT'],
        ], JSON_UNESCAPED_UNICODE) : 'none') . "\n";
    }
    echo "\n";
}
