<?php

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
define('BX_CRONTAB', true);

$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../../..');

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

\Bitrix\Main\Loader::includeModule('prognos9ys.main');

if (class_exists(\Bitrix\Main\Data\ManagedCache::class)) {
    $cache = \Bitrix\Main\Data\Cache::createInstance();
    $cache->cleanDir('/bitrix/managed_cache');
}

if (class_exists(\Bitrix\Main\Application::class)) {
    \Bitrix\Main\Application::getInstance()->getManagedCache()->cleanAll();
}

$config = \Bitrix\Main\Config\Configuration::getInstance('prognos9ys.main')->get('controllers');
echo 'controllers config dump:' . PHP_EOL;
var_export($config);
echo PHP_EOL;
