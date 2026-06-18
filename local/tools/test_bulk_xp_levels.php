<?php

declare(strict_types=1);

use Prognos9ys\Main\Service\Game\GameEconomyConfig;
use Prognos9ys\Main\Service\Game\LevelService;

$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

if (!\Bitrix\Main\Loader::includeModule('prognos9ys.main')) {
    fwrite(STDERR, "prognos9ys.main not loaded\n");
    exit(1);
}

$levelService = new LevelService();

$cases = [
  ['matches' => 80, 'avg_points' => 10.0],
  ['matches' => 80, 'avg_points' => 25.0],
  ['matches' => 80, 'avg_points' => 50.0],
];

foreach ($cases as $case) {
    $xp = round($case['matches'] * $case['avg_points'], 1);
    $from = $levelService->getProgressSummary(0);
    $to = $levelService->getProgressSummary($xp);
    $oldLevel = (int)$from['level'];
    $newLevel = (int)$to['level'];
    $levelsGained = [];

    for ($level = $oldLevel + 1; $level <= $newLevel; $level++) {
        $levelsGained[] = $level;
    }

    echo sprintf(
        "%d matches x %.1f XP = %.1f total => levels %d -> %d (%s)\n",
        $case['matches'],
        $case['avg_points'],
        $xp,
        $oldLevel,
        $newLevel,
        $levelsGained ? implode(',', $levelsGained) : 'none'
    );
}

$rewardLevels = range(1, 15);
echo "Level-up rewards for levels 1-15: " . count($rewardLevels) . " entries\n";
