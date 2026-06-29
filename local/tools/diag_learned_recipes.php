<?php
declare(strict_types=1);

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

$userId = (int)($_SERVER['argv'][1] ?? 0);
if ($userId <= 0) {
    echo "Usage: php diag_learned_recipes.php <userId>\n";
    exit(1);
}

$repo = new \Prognos9ys\Main\Model\Repository\GameEconomyRepository();
$repo->ensureLearnedRecipesSchema();

$row = $repo->getProgressByUserId($userId);
echo "=== user_progress row (via getProgressByUserId) ===\n";
echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n";

echo "=== getLearnedRecipes ===\n";
echo json_encode($repo->getLearnedRecipes($userId), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n";

echo "=== recipe in inventory ===\n";
echo (string)$repo->getEventAgnosticLootItemCount(
    $userId,
    \Prognos9ys\Main\Service\Game\AlbumConfig::RECIPE_ITEM_CODE,
    \Prognos9ys\Main\Service\Game\ChestLootConfig::CATEGORY_RECIPE
) . "\n\n";

echo "=== album craft state ===\n";
echo json_encode(
    (new \Prognos9ys\Main\Service\Game\AlbumCraftService())->getCraftState($userId),
    JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
) . "\n";
