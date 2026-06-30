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

use Prognos9ys\Main\Model\Repository\GameEconomyRepository;
use Prognos9ys\Main\Service\Game\AlbumService;
use Prognos9ys\Main\Service\Game\GameEventScopeService;
use Prognos9ys\Main\Service\Game\GameProfileService;

$userId = (int)($_SERVER['argv'][1] ?? 0);
if ($userId <= 0) {
    echo "Usage: php diag_album_perf.php <user_id>\n";
    exit(1);
}

$repo = new GameEconomyRepository();
$scope = new GameEventScopeService();
$globalStacks = $repo->getLootItemStacksForUser($userId, 0);
$eventStacks = $repo->getLootItemStacksForUser($userId, $scope->getAnchorEventId());

echo "User #{$userId}\n";
echo 'Loot stacks (global): ' . count($globalStacks) . "\n";
echo 'Loot stacks (event):  ' . count($eventStacks) . "\n";
echo str_repeat('-', 48) . "\n";

$bench = static function (string $label, callable $fn): void {
    $t0 = microtime(true);
    $result = $fn();
    $ms = round((microtime(true) - $t0) * 1000, 1);
    $bytes = is_array($result) ? strlen(json_encode($result, JSON_UNESCAPED_UNICODE)) : 0;
    echo sprintf("%-28s %6.1f ms  ~%s KB\n", $label, $ms, number_format($bytes / 1024, 1));
};

$bench('GameProfileService::getSummary', static function () use ($userId) {
    return (new GameProfileService())->getSummary($userId);
});

$bench('GameProfileService::getMutationSummary', static function () use ($userId) {
    return (new GameProfileService())->getMutationSummary($userId);
});

$bench('AlbumService::getState', static function () use ($userId) {
    return (new AlbumService())->getState($userId);
});

$bench('AlbumService::getProfileMeta', static function () use ($userId) {
    return (new AlbumService())->getProfileMeta($userId);
});
