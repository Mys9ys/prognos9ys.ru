<?php

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

$eventId = (int)($argv[1] ?? 63849);

$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../../..');
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/classes/ajax/PlayoffSlotHelper.php';

\Bitrix\Main\Loader::includeModule('iblock');

$matchesIb = 2;
$res = CIBlock::GetList([], ['TYPE' => 'content', 'CODE' => 'matches'], true);
if ($res && ($row = $res->Fetch())) {
    $matchesIb = (int)$row['ID'];
}

$baseFilter = [
    'IBLOCK_ID' => $matchesIb,
    'PROPERTY_events' => $eventId,
];

$say = static function (string $line): void {
    echo $line . PHP_EOL;
    if (function_exists('ob_flush')) {
        @ob_flush();
    }
    flush();
};

$say('diag event ' . $eventId . ' ib=' . $matchesIb);
$say('prolog ok');

$t0 = microtime(true);
$count = 0;
$response = CIBlockElement::GetList(
    ['ID' => 'ASC'],
    $baseFilter,
    false,
    false,
    ['ID', 'PROPERTY_number', 'PROPERTY_stage']
);
while ($response->Fetch()) {
    $count++;
}
$say(sprintf('events-only query: %d matches in %.3fs', $count, microtime(true) - $t0));

$stageDetailCode = null;
$propRes = CIBlockProperty::GetList([], ['IBLOCK_ID' => $matchesIb, 'NAME' => 'Этап расширенный']);
if ($propRes && ($propRow = $propRes->Fetch())) {
    $stageDetailCode = (string)($propRow['CODE'] ?? '');
}
$say('stage_detail property: ' . ($stageDetailCode ?: 'not found'));

$playoff = 0;
$t1 = microtime(true);
$select = ['ID', 'PROPERTY_number', 'PROPERTY_stage', 'PROPERTY_bracket_code'];
if ($stageDetailCode) {
    $select[] = 'PROPERTY_' . $stageDetailCode;
}
$response = CIBlockElement::GetList(['PROPERTY_number' => 'ASC'], $baseFilter, false, false, $select);
while ($row = $response->Fetch()) {
    if (PlayoffSlotHelper::isPlayoffMatchRow($row, $stageDetailCode ?: null)) {
        $playoff++;
    }
}
$say(sprintf('php classify playoff: %d in %.3fs', $playoff, microtime(true) - $t1));

$say('full handler...');
$t2 = microtime(true);
$handler = new ChampionshipFootballTable(['events' => $eventId, 'token' => '']);
$result = $handler->result();
$say(sprintf('handler status=%s in %.3fs', $result['status'] ?? '?', microtime(true) - $t2));

if (!empty($result['result'])) {
    $data = $result['result'];
    $say('groups: ' . count($data['groups'] ?? []));
    $say('playoffRounds: ' . count($data['playoffRounds'] ?? []));
    $cols = count($data['playoffBracket']['columns'] ?? []);
    $say('playoffBracket columns: ' . $cols);
    if ($cols === 0) {
        $say('run: php7.4 local/tools/seed_wc2026_playoff.php --event=' . $eventId);
    }
}
