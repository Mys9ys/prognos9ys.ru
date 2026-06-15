<?php

/**
 * Проверка установки модуля prognos9ys.main.
 * Запуск из корня сайта:
 * php local/modules/prognos9ys.main/verify_module.php
 *
 * На хостинге CLI часто старый (php 5.6), а сайт работает на 7.4+.
 * Если CLI < 7.4, попробуйте: php74, php7.4, /opt/php74/bin/php ...
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

echo 'php_version: ' . PHP_VERSION . PHP_EOL;
echo 'php_sapi: ' . PHP_SAPI . PHP_EOL;

if (version_compare(PHP_VERSION, '7.4.0', '<')) {
    echo 'WARNING: модуль prognos9ys.main требует PHP 7.4+.' . PHP_EOL;
    echo 'Текущий CLI слишком старый — проверка классов контроллера будет пропущена.' . PHP_EOL;
    echo 'Попробуйте: php74 local/modules/prognos9ys.main/verify_module.php' . PHP_EOL;
}

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
define('BX_CRONTAB', true);

$documentRoot = realpath(dirname(dirname(dirname(__DIR__))));

if ($documentRoot === false) {
    fwrite(STDERR, 'ERROR: не удалось определить корень сайта от ' . __DIR__ . PHP_EOL);
    exit(1);
}

$_SERVER['DOCUMENT_ROOT'] = $documentRoot;

echo 'document_root: ' . $documentRoot . PHP_EOL;

$prolog = $documentRoot . '/bitrix/modules/main/include/prolog_before.php';
if (!is_file($prolog)) {
    fwrite(STDERR, 'ERROR: не найден Bitrix prolog: ' . $prolog . PHP_EOL);
    exit(1);
}

require $prolog;

$moduleId = 'prognos9ys.main';
$settingsPath = \Bitrix\Main\Loader::getLocal('modules/' . $moduleId . '/.settings.php');

echo 'settings_file: ' . ($settingsPath ? $settingsPath : 'not found') . PHP_EOL;

$installed = \Bitrix\Main\ModuleManager::isModuleInstalled($moduleId);
$controllersConfig = \Bitrix\Main\Config\Configuration::getInstance($moduleId)->get('controllers');

echo 'installed: ' . ($installed ? 'yes' : 'no') . PHP_EOL;
echo 'controllers_config: ' . ($controllersConfig ? 'yes' : 'no') . PHP_EOL;

if (!$installed) {
    echo PHP_EOL . 'Модуль не установлен. Запустите:' . PHP_EOL;
    echo 'php local/modules/prognos9ys.main/install_module.php' . PHP_EOL;
    exit(1);
}

if (!$controllersConfig) {
    echo PHP_EOL . 'ERROR: Bitrix не видит controllers в .settings.php.' . PHP_EOL;
    echo 'Убедитесь, что модуль установлен (installed: yes).' . PHP_EOL;
    exit(1);
}

if (version_compare(PHP_VERSION, '7.4.0', '>=')) {
    $loaded = \Bitrix\Main\Loader::includeModule($moduleId);
    $controller = class_exists('Prognos9ys\\Main\\Controller\\ProfileController');

    echo 'loaded: ' . ($loaded ? 'yes' : 'no') . PHP_EOL;
    echo 'controller: ' . ($controller ? 'yes' : 'no') . PHP_EOL;

    if (!$loaded || !$controller) {
        exit(1);
    }
} else {
    echo 'loaded: skipped (нужен PHP 7.4+ CLI)' . PHP_EOL;
    echo 'controller: skipped (нужен PHP 7.4+ CLI)' . PHP_EOL;
    echo PHP_EOL . 'Если installed и controllers_config = yes, API на сайте должно работать при PHP 7.4+ в веб-сервере.' . PHP_EOL;
}

exit(0);
