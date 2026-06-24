<?php
declare(strict_types=1);

/**
 * Проверка лавки казны на этапах 40, 50, 60…
 *
 *   php local/tools/diag_treasury_shop_milestone.php
 *   php local/tools/diag_treasury_shop_milestone.php 50
 *   php local/tools/diag_treasury_shop_milestone.php 50 --dry-run
 *   php local/tools/diag_treasury_shop_milestone.php --match-id=12345 --apply
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

use Prognos9ys\Main\Model\Repository\GameEconomyRepository;
use Prognos9ys\Main\Service\Game\GameEconomyConfig;
use Prognos9ys\Main\Service\Game\GameEventScopeService;
use Prognos9ys\Main\Service\Game\MatchEconomySettlementService;
use Prognos9ys\Main\Service\Game\TreasuryShopService;

$argv = $_SERVER['argv'] ?? [];
$dryRun = !in_array('--apply', $argv, true);
$milestone = 0;
$matchId = 0;

foreach ($argv as $arg) {
    if (is_numeric($arg)) {
        $milestone = (int)$arg;
    }
    if (str_starts_with((string)$arg, '--match-id=')) {
        $matchId = (int)substr((string)$arg, strlen('--match-id='));
    }
}

$scope = new GameEventScopeService();
$repo = new GameEconomyRepository();
$shop = new TreasuryShopService();
$eventId = $scope->getAnchorEventId();
$lastSettled = (new MatchEconomySettlementService())->getLastSettledMatchForEvent($eventId);

echo "Anchor event: {$eventId}\n";
echo "Last settled match: #{$lastSettled['number']} (id {$lastSettled['id']})\n";
echo "Shop first milestone: " . GameEconomyConfig::TREASURY_SHOP_FIRST_MILESTONE . "\n";
echo "Milestones up to tour: " . implode(', ', GameEconomyConfig::getTreasuryShopMilestonesUpTo((int)$lastSettled['number'])) . "\n\n";

if ($matchId <= 0 && $milestone > 0) {
    if (!\Bitrix\Main\Loader::includeModule('iblock')) {
        fwrite(STDERR, "iblock required\n");
        exit(1);
    }

    $row = \CIBlockElement::GetList(
        [],
        [
            'IBLOCK_ID' => 2,
            'PROPERTY_events' => $eventId,
            'PROPERTY_number' => $milestone,
        ],
        false,
        ['nTopCount' => 1],
        ['ID', 'PROPERTY_number']
    )->Fetch();

    $matchId = (int)($row['ID'] ?? 0);
    if ($matchId <= 0) {
        fwrite(STDERR, "Match with number {$milestone} not found for event {$eventId}\n");
        exit(1);
    }
}

if ($matchId <= 0) {
    $milestone = $milestone > 0 ? $milestone : 50;
    $prev = $milestone - GameEconomyConfig::TREASURY_SHOP_MILESTONE_STEP;
    $completedPrev = $repo->getUserIdsWithCompletedTreasuryShopMilestone($prev);
    $wavesAtMilestone = 0;

    if ($milestone === GameEconomyConfig::TREASURY_SHOP_FIRST_MILESTONE) {
        $eligible = $repo->getDistinctWalletUserIds();
    } else {
        $eligible = $completedPrev;
    }

    foreach ($eligible as $userId) {
        if ($repo->getTreasuryShopWave($userId, $milestone)) {
            $wavesAtMilestone++;
        }
    }

    echo "Preview milestone {$milestone} (no match id):\n";
    echo "  Eligible players: " . count($eligible) . "\n";
    if ($milestone > GameEconomyConfig::TREASURY_SHOP_FIRST_MILESTONE) {
        echo "  Completed prev wave {$prev}: " . count($completedPrev) . "\n";
    }
    echo "  Existing wave rows at {$milestone}: {$wavesAtMilestone}\n";
    echo "  Would create: " . max(0, count($eligible) - $wavesAtMilestone) . "\n";
    echo "\nPass match number or --match-id=... to run provisionWavesForSettledMatch.\n";
    exit(0);
}

$matchNumber = $scope->getMatchNumber($matchId);
echo "Match id {$matchId}, number {$matchNumber}\n";
echo 'Mode: ' . ($dryRun ? "dry-run (add --apply to write)\n" : "APPLY\n");

$result = $shop->provisionWavesForSettledMatch($matchId, $dryRun);

echo "\nProvision result:\n";
foreach ($result as $key => $value) {
    echo "  {$key}: " . (is_scalar($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE)) . "\n";
}

if (!empty($result['log_text'])) {
    echo "\nLog line: {$result['log_text']}\n";
}
