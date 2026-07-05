<?php
declare(strict_types=1);

/**
 * Диагностика госстроек города (stash, остаток по новому BOM, %).
 *
 *   php local/tools/diag_city_construction.php
 *   php local/tools/diag_city_construction.php --city=cpv
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

use Prognos9ys\Main\Model\Repository\CityRepository;
use Prognos9ys\Main\Model\Repository\ProfessionRepository;
use Prognos9ys\Main\Service\Game\EstateCityConfig;
use Prognos9ys\Main\Service\Game\EstateRecipesConfig;
use Prognos9ys\Main\Service\Game\ProfessionCraftedItemConfig;

$argv = $_SERVER['argv'] ?? [];
array_shift($argv);

$citySlug = 'cpv';
foreach ($argv as $arg) {
    if (strpos($arg, '--city=') === 0) {
        $citySlug = strtolower(trim(substr($arg, 7)));
    }
}

$cityRepo = new CityRepository();
$professionRepo = new ProfessionRepository();
$city = $cityRepo->getCityBySlug($citySlug);

echo "=== city {$citySlug} (" . EstateCityConfig::getCityName($citySlug) . ") ===\n";
if (!$city) {
    echo "City row not found in HL (status=planned or not started).\n";
    exit(0);
}

echo 'status: ' . (string)($city['UF_STATUS'] ?? '') . "\n";
echo 'founded_at: ' . formatDt($city['UF_FOUNDED_AT'] ?? null) . "\n";
echo 'opened_at: ' . formatDt($city['UF_OPENED_AT'] ?? null) . "\n\n";

$projects = $professionRepo->getConstructionProjectsByCity($citySlug);
$byRecipe = [];
foreach ($projects as $project) {
    $byRecipe[(string)($project['UF_RECIPE_CODE'] ?? '')] = $project;
}

foreach (EstateCityConfig::FOUNDING_BUILDINGS as $recipeCode) {
    $recipe = EstateRecipesConfig::all()[$recipeCode] ?? null;
    if ($recipe === null) {
        continue;
    }

    $bom = (array)($recipe['components'] ?? []);
    $project = $byRecipe[$recipeCode] ?? null;

    echo "--- {$recipeCode} :: " . (string)($recipe['label_ru'] ?? $recipeCode) . " ---\n";
    if (!$project) {
        echo "  project: (no HL row)\n";
        echo "  nominal_total: " . (float)($recipe['nominal_total'] ?? 0) . "\n\n";
        continue;
    }

    $stash = $professionRepo->decodeStashJson($project['UF_STASH_JSON'] ?? '{}');
    $remaining = calcRemaining($bom, $stash);
    $progress = calcProgressPct($bom, $stash);
    $storedProgress = (int)($project['UF_PROGRESS'] ?? 0);
    $status = (string)($project['UF_STATUS'] ?? 'building');
    $isComplete = $remaining === [];

    echo '  project_id: ' . (int)($project['ID'] ?? 0) . "\n";
    echo "  db_status: {$status}, db_progress: {$storedProgress}%, calc_progress: {$progress}%\n";
    echo '  complete: ' . ($isComplete ? 'yes' : 'no') . "\n";
    echo '  nominal_total: ' . (float)($recipe['nominal_total'] ?? 0) . "\n";

    if ($stash !== []) {
        echo "  stash:\n";
        foreach ($stash as $code => $qty) {
            $inBom = isset($bom[$code]) ? 'in BOM' : 'orphan';
            echo "    {$code} ×{$qty} ({$inBom}) — " . ProfessionCraftedItemConfig::getLabel((string)$code) . "\n";
        }
    } else {
        echo "  stash: (empty)\n";
    }

    if ($remaining !== []) {
        echo "  remaining:\n";
        foreach ($remaining as $code => $qty) {
            echo "    {$code} ×{$qty} — " . ProfessionCraftedItemConfig::getLabel($code) . "\n";
        }
    } else {
        echo "  remaining: none\n";
    }

    echo "\n";
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

/**
 * @param mixed $value
 */
function formatDt($value): string
{
    if ($value instanceof \Bitrix\Main\Type\DateTime) {
        return $value->format('Y-m-d H:i:s');
    }

    return trim((string)$value);
}
