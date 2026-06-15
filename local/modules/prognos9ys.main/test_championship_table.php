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
