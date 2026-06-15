<?php

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../../..');

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

\Bitrix\Main\Loader::includeModule('prognos9ys.main');

$userId = 1;
$eventId = 63849;

echo '=== profile.getPublicProfile ===' . PHP_EOL;
$result = (new \Prognos9ys\Main\Controller\ProfileController())->getPublicProfileAction($userId);
echo 'status: ' . ($result['status'] ?? '') . PHP_EOL;
echo 'name: ' . ($result['profile']['info']['NAME'] ?? '') . PHP_EOL;
echo PHP_EOL;

echo '=== football.getMatchesByEvent ===' . PHP_EOL;
$matches = (new \Prognos9ys\Main\Controller\FootballController())->getMatchesByEventAction($eventId);
echo 'total: ' . ($matches['total'] ?? 0) . PHP_EOL;
echo PHP_EOL;

echo '=== football.getEventMatches ===' . PHP_EOL;
$eventMatches = (new \Prognos9ys\Main\Controller\FootballController())->getEventMatchesAction((string)$eventId, '');
echo 'status: ' . ($eventMatches['status'] ?? '') . PHP_EOL;
echo 'sections: ' . count($eventMatches['info'] ?? []) . PHP_EOL;
