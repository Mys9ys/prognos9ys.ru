<?php
declare(strict_types=1);

/**
 * Откат ошибочно активированного альбома: снять все вклейки в инвентарь, удалить альбом,
 * вернуть универсальный альбом в рюкзак.
 *
 * Dry-run (по умолчанию):
 *   php local/tools/fix_rollback_album.php --album=55
 *   php local/tools/fix_rollback_album.php --user=42 --album=55
 *   php local/tools/fix_rollback_album.php --login=asenka --album=55
 *   php local/tools/fix_rollback_album.php --name=Асенька --album=55
 *
 * Применить:
 *   php local/tools/fix_rollback_album.php --user 42 --album 55 --confirm
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

use Prognos9ys\Main\Model\Repository\AlbumRepository;
use Prognos9ys\Main\Model\Repository\GameEconomyRepository;
use Prognos9ys\Main\Service\Game\AlbumConfig;
use Prognos9ys\Main\Service\Game\AlbumService;
use Prognos9ys\Main\Service\Game\ChestLootConfig;
use Prognos9ys\Main\Service\Game\Wc26CollectibleConfig;

$argv = $_SERVER['argv'] ?? [];
array_shift($argv);

$userId = 0;
$login = '';
$nameHint = '';
$albumId = 0;
$confirm = false;

foreach ($argv as $arg) {
    if ($arg === '--confirm') {
        $confirm = true;
        continue;
    }
    if (strpos($arg, '--user=') === 0) {
        $userId = (int)substr($arg, 7);
        continue;
    }
    if (strpos($arg, '--login=') === 0) {
        $login = trim((string)substr($arg, 8));
        continue;
    }
    if (strpos($arg, '--name=') === 0) {
        $nameHint = trim((string)substr($arg, 7));
        continue;
    }
    if (strpos($arg, '--album=') === 0) {
        $albumId = (int)substr($arg, 8);
    }
}

$searchHint = $nameHint !== '' ? $nameHint : $login;

if ($userId <= 0 && $albumId > 0) {
    $ownerId = resolveAlbumOwnerUserId($albumId);
    if ($ownerId > 0) {
        $userId = $ownerId;
        echo "Album #{$albumId} owner: user #{$userId}\n";
        printUserLine($userId);
    }
}

if ($userId <= 0 && $searchHint !== '') {
    $matches = findUsersByHint($searchHint);
    if (!$matches) {
        echo "User not found by hint: {$searchHint}\n";
        echo "Tip: use --album=ID alone (owner is resolved from album), or --user=ID\n";
        exit(1);
    }
    if (count($matches) > 1) {
        echo "Multiple users match \"{$searchHint}\":\n";
        foreach ($matches as $row) {
            echo formatUserLine($row) . "\n";
        }
        echo "Pass --user=ID explicitly.\n";
        exit(1);
    }
    $userRow = $matches[0];
    $userId = (int)$userRow['ID'];
    echo formatUserLine($userRow) . "\n";
}

if ($userId <= 0 || $albumId <= 0) {
    echo "Usage: php fix_rollback_album.php --album=ID [--confirm]\n";
    echo "   or: php fix_rollback_album.php --user=ID --album=ID [--confirm]\n";
    echo "   or: php fix_rollback_album.php --login=LOGIN --album=ID [--confirm]\n";
    echo "   or: php fix_rollback_album.php --name=DISPLAY_NAME --album=ID [--confirm]\n";
    exit(1);
}

$repo = new AlbumRepository();
$economy = new GameEconomyRepository();
$service = new AlbumService();

$album = $repo->getAlbumById($albumId, $userId);
if (!$album) {
    echo "Album #{$albumId} not found for user #{$userId}\n";
    exit(1);
}

$albumsBefore = $repo->getAlbumsByUserId($userId);
$albumsInInventoryBefore = $economy->getEventAgnosticLootItemCount(
    $userId,
    AlbumConfig::ITEM_CODE,
    ChestLootConfig::CATEGORY_ALBUM
);

echo "=== albums user #{$userId} ===\n";
foreach ($albumsBefore as $row) {
    $id = (int)$row['ID'];
    $coll = (string)($row['UF_COLLECTION'] ?? '');
    $slots = $repo->getSlotsByAlbumId($id);
    $teams = array_map(static fn($s) => (string)($s['UF_TEAM_SLUG'] ?? ''), $slots);
    echo "#{$id} collection={$coll} glued=" . ($teams ? implode(',', $teams) : '-') . "\n";
}
echo "album_universal in inventory: {$albumsInInventoryBefore}\n";

$slots = $repo->getSlotsByAlbumId($albumId);
$collection = (string)($album['UF_COLLECTION'] ?? '');
echo "\nTarget album #{$albumId} collection={$collection} slots=" . count($slots) . "\n";
foreach ($slots as $slot) {
    $code = (string)($slot['UF_ITEM_CODE'] ?? '');
    $slug = (string)($slot['UF_TEAM_SLUG'] ?? '');
    $label = Wc26CollectibleConfig::getPennantLabel($code)
        ?: Wc26CollectibleConfig::getScarfLabel($code)
        ?: $slug;
    echo "  slot #{$slot['ID']}: {$slug} ({$code}) — {$label}\n";
}

if (!$confirm) {
    echo "\nDry-run. Add --confirm to unglue slots, delete album and return album_universal.\n";
    exit(0);
}

echo "\nApplying rollback...\n";
$lines = [];
foreach ($slots as $slot) {
    $slug = (string)($slot['UF_TEAM_SLUG'] ?? '');
    if ($slug === '') {
        continue;
    }
    $result = $service->unglueTeam($userId, $albumId, $slug, false);
    foreach ($result['lines'] ?? [] as $line) {
        $lines[] = (string)($line['text'] ?? '');
        echo '  ' . ($line['text'] ?? '') . "\n";
    }
}

$remainingSlots = $repo->countSlotsByAlbumId($albumId);
if ($remainingSlots > 0) {
    echo "ERROR: album #{$albumId} still has {$remainingSlots} slots\n";
    exit(1);
}

$stillThere = $repo->getAlbumById($albumId, $userId);
if ($stillThere) {
    $repo->deleteAlbum($albumId);
    echo "Deleted album #{$albumId}\n";
}

$economy->incrementLootItem(
    $userId,
    ChestLootConfig::LOOT_EVENT_GLOBAL,
    AlbumConfig::ITEM_CODE,
    ChestLootConfig::CATEGORY_ALBUM,
    1,
    'N'
);
echo "Returned album_universal to inventory\n";

echo "\n=== after ===\n";
foreach ($repo->getAlbumsByUserId($userId) as $row) {
    $id = (int)$row['ID'];
    $coll = (string)($row['UF_COLLECTION'] ?? '');
    $slotRows = $repo->getSlotsByAlbumId($id);
    $teams = array_map(static fn($s) => (string)($s['UF_TEAM_SLUG'] ?? ''), $slotRows);
    echo "#{$id} collection={$coll} glued=" . ($teams ? implode(',', $teams) : '-') . "\n";
}
$albumsInInventoryAfter = $economy->getEventAgnosticLootItemCount(
    $userId,
    AlbumConfig::ITEM_CODE,
    ChestLootConfig::CATEGORY_ALBUM
);
echo "album_universal in inventory: {$albumsInInventoryAfter}\n";
echo "Done.\n";

/**
 * @return array<int, array<string, mixed>>
 */
