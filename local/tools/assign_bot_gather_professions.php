<?php
declare(strict_types=1);

/**
 * Назначить добывающую профессию (если ещё нет).
 *
 *   php local/tools/assign_bot_gather_professions.php --dry-run
 *   php local/tools/assign_bot_gather_professions.php --confirm
 *   php local/tools/assign_bot_gather_professions.php --info
 *   php local/tools/assign_bot_gather_professions.php --confirm --profile=cotton_heavy
 *   php local/tools/assign_bot_gather_professions.php --confirm --seed-only
 *   php local/tools/assign_bot_gather_professions.php --confirm 12345
 */

$docRoot = dirname(__DIR__, 2);
$_SERVER['DOCUMENT_ROOT'] = $docRoot;
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['SERVER_NAME'] = $_SERVER['SERVER_NAME'] ?? 'localhost';

define('NO_KEEP_STATISTIC', true);
define('NO_AGENT_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

require_once $docRoot . '/bitrix/modules/main/include/prolog_before.php';

use Prognos9ys\Main\Service\Game\BotFarmService;
use Prognos9ys\Main\Service\Game\BotProfessionPickConfig;
use Prognos9ys\Main\Service\Game\ProfessionMaterialConfig;

if (!\Bitrix\Main\Loader::includeModule('prognos9ys.main')) {
    echo "prognos9ys.main module not loaded\n";
    exit(1);
}

$dryRun = in_array('--dry-run', $argv ?? [], true);
$confirm = in_array('--confirm', $argv ?? [], true);
$showInfo = in_array('--info', $argv ?? [], true);
$seedOnly = in_array('--seed-only', $argv ?? [], true);
$targetUserId = 0;
$profile = BotProfessionPickConfig::DEFAULT_PROFILE;

foreach ($argv ?? [] as $arg) {
    if (is_numeric($arg) && (int)$arg > 0) {
        $targetUserId = (int)$arg;
    }
    if (strpos($arg, '--profile=') === 0) {
        $profile = substr($arg, strlen('--profile='));
    }
}

if ($showInfo) {
    echo "Профили распределения профессий для ботов:\n\n";
    foreach (BotProfessionPickConfig::profileLabels() as $code => $label) {
        $mark = $code === BotProfessionPickConfig::DEFAULT_PROFILE ? ' [default]' : '';
        echo "  {$code}{$mark}: {$label}\n";
        foreach (BotProfessionPickConfig::gatheringWeights($code) as $profCode => $weight) {
            $profLabel = ProfessionMaterialConfig::getProfession($profCode)['label'] ?? $profCode;
            echo "    - {$profLabel} ({$profCode}): {$weight}%\n";
        }
        echo "\n";
    }

    echo "Спрос рецептов усадьбы/казны (без хлопка), ед. материалов:\n";
    foreach (BotProfessionPickConfig::estateRecipeGatherDemand() as $code => $qty) {
        if ($code === 'cottongatherer') {
            continue;
        }
        $profLabel = ProfessionMaterialConfig::getProfession($code)['label'] ?? $code;
        $pct = BotProfessionPickConfig::estateRecipeGatherDemandPercents()[$code] ?? 0;
        echo "  {$profLabel}: {$qty} (~{$pct}%)\n";
    }
    echo "\nХлопок в рецептах пока 0 — в профилях добавлен отдельно (8–17%).\n";
    exit(0);
}

if (!$dryRun && !$confirm) {
    echo "Назначение добывающих профессий.\n";
    echo "  --info              профили и доли\n";
    echo "  --dry-run           без записи\n";
    echo "  --confirm           выполнить\n";
    echo "  --profile=...       recipe_cotton_10 | cotton_heavy | round\n";
    echo "  --seed-only         только seed-аккаунты (@prognos9ys.ru)\n";
    echo "  12345               один userId\n";
    exit(1);
}

if (!isset(BotProfessionPickConfig::profileLabels()[$profile])) {
    echo "Unknown profile: {$profile}\n";
    exit(1);
}

$service = new BotFarmService();
$ids = $targetUserId > 0
    ? [$targetUserId]
    : ($seedOnly ? $service->listSeedUserIds() : $service->listWalletUserIds());

$scopeLabel = $seedOnly ? 'Seed bots' : 'Wallet users';
echo ($dryRun ? '[DRY RUN] ' : '') . "Profile: {$profile}\n";
echo "{$scopeLabel}: " . count($ids) . "\n\n";

$stats = ['success' => 0, 'skipped' => 0, 'failed' => 0];

foreach ($ids as $userId) {
    if ($dryRun) {
        if ($seedOnly && !$service->isSeedUser($userId)) {
            echo "Skip #{$userId}: not seed\n";
            $stats['skipped']++;
            continue;
        }
        if ($service->userHasGatherProfession($userId)) {
            echo "Skip #{$userId}: already has profession\n";
            $stats['skipped']++;
            continue;
        }
        $code = BotProfessionPickConfig::pickGatheringCodeForUser($userId, $profile);
        $label = ProfessionMaterialConfig::getProfession($code)['label'] ?? $code;
        echo "Would assign #{$userId}: {$label} ({$code})\n";
        $stats['success']++;
        continue;
    }

    $result = $service->pickGatherProfessionIfMissing($userId, $profile);
    $status = (string)($result['status'] ?? 'failed');
    $stats[$status === 'success' ? 'success' : ($status === 'skipped' ? 'skipped' : 'failed')]++;
    echo ucfirst($status) . " #{$userId}: " . ($result['message'] ?? '') . "\n";
}

echo "\nDone: success={$stats['success']}, skipped={$stats['skipped']}, failed={$stats['failed']}\n";
