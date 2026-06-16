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

if (!\Bitrix\Main\Loader::includeModule('prognos9ys.main')) {
    echo "module prognos9ys.main not loaded\n";
    exit(1);
}

$hlOk = \Bitrix\Main\Loader::includeModule('highloadblock');
echo 'highloadblock loaded: ' . ($hlOk ? 'Y' : 'N') . "\n";

$userId = (int)($_SERVER['argv'][1] ?? 0);
if ($userId <= 0) {
    echo "Usage: php diag_chests.php <userId>\n";
    exit(1);
}

$repo = new \Prognos9ys\Main\Model\Repository\GameEconomyRepository();
$total = $repo->getTreasureChestTotalForUser($userId);
echo "user {$userId} closed chests total: {$total}\n";

try {
    $dc = $repo->getTreasureChestDataClass();
    $rows = 0;
    $rs = $dc::getList(['select' => ['ID', 'UF_USER_ID', 'UF_MATCH_ID', 'UF_COUNT', 'UF_STATUS']]);
    while ($r = $rs->fetch()) {
        $rows++;
        if ($rows <= 5) {
            echo 'sample: ' . json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
        }
    }
    echo "total chest rows in HL: {$rows}\n";
} catch (\Throwable $e) {
    echo 'HL read error: ' . $e->getMessage() . "\n";
}

