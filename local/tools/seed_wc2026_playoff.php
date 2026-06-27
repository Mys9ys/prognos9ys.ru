<?php
declare(strict_types=1);

/**
 * Импорт сетки плей-офф ЧМ-2026 из JSON (parse_championat_wc_playoff.mjs).
 *
 *   php local/tools/seed_wc2026_playoff.php --dry-run
 *   php local/tools/seed_wc2026_playoff.php
 *   php local/tools/seed_wc2026_playoff.php --event=63849
 *   php local/tools/seed_wc2026_playoff.php --event=63849 --playoff-from=73
 */

$docRoot = dirname(__DIR__, 2);
$_SERVER['DOCUMENT_ROOT'] = $docRoot;
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_SERVER['SERVER_NAME'] = $_SERVER['SERVER_NAME'] ?? 'localhost';
define('NO_KEEP_STATISTIC', true);
define('NO_AGENT_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

require_once $docRoot . '/bitrix/modules/main/include/prolog_before.php';
require_once $docRoot . '/local/classes/ajax/PlayoffSlotHelper.php';

\Bitrix\Main\Loader::includeModule('iblock');

$argv = $argv ?? [];
$dryRun = in_array('--dry-run', $argv, true);
$eventId = 63849;
$jsonPath = $docRoot . '/local/tools/output/wc2026_playoff_bracket.json';
$playoffFromOverride = 0;

foreach ($argv as $arg) {
    if (strpos($arg, '--event=') === 0) {
        $eventId = (int)substr($arg, 8);
    }
    if (strpos($arg, '--json=') === 0) {
        $jsonPath = $docRoot . '/' . ltrim(substr($arg, 7), '/');
    }
    if (strpos($arg, '--playoff-from=') === 0) {
        $playoffFromOverride = (int)substr($arg, 15);
    }
}

if (!is_file($jsonPath)) {
    echo "JSON не найден: {$jsonPath}\n";
    echo "Сначала: node local/tools/parse_championat_wc_playoff.mjs\n";
    exit(1);
}

$payload = json_decode((string)file_get_contents($jsonPath), true);
if (!is_array($payload) || empty($payload['rounds'])) {
    echo "Пустой или неверный JSON\n";
    exit(1);
}

$matchesIb = resolveIblockId('matches', 2);
$countriesIb = resolveIblockId('countries', 3);
$eventsIb = resolveIblockId('events', 1);

if ($matchesIb <= 0) {
    echo "Инфоблок matches не найден\n";
    exit(1);
}

if (!eventExists($eventsIb, $eventId)) {
    echo "Событие {$eventId} не найдено в инфоблоке events (id={$eventsIb})\n";
    exit(1);
}

$allowedProps = loadPropertyCodes($matchesIb);
$teamIds = loadTeamIdsByName($countriesIb);
$totalPlayoffMatches = 0;
foreach ($payload['rounds'] as $round) {
    $totalPlayoffMatches += count($round['matches'] ?? []);
}
$maxGroupNumber = findMaxGroupMatchNumber($matchesIb, $eventId);
$playoffStartNumber = $playoffFromOverride > 0
    ? $playoffFromOverride
    : ($maxGroupNumber + 1);
$playoffEndNumber = $playoffStartNumber + $totalPlayoffMatches - 1;

echo "matchesIb={$matchesIb}, eventId={$eventId}, groupMax={$maxGroupNumber}, playoff=#{$playoffStartNumber}..#{$playoffEndNumber}\n";
if (empty($allowedProps['bracket_code'])) {
    echo "Внимание: свойства bracket_code/home_label/guest_label не найдены — сначала миграция Version20260627143000\n";
}

$created = 0;
$updated = 0;
$skipped = 0;
$expectedCodes = [];

foreach ($payload['rounds'] as $round) {
    foreach ($round['matches'] as $match) {
        $bracketCode = (string)($match['bracket_code'] ?? '');
        if ($bracketCode === '') {
            $skipped++;
            continue;
        }
        $expectedCodes[] = $bracketCode;

        $xmlId = 'wc26_po_' . $bracketCode;
        $existingId = findMatchId($matchesIb, $eventId, $xmlId, $bracketCode, $playoffStartNumber);

        [$homeId, $homeLabel] = resolveSide($match['home'] ?? null, !empty($match['home_is_slot']), $teamIds);
        [$guestId, $guestLabel] = resolveSide($match['guest'] ?? null, !empty($match['guest_is_slot']), $teamIds);

        if (!$dryRun && empty($match['home_is_slot']) && $homeId <= 0 && trim((string)($match['home'] ?? '')) !== '') {
            echo "Предупреждение {$bracketCode}: команда не найдена — «{$match['home']}» (будет слот по имени)\n";
        }
        if (!$dryRun && empty($match['guest_is_slot']) && $guestId <= 0 && trim((string)($match['guest'] ?? '')) !== '') {
            echo "Предупреждение {$bracketCode}: команда не найдена — «{$match['guest']}» (будет слот по имени)\n";
        }

        $sortStep = (int)($match['sort_step'] ?? 0);
        if ($sortStep > 0) {
            $number = $playoffStartNumber + $sortStep - 1;
        } elseif ($existingId > 0) {
            $number = getMatchNumber($existingId);
        } else {
            $number = $playoffEndNumber + 1;
            while (matchNumberExists($matchesIb, $eventId, $number)) {
                $number++;
            }
        }
        $homeName = displaySideName($match['home'] ?? null, $homeId, $homeLabel, $teamIds);
        $guestName = displaySideName($match['guest'] ?? null, $guestId, $guestLabel, $teamIds);
        $date = trim((string)($match['date'] ?? ''));
        $dateActive = $date !== '' ? $date . ' 18:00:00' : date('d.m.Y H:i:s');

        $props = [
            'events' => $eventId,
            'number' => $number,
            'step' => bracketStepFromCode($bracketCode),
            'stage' => 'Плей-офф',
            'bracket_code' => $bracketCode,
            'home_label' => $homeLabel,
            'guest_label' => $guestLabel,
        ];

        if ($homeId > 0) {
            $props['home'] = $homeId;
        }
        if ($guestId > 0) {
            $props['guest'] = $guestId;
        }

        $props = filterProps($props, $allowedProps);

        // PROPERTY_round — тур группового этапа, для плей-офф не используем.
        if (isset($allowedProps['round'])) {
            $props['round'] = false;
        }

        $fields = [
            'IBLOCK_ID' => $matchesIb,
            'NAME' => sprintf('ЧМ-2026 %s: %s — %s', $bracketCode, $homeName, $guestName),
            'CODE' => 'wc26-' . $eventId . '-po-' . strtolower($bracketCode),
            'XML_ID' => $xmlId,
            'ACTIVE' => 'Y',
            'DATE_ACTIVE_FROM' => $dateActive,
            'SORT' => ((int)($match['sort_step'] ?? $number)) * 10,
        ];

        if ($dryRun) {
            echo "[DRY] {$bracketCode} #{$number} {$homeName} — {$guestName}\n";
            $created++;
            continue;
        }

        $el = new CIBlockElement();
        if ($existingId > 0) {
            if (!$el->Update($existingId, $fields)) {
                echo "Ошибка update {$bracketCode}: {$el->LAST_ERROR}\n";
                $skipped++;
                continue;
            }
            CIBlockElement::SetPropertyValuesEx($existingId, $matchesIb, $props);
            $updated++;
            echo "Обновлён {$bracketCode} #{$number}\n";
        } else {
            $newId = (int)$el->Add($fields);
            if ($newId <= 0) {
                echo "Ошибка create {$bracketCode}: {$el->LAST_ERROR}\n";
                $skipped++;
                continue;
            }
            CIBlockElement::SetPropertyValuesEx($newId, $matchesIb, $props);
            $created++;
            echo "Создан {$bracketCode} #{$number} (id {$newId})\n";
        }
    }
}

echo PHP_EOL . "Готово: создано {$created}, обновлено {$updated}, пропущено {$skipped}\n";

$missingCodes = $dryRun ? [] : findMissingBracketCodes($matchesIb, $eventId, $expectedCodes);
if ($missingCodes) {
    echo 'ВНИМАНИЕ: в БД нет матчей с кодами: ' . implode(', ', $missingCodes) . PHP_EOL;
    echo 'Ожидалось ' . count($expectedCodes) . ' матчей, не хватает ' . count($missingCodes) . PHP_EOL;
    exit(1);
}

echo 'Проверка: все ' . count($expectedCodes) . " матчей плей-офф на месте.\n";

function resolveIblockId(string $code, int $fallback = 0): int
{
    if (class_exists(\Bitrix\Iblock\IblockTable::class)) {
        try {
            $row = \Bitrix\Iblock\IblockTable::getRow([
                'filter' => ['=CODE' => $code],
                'select' => ['ID'],
            ]);
            if (!empty($row['ID'])) {
                return (int)$row['ID'];
            }

            $row = \Bitrix\Iblock\IblockTable::getRow([
                'filter' => ['=CODE' => $code, '=IBLOCK_TYPE_ID' => 'content'],
                'select' => ['ID'],
            ]);
            if (!empty($row['ID'])) {
                return (int)$row['ID'];
            }
        } catch (\Throwable $e) {
            // CLI без полного контекста — пробуем legacy API ниже
        }
    }

    $res = CIBlock::GetList([], ['TYPE' => 'content', 'CODE' => $code], true);
    if ($res && ($row = $res->Fetch()) && (int)($row['ID'] ?? 0) > 0) {
        return (int)$row['ID'];
    }

    $res = CIBlock::GetList([], ['CODE' => $code], true);
    if ($res && ($row = $res->Fetch()) && (int)($row['ID'] ?? 0) > 0) {
        return (int)$row['ID'];
    }

    if ($fallback > 0) {
        $res = CIBlock::GetByID($fallback);
        if ($res && ($row = $res->Fetch()) && (int)($row['ID'] ?? 0) > 0) {
            return (int)$row['ID'];
        }

        // Как в остальных скриптах проекта: ? : 2 без жёсткой проверки метаданных
        return $fallback;
    }

    return 0;
}

function normalizeTeamKey(string $value): string
{
    $value = mb_strtolower(trim($value));
    $value = str_replace(["`", '´', 'ʼ', '′', '’'], "'", $value);
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

    static $canonical = [
        "кот д'ивуар" => "кот-д'ивуар",
        'кот-д ивуар' => "кот-д'ивуар",
        "кот-д'ивуар" => "кот-д'ивуар",
        'южная африка' => 'юар',
        'соединенные штаты' => 'сша',
        'соединённые штаты' => 'сша',
        'кабо верде' => 'кабо-верде',
    ];

    return $canonical[$value] ?? $value;
}

function loadTeamIdsByName(int $countriesIb): array
{
    $map = [];
    if ($countriesIb <= 0) {
        return $map;
    }

    $response = CIBlockElement::GetList(
        ['NAME' => 'ASC'],
        ['IBLOCK_ID' => $countriesIb],
        false,
        false,
        ['ID', 'NAME']
    );
    while ($row = $response->Fetch()) {
        $id = (int)$row['ID'];
        $name = normalizeTeamKey((string)$row['NAME']);
        $map[$name] = $id;
    }

    $aliases = [
        'юар' => ['южная африка'],
        'сша' => ['usa'],
        "кот-д'ивуар" => ["кот д'ивуар", 'кот-д ивуар', 'кот-д`ивуар'],
        'босния и герцеговина' => ['босния'],
        'кабо-верде' => ['кабо верде'],
    ];

    foreach ($aliases as $target => $names) {
        $target = normalizeTeamKey($target);
        if (!isset($map[$target])) {
            foreach ($names as $alias) {
                $aliasKey = normalizeTeamKey($alias);
                if (isset($map[$aliasKey])) {
                    $map[$target] = $map[$aliasKey];
                    break;
                }
            }
        }
    }

    return $map;
}

function loadPropertyCodes(int $iblockId): array
{
    $codes = [];
    $response = CIBlockProperty::GetList(['SORT' => 'ASC'], ['IBLOCK_ID' => $iblockId, 'ACTIVE' => 'Y']);
    while ($row = $response->Fetch()) {
        $code = (string)($row['CODE'] ?? '');
        if ($code !== '') {
            $codes[$code] = true;
        }
    }

    return $codes;
}

function filterProps(array $props, array $allowed): array
{
    return array_intersect_key($props, $allowed);
}

function eventExists(int $eventsIb, int $eventId): bool
{
    if ($eventsIb <= 0 || $eventId <= 0) {
        return false;
    }

    $row = CIBlockElement::GetList(
        [],
        ['IBLOCK_ID' => $eventsIb, 'ID' => $eventId],
        false,
        ['nTopCount' => 1],
        ['ID']
    )->Fetch();

    return (bool)$row;
}

function resolveSide(?string $value, bool $isSlot, array $teamIds): array
{
    $value = trim((string)$value);
    if ($value === '') {
        return [0, ''];
    }

    if ($isSlot) {
        return [0, $value];
    }

    $teamId = $teamIds[normalizeTeamKey($value)] ?? 0;
    if ($teamId > 0) {
        return [$teamId, ''];
    }

    return [0, $value];
}

function displaySideName(?string $raw, int $teamId, string $label, array $teamIds): string
{
    if ($teamId > 0) {
        foreach ($teamIds as $name => $id) {
            if ($id === $teamId) {
                return mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');
            }
        }
    }

    $raw = trim((string)$raw);
    if ($raw !== '') {
        return $raw;
    }

    return $label !== '' ? $label : 'TBD';
}

function findMaxGroupMatchNumber(int $matchesIb, int $eventId): int
{
    $max = 0;
    $response = CIBlockElement::GetList(
        ['PROPERTY_number' => 'DESC'],
        [
            'IBLOCK_ID' => $matchesIb,
            'PROPERTY_events' => $eventId,
        ],
        false,
        false,
        ['ID', 'XML_ID', 'PROPERTY_number', 'PROPERTY_stage', 'PROPERTY_bracket_code', 'PROPERTY_group']
    );

    while ($row = $response->Fetch()) {
        if (PlayoffSlotHelper::isPlayoffMatchRow($row)) {
            continue;
        }

        $num = (int)($row['PROPERTY_NUMBER_VALUE'] ?? 0);
        if ($num > $max) {
            $max = $num;
        }
    }

    return $max;
}

function matchNumberExists(int $matchesIb, int $eventId, int $number): bool
{
    if ($number <= 0) {
        return false;
    }

    $row = CIBlockElement::GetList(
        [],
        [
            'IBLOCK_ID' => $matchesIb,
            'PROPERTY_events' => $eventId,
            'PROPERTY_number' => $number,
        ],
        false,
        ['nTopCount' => 1],
        ['ID']
    )->Fetch();

    return (bool)$row;
}

function findNextMatchNumber(int $matchesIb, int $eventId): int
{
    $max = 0;
    $response = CIBlockElement::GetList(
        ['PROPERTY_number' => 'DESC'],
        [
            'IBLOCK_ID' => $matchesIb,
            'PROPERTY_events' => $eventId,
        ],
        false,
        ['nTopCount' => 1],
        ['PROPERTY_number']
    );
    if ($row = $response->Fetch()) {
        $max = (int)($row['PROPERTY_NUMBER_VALUE'] ?? 0);
    }

    return max(1, $max + 1);
}

function findMatchId(int $matchesIb, int $eventId, string $xmlId, string $bracketCode, int $playoffStartNumber): int
{
    $byXml = CIBlockElement::GetList(
        [],
        [
            'IBLOCK_ID' => $matchesIb,
            'XML_ID' => $xmlId,
        ],
        false,
        ['nTopCount' => 1],
        ['ID', 'XML_ID', 'PROPERTY_events', 'PROPERTY_bracket_code', 'PROPERTY_stage', 'PROPERTY_number']
    )->Fetch();
    if ($byXml) {
        $id = (int)$byXml['ID'];
        if (isPlayoffSeedMatchRow($byXml, $eventId, $bracketCode, $xmlId, $playoffStartNumber)) {
            return $id;
        }
        $num = (int)($byXml['PROPERTY_NUMBER_VALUE'] ?? 0);
        echo "Предупреждение: XML_ID {$xmlId} на матче #{$num} (id {$id}) не похож на плей-офф — ищем дальше\n";
    }

    $byCode = CIBlockElement::GetList(
        [],
        [
            'IBLOCK_ID' => $matchesIb,
            'PROPERTY_events' => $eventId,
            'PROPERTY_bracket_code' => $bracketCode,
            'PROPERTY_stage' => 'Плей-офф',
        ],
        false,
        false,
        ['ID', 'XML_ID', 'PROPERTY_events', 'PROPERTY_bracket_code', 'PROPERTY_stage', 'PROPERTY_number']
    );

    while ($row = $byCode->Fetch()) {
        $id = (int)$row['ID'];
        if (isPlayoffSeedMatchRow($row, $eventId, $bracketCode, $xmlId, $playoffStartNumber)) {
            return $id;
        }
    }

    return 0;
}

function isPlayoffSeedMatchRow(array $row, int $eventId, string $bracketCode, string $xmlId, int $playoffStartNumber): bool
{
    if ((int)($row['PROPERTY_EVENTS_VALUE'] ?? 0) !== $eventId) {
        return false;
    }

    $number = (int)($row['PROPERTY_NUMBER_VALUE'] ?? 0);
    if ($playoffStartNumber > 1 && $number > 0 && $number < $playoffStartNumber) {
        return false;
    }

    $rowXmlId = (string)($row['XML_ID'] ?? '');
    if ($rowXmlId === $xmlId) {
        return true;
    }

    $code = strtoupper(trim((string)($row['PROPERTY_BRACKET_CODE_VALUE'] ?? '')));
    if ($code !== strtoupper($bracketCode)) {
        return false;
    }

    $stage = mb_strtolower(trim((string)($row['PROPERTY_STAGE_VALUE'] ?? '')));
    if (in_array($stage, ['плей-офф', 'play-off', 'playoff'], true)) {
        return true;
    }

    return strpos($rowXmlId, 'wc26_po_') === 0;
}

function getMatchNumber(int $matchId): int
{
    $row = CIBlockElement::GetList(
        [],
        ['ID' => $matchId],
        false,
        ['nTopCount' => 1],
        ['PROPERTY_number']
    )->Fetch();

    return (int)($row['PROPERTY_NUMBER_VALUE'] ?? 0);
}

function findMissingBracketCodes(int $matchesIb, int $eventId, array $expectedCodes): array
{
    $expectedCodes = array_values(array_unique(array_filter(array_map('strval', $expectedCodes))));
    if (!$expectedCodes) {
        return [];
    }

    $found = [];
    $response = CIBlockElement::GetList(
        [],
        [
            'IBLOCK_ID' => $matchesIb,
            'PROPERTY_events' => $eventId,
        ],
        false,
        false,
        ['ID', 'XML_ID', 'PROPERTY_bracket_code']
    );

    while ($row = $response->Fetch()) {
        $code = strtoupper(trim((string)($row['PROPERTY_BRACKET_CODE_VALUE'] ?? '')));
        if ($code !== '') {
            $found[$code] = true;
            continue;
        }

        $xmlId = (string)($row['XML_ID'] ?? '');
        if (strpos($xmlId, 'wc26_po_') === 0) {
            $found[strtoupper(substr($xmlId, 8))] = true;
        }
    }

    $missing = [];
    foreach ($expectedCodes as $code) {
        if (empty($found[strtoupper($code)])) {
            $missing[] = $code;
        }
    }

    return $missing;
}

function bracketStepFromCode(string $code): int
{
    $code = strtoupper(trim($code));
    if (preg_match('/^(?:A|B)(\d+)$/u', $code, $m)) {
        return (int)$m[1];
    }
    if (preg_match('/^QF(\d+)$/u', $code, $m)) {
        return (int)$m[1];
    }
    if (preg_match('/^SF(\d+)$/u', $code, $m)) {
        return (int)$m[1];
    }
    if (preg_match('/^LSF(\d+)$/u', $code, $m)) {
        return (int)$m[1];
    }
    if ($code === 'F1' || $code === 'F3') {
        return 1;
    }

    return 0;
}
