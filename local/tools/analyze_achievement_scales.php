<?php
declare(strict_types=1);

/**
 * Анализ шкал ачивок по когорте пользователей + опциональная выдача наград.
 *
 *   php analyze_achievement_scales.php --from 2 --to 30
 *   php analyze_achievement_scales.php --from 2 --to 30 --grant
 */

$docRoot = dirname(__DIR__, 2);
$_SERVER['DOCUMENT_ROOT'] = $docRoot;
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['SERVER_NAME'] = $_SERVER['SERVER_NAME'] ?? 'localhost';

define('NO_KEEP_STATISTIC', true);
define('NO_AGENT_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

require_once $docRoot . '/bitrix/modules/main/include/prolog_before.php';

use Prognos9ys\Main\Service\Game\AchievementConfig;
use Prognos9ys\Main\Service\Game\AchievementService;
use Prognos9ys\Main\Service\Game\GameEventScopeService;

if (!\Bitrix\Main\Loader::includeModule('prognos9ys.main')) {
    echo "prognos9ys.main module not loaded\n";
    exit(1);
}

$from = 2;
$to = 30;
$doGrant = false;

for ($i = 1; $i < $argc; $i++) {
    $arg = $argv[$i];
    if ($arg === '--grant') {
        $doGrant = true;
        continue;
    }
    if (strpos($arg, '--from=') === 0) {
        $from = (int)substr($arg, 7);
        continue;
    }
    if (strpos($arg, '--to=') === 0) {
        $to = (int)substr($arg, 5);
        continue;
    }
    if ($arg === '--from' && isset($argv[$i + 1])) {
        $from = (int)$argv[++$i];
        continue;
    }
    if ($arg === '--to' && isset($argv[$i + 1])) {
        $to = (int)$argv[++$i];
        continue;
    }
}

if ($from > $to || $from <= 0) {
    echo "Usage: php analyze_achievement_scales.php --from 2 --to 30 [--grant]\n";
    exit(1);
}

$service = new AchievementService();
$scope = new GameEventScopeService();
$catalog = AchievementConfig::getCatalog();

echo "=== Achievement scale analysis ===\n";
echo 'WC26: ' . $scope->getAnchorEventId() . ' (' . $scope->getEventName($scope->getAnchorEventId()) . ")\n";
echo "Users: {$from}..{$to}" . ($doGrant ? ' [GRANT]' : '') . "\n\n";

/** @var array<int, array> */
$userSnapshots = [];
$totalGranted = 0;
$prognosisCounts = [];

for ($userId = $from; $userId <= $to; $userId++) {
    if ($doGrant) {
        $granted = $service->grantMissedRewards($userId);
        $totalGranted += count($granted);
        if ($granted) {
            echo "granted user {$userId}: " . count($granted) . " level(s)\n";
        }
    }

    $data = $service->getForUser($userId);
    $prognosis = (int)($data['stats']['football_prognosis'] ?? 0);
    if ($prognosis > 0) {
        $prognosisCounts[] = $prognosis;
    }

    $userSnapshots[$userId] = $data;
}

if ($doGrant) {
    echo "\nTotal granted levels: {$totalGranted}\n\n";
}

$activeUsers = count($prognosisCounts);
$prognosisMin = $prognosisCounts ? min($prognosisCounts) : 0;
$prognosisMax = $prognosisCounts ? max($prognosisCounts) : 0;
$prognosisAvg = $prognosisCounts ? round(array_sum($prognosisCounts) / count($prognosisCounts), 1) : 0;

echo "=== Cohort activity ===\n";
echo "Users with WC26 prognosis: {$activeUsers} / " . ($to - $from + 1) . "\n";
echo "Prognosis per user: min={$prognosisMin} avg={$prognosisAvg} max={$prognosisMax}\n\n";

/** @var array<string, array{
 *   title:string,
 *   thresholds:int[],
 *   progresses:int[],
 *   claimed_levels:int[],
 *   unlocked_levels:int[],
 *   claimable:int
 * }> */
$agg = [];

foreach ($catalog as $code => $def) {
    $thresholds = array_map(static fn($l) => (int)($l['threshold'] ?? 0), $def['levels'] ?? []);
    $agg[$code] = [
        'title' => (string)($def['title'] ?? $code),
        'thresholds' => $thresholds,
        'progresses' => [],
        'claimed_levels' => [],
        'unlocked_levels' => [],
        'claimable' => 0,
    ];
}

foreach ($userSnapshots as $data) {
    foreach ($data['items'] ?? [] as $item) {
        $code = (string)($item['code'] ?? '');
        if (!isset($agg[$code])) {
            continue;
        }

        $progress = (int)($item['progress'] ?? 0);
        $claimed = (int)($item['claimed_threshold'] ?? 0);
        $thresholds = $agg[$code]['thresholds'];

        $unlocked = 0;
        $claimedLevels = 0;
        foreach ($thresholds as $t) {
            if ($t > 0 && $progress >= $t) {
                $unlocked++;
            }
            if ($t > 0 && $claimed >= $t) {
                $claimedLevels++;
            }
        }

        $agg[$code]['progresses'][] = $progress;
        $agg[$code]['unlocked_levels'][] = $unlocked;
        $agg[$code]['claimed_levels'][] = $claimedLevels;

        if ((int)($item['next_claimable_threshold'] ?? 0) > 0) {
            $agg[$code]['claimable']++;
        }
    }
}

function tierLabel(int $unlocked, int $total): string
{
    if ($total <= 0) {
        return '0';
    }
    if ($unlocked <= 0) {
        return '0/' . $total;
    }
    if ($unlocked >= $total) {
        return 'MAX';
    }

    return $unlocked . '/' . $total;
}

function median(array $values): float
{
    if (!$values) {
        return 0.0;
    }
    sort($values);
    $n = count($values);
    $mid = (int)floor($n / 2);

    return $n % 2 === 0
        ? ($values[$mid - 1] + $values[$mid]) / 2
        : (float)$values[$mid];
}

echo str_pad('ACHIEVEMENT', 24)
    . str_pad('PROG', 12)
    . str_pad('TIER', 8)
    . str_pad('CLAIM', 8)
    . str_pad('WAIT', 6)
    . "THRESHOLDS / pace\n";
echo str_repeat('-', 110) . "\n";

/** @var list<array{code:string,pace:float,title:string}> */
$paceRanking = [];

foreach ($agg as $code => $row) {
    $progresses = $row['progresses'];
    $unlocked = $row['unlocked_levels'];
    $claimed = $row['claimed_levels'];
    $thresholds = $row['thresholds'];
    $tierTotal = count($thresholds);

    $progMax = $progresses ? max($progresses) : 0;
    $progAvg = $progresses ? round(array_sum($progresses) / count($progresses), 1) : 0;
    $progMed = round(median($progresses), 1);

    $tierAvg = $unlocked ? round(array_sum($unlocked) / count($unlocked), 2) : 0;
    $tierMax = $unlocked ? max($unlocked) : 0;
    $claimAvg = $claimed ? round(array_sum($claimed) / count($claimed), 2) : 0;

    $active = count(array_filter($progresses, static fn($p) => $p > 0));
    $pace = $prognosisAvg > 0 ? round($progMed / $prognosisAvg, 3) : 0.0;
    $paceRanking[] = ['code' => $code, 'pace' => $pace, 'title' => $row['title']];

    $thresholdStr = implode('/', $thresholds);

    $nextGap = '';
    if ($thresholds && $progMed > 0) {
        $nextIdx = min((int)floor($tierAvg), $tierTotal - 1);
        $nextThr = $thresholds[$nextIdx] ?? end($thresholds);
        if ($progMed < $nextThr) {
            $nextGap = '~' . max(0, $nextThr - (int)$progMed) . ' to T' . ($nextIdx + 1);
        } elseif ($tierMax < $tierTotal) {
            $hi = $thresholds[min($tierMax, $tierTotal - 1)] ?? 0;
            $nextGap = $hi > 0 ? 'past T' . $tierMax : '';
        } else {
            $nextGap = 'saturated';
        }
    }

    echo str_pad(mb_substr($row['title'], 0, 22), 24)
        . str_pad("med {$progMed} max {$progMax}", 12)
        . str_pad(tierLabel((int)round($tierAvg), $tierTotal), 8)
        . str_pad((string)$claimAvg, 8)
        . str_pad((string)$row['claimable'], 6)
        . $thresholdStr
        . ($nextGap ? " | {$nextGap}" : '')
        . "\n";
}

usort($paceRanking, static fn($a, $b) => $b['pace'] <=> $a['pace']);

echo "\n=== Fastest scales (median progress / avg prognosis) ===\n";
foreach (array_slice($paceRanking, 0, 8) as $item) {
    if ($item['pace'] <= 0) {
        continue;
    }
    echo sprintf("  %.3f  %s (%s)\n", $item['pace'], $item['title'], $item['code']);
}

echo "\n=== Slowest scales ===\n";
$slow = array_filter($paceRanking, static fn($i) => $i['pace'] >= 0);
usort($slow, static fn($a, $b) => $a['pace'] <=> $b['pace']);
foreach (array_slice(array_values($slow), 0, 8) as $item) {
    echo sprintf("  %.3f  %s (%s)\n", $item['pace'], $item['title'], $item['code']);
}

echo "\n=== Balance hints ===\n";
foreach ($agg as $code => $row) {
    $tierTotal = count($row['thresholds']);
    $tierMax = $row['unlocked_levels'] ? max($row['unlocked_levels']) : 0;
    $progMax = $row['progresses'] ? max($row['progresses']) : 0;
    $active = count(array_filter($row['progresses'], static fn($p) => $p > 0));

    if ($active === 0) {
        echo "  [DEAD] {$row['title']}: nobody has progress — thresholds may be too high or stat broken\n";
        continue;
    }
    if ($tierMax >= $tierTotal && $active >= max(3, (int)($activeUsers * 0.5))) {
        echo "  [FAST] {$row['title']}: many users already MAX ({$tierMax}/{$tierTotal}) — consider raising thresholds\n";
        continue;
    }
    if ($tierMax <= 1 && $progMax > 0 && $active >= 3) {
        echo "  [SLOW] {$row['title']}: max tier {$tierMax}/{$tierTotal}, prog max {$progMax} — early tiers OK, late tiers very far\n";
    }
}

echo "\nDone.\n";
