<?php

declare(strict_types=1);

use Bitrix\Main\Loader;
use Prognos9ys\Main\Service\Game\GameEconomyHlInstaller;

$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../../..');
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

if (!Loader::includeModule('prognos9ys.main')) {
    fwrite(STDERR, "Module prognos9ys.main not loaded\n");
    exit(1);
}

try {
    $result = (new GameEconomyHlInstaller())->upgradeLaborExchangeHl();
    echo 'Labor exchange HL installed: ' . json_encode($result, JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
