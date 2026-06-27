<?php

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

$eventId = (int)($argv[1] ?? 63849);

$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../../..');
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/classes/ajax/PlayoffSlotHelper.php';

\Bitrix\Main\Loader::includeModule('iblock');

$matchesIb = 2;
$baseFilter = [
    'IBLOCK_ID' => $matchesIb,
    'PROPERTY_events' => $eventId,
];

$tick = static function (string $label) {
    static $startedAt = null;
    $now = microtime(true);
    if ($startedAt === null) {
        $startedAt = $now;
        echo $label . PHP_EOL;
        return;
    }
    echo sprintf('  %.3fs  %s', $now - $startedAt, $label) . PHP_EOL;
    $startedAt = $now;
};

echo 'diag event ' . $eventId . PHP_EOL;

$tick('prolog ok');

$row = CIBlockElement::GetList(
    [],
    $baseFilter + ['PROPERTY_stage' => 'Плей-офф'],
    false,
    ['nTopCount' => 1],
    ['ID', 'PROPERTY_number', 'PROPERTY_bracket_code']
)->Fetch();
$tick('stage=Плей-офф (nTopCount 1): ' . ($row ? 'found #' . ($row['ID'] ?? '?') : 'none'));

$count = 0;
$response = CIBlockElement::GetList(
    ['PROPERTY_number' => 'DESC'],
    $baseFilter,
    false,
    ['nTopCount' => 48],
    ['ID', 'PROPERTY_number', 'PROPERTY_bracket_code', 'PROPERTY_stage']
);
while ($row = $response->Fetch()) {
    if (PlayoffSlotHelper::isPlayoffMatchRow($row)) {
        $count++;
    }
}
$tick('top-48 by number + isPlayoff: ' . $count);

$tick('full ChampionshipFootballTable...');
$t0 = microtime(true);
$handler = new ChampionshipFootballTable(['events' => $eventId, 'token' => '']);
$result = $handler->result();
$tick('handler done, status=' . ($result['status'] ?? '?') . ', elapsed=' . round(microtime(true) - $t0, 2) . 's');

if (!empty($result['result'])) {
    $data = $result['result'];
    echo 'groups: ' . count($data['groups'] ?? []) . PHP_EOL;
    echo 'playoffRounds: ' . count($data['playoffRounds'] ?? []) . PHP_EOL;
    $cols = count($data['playoffBracket']['columns'] ?? []);
    echo 'playoffBracket columns: ' . $cols . PHP_EOL;
    if ($cols === 0) {
        echo 'hint: php7.4 local/tools/seed_wc2026_playoff.php --event=' . $eventId . PHP_EOL;
    }
}
