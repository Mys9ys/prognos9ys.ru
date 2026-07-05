<?php
declare(strict_types=1);

/**
 * Откат устаревших кафтанов и рецептов (до профессиональной системы):
 * — снять изученные legacy-рецепты, вернуть pack_equipment_work (запечатанный, можно открыть снова);
 * — удалить сшитые legacy-кафтаны из инвентаря;
 * — снять legacy-кафтан с тела без возврата в сумку.
 *
 * Dry-run (по умолчанию):
 *   php local/tools/fix_rollback_legacy_caftans.php
 *   php local/tools/fix_rollback_legacy_caftans.php --user=42
 *
 * Применить:
 *   php local/tools/fix_rollback_legacy_caftans.php --confirm
 *   php local/tools/fix_rollback_legacy_caftans.php --user=42 --confirm
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

use Prognos9ys\Main\Model\Repository\GameEconomyRepository;
use Prognos9ys\Main\Service\Game\CaftanRecipeConfig;
use Prognos9ys\Main\Service\Game\ChestLootConfig;
use Prognos9ys\Main\Service\Game\GameEconomyHlInstaller;
use Prognos9ys\Main\Service\Game\ProfessionRecipeConfig;

$argv = $_SERVER['argv'] ?? [];
array_shift($argv);

$userId = 0;
$confirm = false;

foreach ($argv as $arg) {
    if ($arg === '--confirm') {
        $confirm = true;
        continue;
    }
    if (strpos($arg, '--user=') === 0) {
        $userId = (int)substr($arg, 7);
    }
}

$legacy = CaftanRecipeConfig::legacyRollbackTargets();
$legacyRecipes = $legacy['recipes'];
$legacyProducts = $legacy['products'];
$packCode = ProfessionRecipeConfig::PACK_EQUIPMENT_WORK;
$packCategory = ChestLootConfig::CATEGORY_PACK;
$equipmentCategory = ChestLootConfig::CATEGORY_EQUIPMENT;

$economy = new GameEconomyRepository();
$userIds = $userId > 0 ? [$userId] : listProgressUserIds();

if ($userIds === []) {
    echo "No users in progress table.\n";
    exit(0);
}

echo 'Scanning ' . count($userIds) . " user(s). Mode: " . ($confirm ? 'APPLY' : 'dry-run') . "\n\n";

$totalRecipes = 0;
$totalPacks = 0;
$totalProducts = 0;
$totalUnequip = 0;
$affectedUsers = 0;

foreach ($userIds as $uid) {
    $plan = buildUserRollbackPlan($economy, $uid, $legacyRecipes, $legacyProducts);
    if (!$plan['has_changes']) {
        continue;
    }

    $affectedUsers++;
    echo "User #{$uid}\n";
    if ($plan['recipes_to_remove'] !== []) {
        echo '  recipes remove: ' . implode(', ', $plan['recipes_to_remove']) . "\n";
        echo '  packs grant: ' . count($plan['recipes_to_remove']) . " × {$packCode} (sealed)\n";
    }
    if ($plan['products_to_remove'] !== []) {
        foreach ($plan['products_to_remove'] as $code => $qty) {
            echo "  dismantle inventory: {$code} ×{$qty}\n";
        }
    }
    if ($plan['unequip_legacy']) {
        echo '  unequip legacy: ' . $plan['equipped_legacy'] . "\n";
    }

    if ($confirm) {
        if ($plan['recipes_to_remove'] !== []) {
            $removed = $economy->removeLearnedRecipes($uid, $plan['recipes_to_remove']);
            if ($removed > 0) {
                $economy->incrementLootItem(
                    $uid,
                    ChestLootConfig::LOOT_EVENT_GLOBAL,
                    $packCode,
                    $packCategory,
                    $removed,
                    'Y'
                );
            }
        }

        if ($plan['unequip_legacy']) {
            $economy->setEquippedCaftanCode($uid, '');
        }

        foreach ($plan['products_to_remove'] as $code => $qty) {
            $economy->decrementEventAgnosticLootItem($uid, $code, $equipmentCategory, $qty);
        }
    }

    $totalRecipes += count($plan['recipes_to_remove']);
    $totalPacks += count($plan['recipes_to_remove']);
    foreach ($plan['products_to_remove'] as $qty) {
        $totalProducts += $qty;
    }
    if ($plan['unequip_legacy']) {
        $totalUnequip++;
    }

    echo "\n";
}

echo "=== summary ===\n";
echo "affected users: {$affectedUsers}\n";
echo "legacy recipes removed: {$totalRecipes}\n";
echo "packs granted: {$totalPacks}\n";
echo "legacy caftans dismantled: {$totalProducts}\n";
echo "legacy unequips: {$totalUnequip}\n";

if (!$confirm) {
    echo "\nDry-run. Add --confirm to apply.\n";
}

/**
 * @param array<int, string> $legacyRecipes
 * @param array<int, string> $legacyProducts
 * @return array{
 *   has_changes:bool,
 *   recipes_to_remove:array<int,string>,
 *   products_to_remove:array<string,int>,
 *   unequip_legacy:bool,
 *   equipped_legacy:string
 * }
 */
function buildUserRollbackPlan(
    GameEconomyRepository $economy,
    int $userId,
    array $legacyRecipes,
    array $legacyProducts
): array {
    $learned = $economy->getLearnedRecipes($userId);
    $recipesToRemove = array_values(array_intersect($learned, $legacyRecipes));

    $productsToRemove = [];
    foreach ($legacyProducts as $code) {
        $qty = $economy->getEventAgnosticLootItemCount($userId, $code, ChestLootConfig::CATEGORY_EQUIPMENT);
        if ($qty > 0) {
            $productsToRemove[$code] = $qty;
        }
    }

    $equipped = $economy->getEquippedCaftanCode($userId);
    $unequipLegacy = $equipped !== '' && in_array($equipped, $legacyProducts, true);

    return [
        'has_changes' => $recipesToRemove !== [] || $productsToRemove !== [] || $unequipLegacy,
        'recipes_to_remove' => $recipesToRemove,
        'products_to_remove' => $productsToRemove,
        'unequip_legacy' => $unequipLegacy,
        'equipped_legacy' => $equipped,
    ];
}

/**
 * @return array<int, int>
 */
function listProgressUserIds(): array
{
    if (!\Bitrix\Main\Loader::includeModule('highloadblock')) {
        return [];
    }

    $hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getList([
        'filter' => ['=TABLE_NAME' => GameEconomyHlInstaller::TABLE_USER_PROGRESS],
    ])->fetch();
    if (!$hlblock) {
        return [];
    }

    $dataClass = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock)->getDataClass();
    $ids = [];
    $response = $dataClass::getList([
        'select' => ['UF_USER_ID'],
        'filter' => ['>UF_USER_ID' => 0],
        'order' => ['UF_USER_ID' => 'ASC'],
    ]);

    while ($row = $response->fetch()) {
        $uid = (int)($row['UF_USER_ID'] ?? 0);
        if ($uid > 0) {
            $ids[$uid] = $uid;
        }
    }

    return array_values($ids);
}
