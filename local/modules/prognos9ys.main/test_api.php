<?php

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
define('BX_CRONTAB', true);

$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../../..');

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

\Bitrix\Main\Loader::includeModule('prognos9ys.main');
\Bitrix\Main\Loader::includeModule('iblock');

// Реальные ID из БД
$user = \Bitrix\Main\UserTable::getRow([
    'select' => ['ID', 'LOGIN', 'NAME'],
    'filter' => ['!UF_TOKEN' => false],
    'order' => ['ID' => 'ASC'],
]);

$event = \CIBlockElement::GetList(
    ['ID' => 'ASC'],
    ['IBLOCK_ID' => 1, 'ACTIVE' => 'Y'],
    false,
    ['nTopCount' => 1],
    ['ID', 'NAME', 'CODE']
);
$eventRow = $event->GetNext();

echo "=== Sample IDs ===" . PHP_EOL;
echo 'userId: ' . ($user['ID'] ?? 'none') . ' (' . ($user['LOGIN'] ?? '') . ')' . PHP_EOL;
echo 'eventId: ' . ($eventRow['ID'] ?? 'none') . ' (' . ($eventRow['NAME'] ?? '') . ')' . PHP_EOL;
echo PHP_EOL;

if ($user) {
    echo "=== ProfileController::getPublicProfile ===" . PHP_EOL;
    try {
        $profile = (new \Prognos9ys\Main\Service\Profile\PublicProfileService())->getByUserId((int)$user['ID']);
        echo 'status: ok' . PHP_EOL;
        echo 'user.name: ' . ($profile['user']['name'] ?? '') . PHP_EOL;
        echo 'rank keys: ' . implode(', ', array_keys($profile['rank'] ?? [])) . PHP_EOL;
    } catch (\Throwable $e) {
        echo 'error: ' . $e->getMessage() . PHP_EOL;
    }
    echo PHP_EOL;
}

if ($eventRow) {
    echo "=== MatchesController::getByEvent ===" . PHP_EOL;
    try {
        $matches = (new \Prognos9ys\Main\Service\Football\MatchListService())->getByEventId((int)$eventRow['ID']);
        echo 'status: ok' . PHP_EOL;
        echo 'total: ' . ($matches['total'] ?? 0) . PHP_EOL;
        if (!empty($matches['items'][0])) {
            echo 'first match id: ' . $matches['items'][0]['id'] . PHP_EOL;
            echo 'first match number: ' . $matches['items'][0]['number'] . PHP_EOL;
        }
    } catch (\Throwable $e) {
        echo 'error: ' . $e->getMessage() . PHP_EOL;
    }
}
