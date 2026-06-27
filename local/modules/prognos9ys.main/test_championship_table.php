<?php

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

$eventId = (int)($argv[1] ?? 63849);

$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../../..');
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

$handler = new ChampionshipFootballTable(['events' => $eventId, 'token' => '']);
$result = $handler->result();

echo 'event ' . $eventId . PHP_EOL;
echo 'status: ' . ($result['status'] ?? 'n/a') . PHP_EOL;

if (empty($result['result'])) {
    echo 'no table data' . PHP_EOL;
    exit(0);
}

$data = $result['result'];
$groups = $data['groups'] ?? [];
$third = $data['thirdPlaces'] ?? [];
$groupMatches = $data['groupMatches'] ?? [];
$playoffRounds = $data['playoffRounds'] ?? [];

echo 'groups: ' . count($groups) . PHP_EOL;
foreach ($groups as $name => $teams) {
    if ($name === 0 || $name === '0') {
        continue;
    }
    echo '  group ' . $name . ': ' . count($teams) . ' teams' . PHP_EOL;
}

echo 'groupMatches: ' . count($groupMatches) . PHP_EOL;
foreach ($groupMatches as $name => $matches) {
    echo '  group ' . $name . ': ' . count($matches) . ' matches' . PHP_EOL;
}

echo 'thirdPlaces: ' . count($third) . PHP_EOL;
foreach ($third as $i => $row) {
    echo '  #' . ($i + 1) . ' group ' . ($row['sourceGroup'] ?? '?') . ' — ' . ($row['info']['NAME'] ?? '?')
        . ' (' . ($row['score'] ?? 0) . ' pts)' . PHP_EOL;
}

$playoffBracket = $data['playoffBracket'] ?? [];
$bracketColumns = is_array($playoffBracket['columns'] ?? null) ? count($playoffBracket['columns']) : 0;

echo 'playoffRounds: ' . count($playoffRounds) . PHP_EOL;
foreach ($playoffRounds as $tab) {
    echo '  tab ' . ($tab['label'] ?? '?') . ': ' . count($tab['matches'] ?? []) . ' matches' . PHP_EOL;
}

echo 'playoffBracket columns: ' . $bracketColumns . PHP_EOL;
if (count($playoffRounds) === 0 && $bracketColumns === 0) {
    echo 'hint: для ЧМ-2026 на сервере — миграция Version20260627143000 и php local/tools/seed_wc2026_playoff.php' . PHP_EOL;
}
