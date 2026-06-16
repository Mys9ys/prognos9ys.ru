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

if (!\Bitrix\Main\Loader::includeModule('iblock')) {
    echo "iblock module not loaded\n";
    exit(1);
}

$matchId = (int)($_SERVER['argv'][1] ?? 0);
if ($matchId <= 0) {
    echo "Usage: php recalc_match.php <matchId>\n";
    exit(1);
}

new \CalcFootballPrognosisResult(['matchId' => $matchId]);
echo "recalc done: {$matchId}\n";

