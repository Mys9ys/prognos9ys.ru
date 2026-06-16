<?php
/**
 * One-off cleanup:
 * 1) Remove duplicate prognosis records (keep oldest per user+match)
 * 2) Delete all result records for match #1 (ЧМ-2026 test match)
 */

declare(strict_types=1);

$docRoot = dirname(__DIR__, 2);
$_SERVER['DOCUMENT_ROOT'] = $docRoot;
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['SERVER_NAME'] = $_SERVER['SERVER_NAME'] ?? 'localhost';

define('NO_KEEP_STATISTIC', true);
define('NO_AGENT_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

require_once $docRoot . '/bitrix/modules/main/include/prolog_before.php';

use Prognos9ys\Main\Service\Game\GameEconomyConfig;

if (!\Bitrix\Main\Loader::includeModule('iblock')) {
    echo "iblock module not loaded\n";
    exit(1);
}

$prognosisIbId = (int)(\CIBlock::GetList([], ['CODE' => 'prognosis'], false)->Fetch()['ID'] ?? 6);
$resultIbId = (int)(\CIBlock::GetList([], ['CODE' => 'result'], false)->Fetch()['ID'] ?? 7);
$matchesIbId = (int)(\CIBlock::GetList([], ['CODE' => 'matches'], false)->Fetch()['ID'] ?? 2);

$eventId = GameEconomyConfig::ANCHOR_EVENT_ID;
$matchNumber = GameEconomyConfig::TEST_ONLY_MATCH_NUMBER;

$matchRow = \CIBlockElement::GetList(
    [],
    [
        'IBLOCK_ID' => $matchesIbId,
        'PROPERTY_events' => $eventId,
        'PROPERTY_number' => $matchNumber,
    ],
    false,
    false,
    ['ID', 'NAME', 'PROPERTY_number', 'PROPERTY_events']
)->Fetch();

if (!$matchRow) {
    echo "Match not found: event={$eventId}, number={$matchNumber}\n";
    exit(1);
}

$matchId = (int)$matchRow['ID'];
echo "Target match: #{$matchNumber}, ID={$matchId}, name={$matchRow['NAME']}\n";

// --- 1) Deduplicate prognosis across entire iblock (user + match_id) ---
$groups = [];
$rs = \CIBlockElement::GetList(
    ['ID' => 'ASC'],
    [
        'IBLOCK_ID' => $prognosisIbId,
    ],
    false,
    false,
    ['ID', 'PROPERTY_user_id', 'PROPERTY_match_id']
);

while ($row = $rs->GetNext()) {
    $userId = (int)($row['PROPERTY_USER_ID_VALUE'] ?? 0);
    $matchIdKey = (int)($row['PROPERTY_MATCH_ID_VALUE'] ?? 0);
    $elemId = (int)$row['ID'];
    if ($userId <= 0 || $matchIdKey <= 0) {
        continue;
    }
    $groups[$userId . ':' . $matchIdKey][] = $elemId;
}

$prognosisDeleted = 0;
$prognosisGroups = 0;
$ib = new \CIBlockElement();

foreach ($groups as $key => $ids) {
    if (count($ids) <= 1) {
        continue;
    }

    $prognosisGroups++;
    $keepId = (int)min($ids);

    foreach ($ids as $id) {
        if ((int)$id === $keepId) {
            continue;
        }
        if ($ib->Delete((int)$id)) {
            $prognosisDeleted++;
        } else {
            echo "Failed to delete prognosis ID={$id} ({$key})\n";
        }
    }
}

echo "Prognosis duplicates: groups={$prognosisGroups}, removed={$prognosisDeleted}\n";

// --- 2) Delete all results for this match ---
$resultsDeleted = 0;
$rsResults = \CIBlockElement::GetList(
    ['ID' => 'ASC'],
    [
        'IBLOCK_ID' => $resultIbId,
        'PROPERTY_match_id' => $matchId,
    ],
    false,
    false,
    ['ID', 'PROPERTY_user_id']
);

while ($row = $rsResults->GetNext()) {
    $id = (int)$row['ID'];
    if ($ib->Delete($id)) {
        $resultsDeleted++;
    } else {
        echo "Failed to delete result ID={$id}\n";
    }
}

echo "Results deleted for match #{$matchNumber}: {$resultsDeleted}\n";

// --- 3) Deduplicate HL match bets (user + match) ---
$betsDeleted = 0;
$betsGroups = 0;

if (\Bitrix\Main\Loader::includeModule('prognos9ys.main')) {
    try {
        $repo = new \Prognos9ys\Main\Model\Repository\GameEconomyRepository();
        $dc = $repo->getMatchBetDataClass();
        $betGroups = [];

        $rsBets = $dc::getList([
            'order' => ['ID' => 'ASC'],
            'select' => ['*'],
        ]);

        while ($bet = $rsBets->fetch()) {
            $userId = (int)($bet['UF_USER_ID'] ?? 0);
            $betMatchId = (int)($bet['UF_MATCH_ID'] ?? 0);
            if ($userId <= 0 || $betMatchId <= 0) {
                continue;
            }
            $betGroups[$userId . ':' . $betMatchId][] = $bet;
        }

        foreach ($betGroups as $rows) {
            if (count($rows) <= 1) {
                continue;
            }

            $betsGroups++;
            usort($rows, static function (array $a, array $b): int {
                $payoutCmp = ((float)($b['UF_PAYOUT'] ?? 0)) <=> ((float)($a['UF_PAYOUT'] ?? 0));
                if ($payoutCmp !== 0) {
                    return $payoutCmp;
                }

                $statusScore = static function (array $row): int {
                    $status = (string)($row['UF_STATUS'] ?? '');
                    if ($status === 'won') {
                        return 3;
                    }
                    if ($status === 'pending') {
                        return 2;
                    }
                    if ($status === 'lost') {
                        return 1;
                    }

                    return 0;
                };

                $statusCmp = $statusScore($b) <=> $statusScore($a);
                if ($statusCmp !== 0) {
                    return $statusCmp;
                }

                return ((int)($b['ID'] ?? 0)) <=> ((int)($a['ID'] ?? 0));
            });

            $keep = array_shift($rows);
            foreach ($rows as $row) {
                $deleteResult = $dc::delete((int)$row['ID']);
                if ($deleteResult->isSuccess()) {
                    $betsDeleted++;
                } else {
                    echo 'Failed to delete bet ID=' . (int)$row['ID'] . ': ' . implode('; ', $deleteResult->getErrorMessages()) . "\n";
                }
            }
        }
    } catch (\Throwable $exception) {
        echo 'Bet cleanup error: ' . $exception->getMessage() . "\n";
    }
}

echo "HL bets duplicates: groups={$betsGroups}, removed={$betsDeleted}\n";
echo "Done.\n";
