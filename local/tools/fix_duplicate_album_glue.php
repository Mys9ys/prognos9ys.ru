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
$teamSlug = strtolower(trim((string)($_SERVER['argv'][2] ?? '')));

if ($userId <= 0 || $teamSlug === '') {
    echo "Usage: php fix_duplicate_album_glue.php <userId> <teamSlug>\n";
    echo "  Example: php fix_duplicate_album_glue.php 20 arg\n";
    exit(1);
}

use Prognos9ys\Main\Model\Repository\AlbumRepository;
use Prognos9ys\Main\Service\Game\AlbumConfig;
use Prognos9ys\Main\Service\Game\AlbumService;

$repo = new AlbumRepository();
$service = new AlbumService();

echo "=== albums user {$userId} ===\n";
$albums = $repo->getAlbumsByUserId($userId);
foreach ($albums as $album) {
    $id = (int)$album['ID'];
    $coll = (string)($album['UF_COLLECTION'] ?? '');
    $slots = $repo->getSlotsByAlbumId($id);
    $teams = array_map(static fn($s) => (string)($s['UF_TEAM_SLUG'] ?? ''), $slots);
    echo "#{$id} collection={$coll} glued=" . implode(',', $teams) . "\n";
}

$matches = [];
foreach ($albums as $album) {
    $albumId = (int)$album['ID'];
    $slot = $repo->getSlotByAlbumAndTeam($albumId, $teamSlug);
    if ($slot) {
        $matches[] = [
            'album_id' => $albumId,
            'collection' => (string)($album['UF_COLLECTION'] ?? ''),
            'slot_id' => (int)$slot['ID'],
        ];
    }
}

if (count($matches) < 2) {
    echo "No duplicate glue for team {$teamSlug} (found " . count($matches) . ")\n";
    exit(0);
}

usort($matches, static fn($a, $b) => $a['album_id'] <=> $b['album_id']);
$keep = $matches[0];
$remove = $matches[count($matches) - 1];

echo "Keep album #{$keep['album_id']}, unglue from album #{$remove['album_id']}\n";

$result = $service->unglueTeam($userId, $remove['album_id'], $teamSlug, true);
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";

echo "\n=== after ===\n";
foreach ($repo->getAlbumsByUserId($userId) as $album) {
    $id = (int)$album['ID'];
    $coll = (string)($album['UF_COLLECTION'] ?? '');
    $slots = $repo->getSlotsByAlbumId($id);
    $teams = array_map(static fn($s) => (string)($s['UF_TEAM_SLUG'] ?? ''), $slots);
    echo "#{$id} collection={$coll} glued=" . implode(',', $teams) . "\n";
}
