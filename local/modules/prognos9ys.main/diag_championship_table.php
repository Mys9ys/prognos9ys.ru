<?php

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

use Prognos9ys\Main\Model\Repository\MatchIblockMetaRepository;
use Prognos9ys\Main\Model\Repository\MatchesRepository;
use Prognos9ys\Main\Service\Championship\FootballTableService;
use Prognos9ys\Main\Service\Championship\PlayoffSlotHelper;

$eventId = (int)($argv[1] ?? 63849);

$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../../..');
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

\Bitrix\Main\Loader::includeModule('prognos9ys.main');
\Bitrix\Main\Loader::includeModule('iblock');

$say = static function (string $line): void {
    echo $line . PHP_EOL;
    if (function_exists('ob_flush')) {
        @ob_flush();
    }
    flush();
};

$meta = new MatchIblockMetaRepository();
$stageDetailCode = $meta->getStageDetailPropertyCode();

$say('diag event ' . $eventId);
$say('stage_detail property: ' . ($stageDetailCode ?: 'not found'));

$t0 = microtime(true);
$matches = (new MatchesRepository())->findForChampionshipTable($eventId);
$say(sprintf('orm matches: %d in %.3fs', count($matches), microtime(true) - $t0));

$playoff = 0;
foreach ($matches as $row) {
    if (PlayoffSlotHelper::isPlayoffMatch($row)) {
        $playoff++;
    }
}
$say('php classify playoff: ' . $playoff);

$say('service...');
$t1 = microtime(true);
$result = (new FootballTableService())->getTable((string)$eventId, '');
$say(sprintf('service status=%s in %.3fs', $result['status'] ?? '?', microtime(true) - $t1));

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
