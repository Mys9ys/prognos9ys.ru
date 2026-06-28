<?php
declare(strict_types=1);

/**
 * Диагностика госсклада и фарма по профессиям.
 *
 *   php local/tools/diag_gov_warehouse.php
 *   php local/tools/diag_gov_warehouse.php --fix   # схлопнуть дубли UF_MATERIAL_CODE
 */

$fix = in_array('--fix', $argv ?? [], true);

$docRoot = dirname(__DIR__, 2);
$_SERVER['DOCUMENT_ROOT'] = $docRoot;
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['SERVER_NAME'] = $_SERVER['SERVER_NAME'] ?? 'localhost';

define('NO_KEEP_STATISTIC', true);
define('NO_AGENT_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

require_once $docRoot . '/bitrix/modules/main/include/prolog_before.php';

use Prognos9ys\Main\Model\Repository\ProfessionRepository;
use Prognos9ys\Main\Service\Game\ProfessionMaterialConfig;

if (!\Bitrix\Main\Loader::includeModule('prognos9ys.main')) {
    echo "prognos9ys.main module not loaded\n";
    exit(1);
}

$repo = new ProfessionRepository();

echo "=== Gov warehouse rows ===\n";
if ($fix) {
    $merged = $repo->mergeDuplicateGovWarehouseRows();
    if ($merged) {
        echo "Merged duplicates: " . json_encode($merged, JSON_UNESCAPED_UNICODE) . "\n";
    }
}
$qtyMap = $repo->getGovWarehouseQtyMap();
$warehouseTotal = 0;
foreach ($qtyMap as $code => $qty) {
    $warehouseTotal += $qty;
    $label = ProfessionMaterialConfig::materialCatalog()[$code]['label'] ?? $code;
    $inCatalog = ProfessionMaterialConfig::materialCatalog()[$code] ?? null;
    $flag = $inCatalog ? '' : ' [ORPHAN - not in catalog]';
    echo sprintf("  %s (%s): %d%s\n", $code, $label, $qty, $flag);
}
echo "Sum: {$warehouseTotal}\n\n";

echo "=== Raw HL rows (after merge) ===\n";
foreach ($repo->getGovWarehouseRows() as $row) {
    echo sprintf(
        "  id=%d %s: %d\n",
        (int)($row['ID'] ?? 0),
        (string)($row['UF_MATERIAL_CODE'] ?? ''),
        (int)($row['UF_QTY'] ?? 0)
    );
}
echo "\n";

echo "=== Professions by code ===\n";
$profCounts = [];
$dataClass = (new ReflectionClass($repo))->getMethod('getUserProfessionDataClass');
$dataClass->setAccessible(true);
$profClass = $dataClass->invoke($repo);
$response = $profClass::getList(['select' => ['UF_PROFESSION_CODE']]);
while ($row = $response->fetch()) {
    $code = (string)($row['UF_PROFESSION_CODE'] ?? '');
    $profCounts[$code] = ($profCounts[$code] ?? 0) + 1;
}
arsort($profCounts);
foreach ($profCounts as $code => $cnt) {
    echo "  {$code}: {$cnt}\n";
}
echo "\n";

echo "=== Completed treasury sessions (from HL) ===\n";
$sessionMethod = (new ReflectionClass($repo))->getMethod('getProfessionSessionDataClass');
$sessionMethod->setAccessible(true);
$sessionClass = $sessionMethod->invoke($repo);
$byProf = [];
$byOutput = [];
$treasuryPay = 0.0;
$response = $sessionClass::getList([
    'filter' => ['=UF_STATUS' => ProfessionMaterialConfig::SESSION_STATUS_COMPLETED],
    'select' => ['UF_PROFESSION_CODE', 'UF_WORK_MODE', 'UF_ITERATIONS_TOTAL', 'UF_LAST_RESULT_JSON'],
]);
while ($row = $response->fetch()) {
    $mode = (string)($row['UF_WORK_MODE'] ?? '');
    $prof = (string)($row['UF_PROFESSION_CODE'] ?? '');
    $iter = (int)($row['UF_ITERATIONS_TOTAL'] ?? 0);
    $json = json_decode((string)($row['UF_LAST_RESULT_JSON'] ?? ''), true);
    if ($mode === ProfessionMaterialConfig::WORK_MODE_TREASURY) {
        $byProf[$prof] = ($byProf[$prof] ?? 0) + $iter;
        $out = (string)($json['output_code'] ?? '');
        $gov = (int)($json['gov_output_qty'] ?? 0);
        if ($out !== '') {
            $byOutput[$out] = ($byOutput[$out] ?? 0) + $gov;
        }
        $treasuryPay += (float)($json['pay_coins'] ?? 0);
    }
}
arsort($byProf);
foreach ($byProf as $prof => $iter) {
    echo "  {$prof}: {$iter} iterations\n";
}
echo "\nGov output by material code (from session JSON):\n";
arsort($byOutput);
foreach ($byOutput as $code => $qty) {
    echo "  {$code}: {$qty}\n";
}
echo "\nTreasury pay from sessions (sum JSON): {$treasuryPay}\n";
