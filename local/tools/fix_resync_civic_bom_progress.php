<?php
declare(strict_types=1);

/**
 * После смены BOM госзданий: пересчитать UF_PROGRESS / UF_STATUS по stash + новому рецепту.
 * Stash не меняется: лишние wall_section просто не учитываются в новом BOM.
 *
 *   php local/tools/fix_resync_civic_bom_progress.php --city=cpv
 *   php local/tools/fix_resync_civic_bom_progress.php --city=cpv --confirm
 */

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

use Bitrix\Main\Type\DateTime;
use Prognos9ys\Main\Model\Repository\CityRepository;
use Prognos9ys\Main\Model\Repository\ProfessionRepository;
use Prognos9ys\Main\Service\Game\EstateCityConfig;
use Prognos9ys\Main\Service\Game\EstateRecipesConfig;

$argv = $_SERVER['argv'] ?? [];
array_shift($argv);

$citySlug = 'cpv';
$confirm = false;

foreach ($argv as $arg) {
    if ($arg === '--confirm') {
        $confirm = true;
        continue;
    }
    if (strpos($arg, '--city=') === 0) {
        $citySlug = strtolower(trim(substr($arg, 7)));
    }
}

$cityRepo = new CityRepository();
$professionRepo = new ProfessionRepository();
$city = $cityRepo->getCityBySlug($citySlug);

echo 'Mode: ' . ($confirm ? 'APPLY' : 'dry-run') . "\n";
echo "City: {$citySlug} (" . EstateCityConfig::getCityName($citySlug) . ")\n\n";

if (!$city) {
    echo "City not found.\n";
    exit(0);
}

$cityStatus = (string)($city['UF_STATUS'] ?? '');
$projects = $professionRepo->getConstructionProjectsByCity($citySlug);
$updated = 0;

foreach ($projects as $project) {
    $recipeCode = (string)($project['UF_RECIPE_CODE'] ?? '');
    if (!in_array($recipeCode, EstateCityConfig::FOUNDING_BUILDINGS, true)) {
        continue;
    }

    $recipe = EstateRecipesConfig::all()[$recipeCode] ?? null;
    if ($recipe === null) {
        continue;
    }

    $bom = (array)($recipe['components'] ?? []);
    $stash = $professionRepo->decodeStashJson($project['UF_STASH_JSON'] ?? '{}');
    $remaining = calcRemaining($bom, $stash);
    $isComplete = $remaining === [];
    $newProgress = $isComplete ? 100 : (int)round(calcProgressPct($bom, $stash));
    $newStatus = $isComplete ? 'complete' : 'building';

    $oldProgress = (int)($project['UF_PROGRESS'] ?? 0);
    $oldStatus = (string)($project['UF_STATUS'] ?? 'building');
    $projectId = (int)($project['ID'] ?? 0);

    echo "#{$projectId} {$recipeCode}: {$oldStatus}/{$oldProgress}% -> {$newStatus}/{$newProgress}%\n";
    if ($remaining !== []) {
        echo '  still need: ' . implode(', ', array_map(
            static fn(string $code, int $qty): string => "{$code}×{$qty}",
            array_keys($remaining),
            array_values($remaining)
        )) . "\n";
    }

    if ($oldStatus === $newStatus && $oldProgress === $newProgress) {
        echo "  (unchanged)\n";
        continue;
    }

    if ($confirm) {
        $professionRepo->updateConstructionProject($projectId, [
            'UF_STATUS' => $newStatus,
            'UF_PROGRESS' => $newProgress,
            'UF_UPDATED_AT' => new DateTime(),
        ]);

        if ($isComplete && $recipeCode === 'civic_city_hall' && $cityStatus === EstateCityConfig::STATUS_FOUNDING) {
            $cityRepo->updateCity((int)$city['ID'], [
                'UF_STATUS' => EstateCityConfig::STATUS_OPEN,
                'UF_OPENED_AT' => new DateTime(),
            ]);
            echo "  city opened (city hall complete)\n";
            $cityStatus = EstateCityConfig::STATUS_OPEN;
        }
    }

    $updated++;
    echo "\n";
}

echo "projects to update: {$updated}\n";
if (!$confirm && $updated > 0) {
    echo "Add --confirm to apply.\n";
}

/**
 * @param array<string, int> $bom
 * @param array<string, int> $stash
 * @return array<string, int>
 */
function calcRemaining(array $bom, array $stash): array
{
    $remaining = [];
    foreach ($bom as $code => $need) {
        $need = (int)$need;
        $have = (int)($stash[$code] ?? 0);
        $left = $need - $have;
        if ($left > 0) {
            $remaining[(string)$code] = $left;
        }
    }

    return $remaining;
}

/**
 * @param array<string, int> $bom
 * @param array<string, int> $stash
 */
function calcProgressPct(array $bom, array $stash): float
{
    $totalNeed = 0;
    $totalHave = 0;

    foreach ($bom as $code => $need) {
        $need = (int)$need;
        if ($need <= 0) {
            continue;
        }
        $totalNeed += $need;
        $totalHave += min($need, (int)($stash[$code] ?? 0));
    }

    if ($totalNeed <= 0) {
        return 0.0;
    }

    return round(100.0 * $totalHave / $totalNeed, 1);
}
