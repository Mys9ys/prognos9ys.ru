<?php

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
define('BX_CRONTAB', true);

$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../../..');

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

$installed = \Bitrix\Main\ModuleManager::isModuleInstalled('prognos9ys.main');
$loaded = \Bitrix\Main\Loader::includeModule('prognos9ys.main');
$controller = class_exists(\Prognos9ys\Main\Controller\ProfileController::class);

echo 'installed: ' . ($installed ? 'yes' : 'no') . PHP_EOL;
echo 'loaded: ' . ($loaded ? 'yes' : 'no') . PHP_EOL;
echo 'controller: ' . ($controller ? 'yes' : 'no') . PHP_EOL;
