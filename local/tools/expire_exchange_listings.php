<?php

declare(strict_types=1);

use Bitrix\Main\Loader;
use Prognos9ys\Main\Service\Game\ExchangeService;

$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../../');
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

if (!Loader::includeModule('prognos9ys.main')) {
    fwrite(STDERR, "Module prognos9ys.main not loaded\n");
    exit(1);
}

$expired = (new ExchangeService())->expireListings();
echo 'Expired exchange listings: ' . $expired . PHP_EOL;
