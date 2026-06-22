<?php
declare(strict_types=1);

/**
 * Бэкфилл прогнозов CS2 на завершённые матчи (тест: user_id=1).
 *
 *   php local/tools/backfill_cs2_prognosis.php --dry-run
 *   php local/tools/backfill_cs2_prognosis.php --user=1
 *   php local/tools/backfill_cs2_prognosis.php --user=1 --event=76284
 *   php local/tools/backfill_cs2_prognosis.php --user=1 --stage="Stage 1"
 *   php local/tools/backfill_cs2_prognosis.php --user=1 --calc
 */

$docRoot = dirname(__DIR__, 2);
$_SERVER['DOCUMENT_ROOT'] = $docRoot;
define('NO_KEEP_STATISTIC', true);
define('NO_AGENT_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

require_once $docRoot . '/bitrix/modules/main/include/prolog_before.php';

\Bitrix\Main\Loader::includeModule('iblock');
\Bitrix\Main\Loader::includeModule('prognos9ys.main');

require_once $docRoot . '/local/php_interface/migrations/Cs2MigrationIblock.php';
require_once $docRoot . '/local/classes/main/GenValuesBotCs2.php';
require_once $docRoot . '/local/classes/ajax/CalcCs2PrognosisResult.php';

use Sprint\Migration\Cs2MigrationIblock;
use Prognos9ys\Main\Service\Cs2\Cs2MapsService;

$argv = $argv ?? [];
$dryRun = in_array('--dry-run', $argv, true);
$withCalc = in_array('--calc', $argv, true);
$userId = (int)(parseCliArg($argv, '--user=') ?? '1');
$eventId = (int)(parseCliArg($argv, '--event=') ?? '0');
$stageFilter = parseCliArg($argv, '--stage=');

if ($userId <= 0) {
    echo "Укажите --user=ID\n";
    exit(1);
}

$registry = new \Prognos9ys\Main\Model\Repository\Cs2IblockRegistry();
$matchesIb = $registry->getIblockId('cs2matches');
$prognosisIb = $registry->getIblockId('prognoscs2');
$eventsIb = Cs2MigrationIblock::findId('events');

if ($matchesIb <= 0 || $prognosisIb <= 0) {
    echo "Инфоблоки CS2 не найдены\n";
    exit(1);
}

if ($eventId <= 0) {
    $eventId = (int)CIBlockElement::GetList([], [
        'IBLOCK_ID' => $eventsIb,
        '=XML_ID' => 'cs2_iem_cologne_2026',
    ], false, ['nTopCount' => 1], ['ID'])->Fetch()['ID'] ?? 0;
}

if ($eventId <= 0) {
    echo "Событие cs2_iem_cologne_2026 не найдено\n";
    exit(1);
}

$mapPool = (new Cs2MapsService())->getPoolMaps();
$filter = [
    'IBLOCK_ID' => $matchesIb,
    'PROPERTY_EVENTS' => $eventId,
    'ACTIVE' => 'N',
];
if ($stageFilter) {
    $filter['PROPERTY_STAGE'] = $stageFilter;
}

$rs = CIBlockElement::GetList(
    ['PROPERTY_NUMBER' => 'ASC'],
    $filter,
    false,
    false,
    ['ID', 'NAME', 'DATE_ACTIVE_FROM', 'PROPERTY_number', 'PROPERTY_events', 'PROPERTY_bo_format', 'PROPERTY_stage']
);

$created = 0;
$skipped = 0;

while ($match = $rs->Fetch()) {
    $matchId = (int)$match['ID'];
    $exists = CIBlockElement::GetList([], [
        'IBLOCK_ID' => $prognosisIb,
        'PROPERTY_MATCH_ID' => $matchId,
        'PROPERTY_USER_ID' => $userId,
    ], false, ['nTopCount' => 1], ['ID'])->Fetch();

    if ($exists) {
        $skipped++;
        continue;
    }

    $boFormat = (string)($match['PROPERTY_BO_FORMAT_VALUE'] ?? 'bo3');
    $generator = new GenValuesBotCs2($boFormat, $mapPool);
    $props = array_replace($generator->getArFields(), [
        17 => $matchId,
        30 => $match['PROPERTY_NUMBER_VALUE'],
        31 => $userId,
        52 => $match['PROPERTY_EVENTS_VALUE'],
    ]);

    $matchTs = MakeTimeStamp($match['DATE_ACTIVE_FROM']);
    $offset = random_int(3600, 48 * 3600);
    $sentTs = max($matchTs - $offset, time() - 30 * 86400);
    $sentAt = date('d.m.Y H:i:s', $sentTs);

    if ($dryRun) {
        echo "[DRY] user={$userId} match #{$match['PROPERTY_NUMBER_VALUE']} ({$match['PROPERTY_STAGE_VALUE']}) @ {$sentAt}\n";
        $created++;
        continue;
    }

    $el = new CIBlockElement();
    $newId = (int)$el->Add([
        'NAME' => 'Участник: ' . $userId . ' Прогноз CS2 на матч: ' . $matchId . ' номер ' . $props[30],
        'IBLOCK_ID' => $prognosisIb,
        'DATE_ACTIVE_FROM' => $sentAt,
        'PROPERTY_VALUES' => $props,
    ]);

    if ($newId <= 0) {
        echo "Ошибка матч #{$match['PROPERTY_NUMBER_VALUE']}: " . $el->LAST_ERROR . "\n";
        continue;
    }

    $created++;

    if ($withCalc) {
        $calc = new CalcCs2PrognosisResult(['matchId' => $matchId]);
        unset($calc);
    }
}

echo ($dryRun ? '[DRY RUN] ' : '') . "user={$userId}, event={$eventId}, created={$created}, skipped={$skipped}\n";

function parseCliArg(array $argv, string $prefix): ?string
{
    foreach ($argv as $arg) {
        if (str_starts_with($arg, $prefix)) {
            return substr($arg, strlen($prefix));
        }
    }

    return null;
}
