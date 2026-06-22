<?php
declare(strict_types=1);

/**
 * Импорт команд и матчей IEM Cologne из JSON (parse_cs2_iem_liquipedia.mjs).
 *
 *   php local/tools/import_cs2_iem_matches.php --dry-run
 *   php local/tools/import_cs2_iem_matches.php
 *   php local/tools/import_cs2_iem_matches.php --json=local/tools/output/cs2_iem_matches.json
 *   php local/tools/import_cs2_iem_matches.php --replace-playoffs
 */

require_once __DIR__ . '/cs2_iem_team_catalog.php';

$docRoot = dirname(__DIR__, 2);
$_SERVER['DOCUMENT_ROOT'] = $docRoot;
define('NO_KEEP_STATISTIC', true);
define('NO_AGENT_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

require_once $docRoot . '/bitrix/modules/main/include/prolog_before.php';

\Bitrix\Main\Loader::includeModule('iblock');
\Bitrix\Main\Loader::includeModule('prognos9ys.main');

require_once $docRoot . '/local/php_interface/migrations/Cs2MigrationIblock.php';

use Sprint\Migration\Cs2MigrationIblock;
use Prognos9ys\Main\Model\Repository\Cs2IblockRegistry;

$argv = $argv ?? [];
$dryRun = in_array('--dry-run', $argv, true);
$replacePlayoffs = in_array('--replace-playoffs', $argv, true);
$jsonPath = parseCliArg($argv, '--json=')
    ?? $docRoot . '/local/tools/output/cs2_iem_matches.json';

if (!is_file($jsonPath)) {
    echo "JSON не найден: {$jsonPath}\n";
    echo "Сначала: node local/tools/parse_cs2_iem_liquipedia.mjs\n";
    exit(1);
}

$payload = json_decode((string)file_get_contents($jsonPath), true);
if (!is_array($payload) || empty($payload['matches'])) {
    echo "Пустой или неверный JSON\n";
    exit(1);
}

$teamsIb = Cs2MigrationIblock::findId('cs2teams');
$matchesIb = Cs2MigrationIblock::findId('cs2matches');
$eventsIb = Cs2MigrationIblock::findId('events');

if ($teamsIb <= 0 || $matchesIb <= 0 || $eventsIb <= 0) {
    echo "Нужны инфоблоки cs2teams, cs2matches, events\n";
    exit(1);
}

$eventId = resolveEventId((string)($payload['event_xml_id'] ?? 'cs2_iem_cologne_2026'), $eventsIb);
if ($eventId <= 0) {
    echo "Событие не найдено\n";
    exit(1);
}

$catalog = cs2_iem_team_catalog();
$teamIds = seedTeams($teamsIb, $catalog, array_unique($payload['teams'] ?? []), $dryRun);

if ($replacePlayoffs && !$dryRun) {
    deletePlayoffDuplicates($matchesIb, $eventId);
}

$mapsByCode = loadMapsByCode();
$created = 0;
$updated = 0;
$skipped = 0;

foreach ($payload['matches'] as $match) {
    $homeCode = (string)($match['home'] ?? '');
    $guestCode = (string)($match['guest'] ?? '');
    $number = (int)($match['number'] ?? 0);

    if ($number <= 0 || $homeCode === '' || $guestCode === '') {
        $skipped++;
        continue;
    }

    $homeId = $teamIds[$homeCode] ?? 0;
    $guestId = $teamIds[$guestCode] ?? 0;
    if ($homeId <= 0 || $guestId <= 0) {
        echo "Пропуск #{$number}: команда {$homeCode}/{$guestCode} не найдена\n";
        $skipped++;
        continue;
    }

    $xmlId = 'cs2_iem26_m' . $number;
    $existingId = findMatchByXmlOrNumber($matchesIb, $eventId, $xmlId, $number);

    $result = $match['result'] ?? null;
    $active = (string)($match['active'] ?? 'Y');
    if (is_array($result) && (int)($result['maps_home'] ?? 0) + (int)($result['maps_guest'] ?? 0) > 0) {
        $active = 'N';
    } else {
        $matchTs = MakeTimeStamp((string)($match['date'] ?? ''));
        if ($matchTs > 0 && $matchTs < time()) {
            $active = 'N';
        }
    }

    $props = [
        'events' => $eventId,
        'home' => $homeId,
        'guest' => $guestId,
        'number' => $number,
        'round' => (int)($match['round'] ?? 0),
        'step' => $number,
        'stage' => (string)($match['stage'] ?? ''),
        'bo_format' => (string)($match['bo_format'] ?? 'bo3'),
    ];

    if (is_array($result)) {
        $props = array_merge($props, normalizeResultProps($result, $mapsByCode));
    }

    $homeName = $catalog[$homeCode]['name'] ?? $homeCode;
    $guestName = $catalog[$guestCode]['name'] ?? $guestCode;

    $fields = [
        'NAME' => sprintf('IEM Cologne 2026 #%d: %s — %s', $number, $homeName, $guestName),
        'CODE' => 'iem26-' . $number,
        'XML_ID' => $xmlId,
        'ACTIVE' => $active,
        'DATE_ACTIVE_FROM' => (string)($match['date'] ?? date('d.m.Y H:i:s')),
        'SORT' => $number * 10,
        'PROPERTY_VALUES' => $props,
    ];

    if ($dryRun) {
        echo "[DRY] #{$number} {$homeCode} vs {$guestCode} ({$props['stage']}) active={$active}\n";
        $created++;
        continue;
    }

    $el = new CIBlockElement();
    if ($existingId > 0) {
        $el->Update($existingId, $fields);
        $updated++;
    } else {
        $newId = (int)$el->Add($fields);
        if ($newId <= 0) {
            echo "Ошибка #{$number}: " . $el->LAST_ERROR . "\n";
            $skipped++;
        } else {
            $created++;
        }
    }
}

Cs2IblockRegistry::resetCache();

echo ($dryRun ? '[DRY RUN] ' : '') . "event={$eventId}, created={$created}, updated={$updated}, skipped={$skipped}\n";

function parseCliArg(array $argv, string $prefix): ?string
{
    foreach ($argv as $arg) {
        if (str_starts_with($arg, $prefix)) {
            return substr($arg, strlen($prefix));
        }
    }

    return null;
}

function resolveEventId(string $xmlId, int $eventsIb): int
{
    return (int)CIBlockElement::GetList([], [
        'IBLOCK_ID' => $eventsIb,
        '=XML_ID' => $xmlId,
    ], false, ['nTopCount' => 1], ['ID'])->Fetch()['ID'] ?? 0;
}

/** @param list<string> $teamCodes */
function seedTeams(int $iblockId, array $catalog, array $teamCodes, bool $dryRun): array
{
    $ids = [];
    foreach ($teamCodes as $code) {
        $code = strtolower($code);
        if (!isset($catalog[$code])) {
            continue;
        }
        $team = $catalog[$code];
        $existing = (int)CIBlockElement::GetList([], [
            'IBLOCK_ID' => $iblockId,
            '=XML_ID' => 'cs2team_' . $code,
        ], false, ['nTopCount' => 1], ['ID'])->Fetch()['ID'] ?? 0;

        if ($existing > 0) {
            $ids[$code] = $existing;
            continue;
        }

        if ($dryRun) {
            $ids[$code] = -1;
            continue;
        }

        $el = new CIBlockElement();
        $newId = (int)$el->Add([
            'NAME' => $team['name'],
            'CODE' => $team['code'],
            'XML_ID' => 'cs2team_' . $code,
            'ACTIVE' => 'Y',
            'SORT' => $team['sort'],
            'IBLOCK_ID' => $iblockId,
            'PROPERTY_VALUES' => [
                'short_tag' => $team['tag'],
                'hltv_slug' => $team['slug'],
                'region' => $team['region'],
            ],
        ]);
        $ids[$code] = $newId;
    }

    return $ids;
}

function findMatchByXmlOrNumber(int $iblockId, int $eventId, string $xmlId, int $number): int
{
    $row = CIBlockElement::GetList([], [
        'IBLOCK_ID' => $iblockId,
        '=XML_ID' => $xmlId,
    ], false, ['nTopCount' => 1], ['ID'])->Fetch();

    if ($row) {
        return (int)$row['ID'];
    }

    $row = CIBlockElement::GetList([], [
        'IBLOCK_ID' => $iblockId,
        'PROPERTY_EVENTS' => $eventId,
        'PROPERTY_NUMBER' => $number,
    ], false, ['nTopCount' => 1], ['ID'])->Fetch();

    return (int)($row['ID'] ?? 0);
}

function deletePlayoffDuplicates(int $matchesIb, int $eventId): void
{
    $rs = CIBlockElement::GetList([], [
        'IBLOCK_ID' => $matchesIb,
        'PROPERTY_EVENTS' => $eventId,
        '<=PROPERTY_NUMBER' => 6,
    ], false, false, ['ID', 'PROPERTY_NUMBER']);

    while ($row = $rs->Fetch()) {
        CIBlockElement::Delete((int)$row['ID']);
    }
}

/** @return array<string, int> */
function loadMapsByCode(): array
{
    $iblockId = Cs2MigrationIblock::findId('cs2maps');
    if ($iblockId <= 0) {
        return [];
    }

    $map = [];
    $rs = CIBlockElement::GetList(['SORT' => 'ASC'], ['IBLOCK_ID' => $iblockId], false, false, ['ID', 'CODE']);
    while ($row = $rs->Fetch()) {
        $code = strtolower((string)($row['CODE'] ?? ''));
        if ($code !== '') {
            $map[$code] = (int)$row['ID'];
        }
    }

    return $map;
}

/** @param array<string, mixed> $result */
function normalizeResultProps(array $result, array $mapsByCode): array
{
    $mapScores = $result['map_scores'] ?? [];
    if (is_array($mapScores)) {
        foreach ($mapScores as &$row) {
            $code = strtolower((string)($row['map_code'] ?? ''));
            if ($code !== '' && isset($mapsByCode[$code])) {
                $row['map_id'] = $mapsByCode[$code];
            }
        }
        unset($row);
        $result['map_scores'] = json_encode($mapScores, JSON_UNESCAPED_UNICODE);
    }

    return $result;
}
