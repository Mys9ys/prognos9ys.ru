<?php
declare(strict_types=1);

/**
 * Выдача запечатанных паков рецептов (компенсация / тест).
 *
 *   php local/tools/grant_recipe_packs.php <login|userId>
 *   php local/tools/grant_recipe_packs.php Mys9ysilii
 *
 * По умолчанию: ×1 базовый, ×1 продвинутый, ×1 рабочий экипировки.
 */

$docRoot = dirname(__DIR__, 2);
$_SERVER['DOCUMENT_ROOT'] = $docRoot;
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['SERVER_NAME'] = $_SERVER['SERVER_NAME'] ?? 'localhost';

define('NO_KEEP_STATISTIC', true);
define('NO_AGENT_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

require_once $docRoot . '/bitrix/modules/main/include/prolog_before.php';

use Prognos9ys\Main\Model\Repository\GameEconomyRepository;
use Prognos9ys\Main\Service\Game\ChestLootConfig;
use Prognos9ys\Main\Service\Game\ProfessionRecipeConfig;

if (!\Bitrix\Main\Loader::includeModule('prognos9ys.main')) {
    echo "prognos9ys.main module not loaded\n";
    exit(1);
}

$arg = isset($argv[1]) ? trim((string)$argv[1]) : '';
if ($arg === '') {
    echo "Usage: php local/tools/grant_recipe_packs.php <login|userId>\n";
    exit(1);
}

$userId = ctype_digit($arg) ? (int)$arg : 0;
if ($userId <= 0) {
    $rs = \CUser::GetList('id', 'asc', ['LOGIN_EQUAL' => $arg], ['FIELDS' => ['ID', 'LOGIN']]);
    $row = $rs->Fetch();
    if (!$row) {
        echo "User not found: {$arg}\n";
        exit(1);
    }
    $userId = (int)$row['ID'];
    $login = (string)$row['LOGIN'];
} else {
    $rs = \CUser::GetByID($userId);
    $row = $rs->Fetch();
    if (!$row) {
        echo "User not found: {$userId}\n";
        exit(1);
    }
    $login = (string)($row['LOGIN'] ?? $userId);
}

$packs = [
    ProfessionRecipeConfig::PACK_RECIPE_BASIC => 1,
    ProfessionRecipeConfig::PACK_RECIPE_ADVANCED => 1,
    ProfessionRecipeConfig::PACK_EQUIPMENT_WORK => 1,
];

$repository = new GameEconomyRepository();
$eventId = ChestLootConfig::LOOT_EVENT_GLOBAL;

foreach ($packs as $packCode => $qty) {
    $repository->incrementLootItem(
        $userId,
        $eventId,
        $packCode,
        ChestLootConfig::CATEGORY_PACK,
        $qty,
        'Y'
    );
    echo "  +{$qty} " . ChestLootConfig::getLabel($packCode) . " ({$packCode})\n";
}

echo "Granted recipe packs to {$login} (user {$userId})\n";