function findUsersByHint(string $hint): array
{
    $hint = trim($hint);
    if ($hint === '') {
        return [];
    }

    $response = \Bitrix\Main\UserTable::getList([
        'filter' => [
            'LOGIC' => 'OR',
            ['=LOGIN' => $hint],
            ['%LOGIN' => $hint],
            ['%NAME' => $hint],
            ['%LAST_NAME' => $hint],
        ],
        'select' => ['ID', 'LOGIN', 'NAME', 'LAST_NAME'],
        'order' => ['ID' => 'ASC'],
        'limit' => 10,
    ]);

    $rows = [];
    while ($row = $response->fetch()) {
        $rows[] = $row;
    }

    return $rows;
}

function resolveAlbumOwnerUserId(int $albumId): int
{
    if ($albumId <= 0) {
        return 0;
    }

    $repo = new AlbumRepository();
    $repo->ensureSchema();

    if (!\Bitrix\Main\Loader::includeModule('highloadblock')) {
        return 0;
    }

    $hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getList([
        'filter' => ['=TABLE_NAME' => \Prognos9ys\Main\Service\Game\GameEconomyHlInstaller::TABLE_USER_ALBUM],
    ])->fetch();
    if (!$hlblock) {
        return 0;
    }

    $dataClass = \Bitrix\Highloadblock\HighloadBlockTable::compileEntity($hlblock)->getDataClass();
    $row = $dataClass::getList([
        'filter' => ['=ID' => $albumId],
        'select' => ['ID', 'UF_USER_ID'],
        'limit' => 1,
    ])->fetch();

    return (int)($row['UF_USER_ID'] ?? 0);
}

/**
 * @param array<string, mixed> $row
 */
function formatUserLine(array $row): string
{
    return 'User ' . ($row['LOGIN'] ?? '') . ' #' . (int)($row['ID'] ?? 0)
        . ' ' . trim((string)(($row['NAME'] ?? '') . ' ' . ($row['LAST_NAME'] ?? '')));
}

function printUserLine(int $userId): void
{
    $row = \Bitrix\Main\UserTable::getList([
        'filter' => ['=ID' => $userId],
        'select' => ['ID', 'LOGIN', 'NAME', 'LAST_NAME'],
        'limit' => 1,
    ])->fetch();
    if ($row) {
        echo formatUserLine($row) . "\n";
    }
}
