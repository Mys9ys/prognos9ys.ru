<?php

/**
 * Одноразовая установка модуля prognos9ys.main без админки.
 * Запуск из корня сайта:
 * php local/modules/prognos9ys.main/install_module.php
 *
 * Работает даже на старом PHP CLI 5.6 (только RegisterModule).
 * Для полной проверки используйте PHP 7.4+ CLI или verify_module.php.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

echo 'php_version: ' . PHP_VERSION . PHP_EOL;

$documentRoot = realpath(dirname(dirname(dirname(__DIR__))));

if ($documentRoot === false) {
    fwrite(STDERR, 'ERROR: не удалось определить корень сайта от ' . __DIR__ . PHP_EOL);
    exit(1);
}

$_SERVER['DOCUMENT_ROOT'] = $documentRoot;
$DOCUMENT_ROOT = $documentRoot;

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
define('BX_CRONTAB', true);

echo 'document_root: ' . $documentRoot . PHP_EOL;

$prolog = $documentRoot . '/bitrix/modules/main/include/prolog_before.php';
if (!is_file($prolog)) {
    fwrite(STDERR, 'ERROR: не найден Bitrix prolog: ' . $prolog . PHP_EOL);
    exit(1);
}

require $prolog;

$moduleId = 'prognos9ys.main';

if (\Bitrix\Main\ModuleManager::isModuleInstalled($moduleId)) {
    echo 'Модуль ' . $moduleId . ' уже установлен' . PHP_EOL;
} else {
    $settingsPath = \Bitrix\Main\Loader::getLocal('modules/' . $moduleId . '/.settings.php');
    if (!$settingsPath || !is_file($documentRoot . $settingsPath)) {
        fwrite(STDERR, 'ERROR: не найден .settings.php модуля (' . $moduleId . ')' . PHP_EOL);
        exit(1);
    }

    RegisterModule($moduleId);

    if (!\Bitrix\Main\ModuleManager::isModuleInstalled($moduleId)) {
        fwrite(STDERR, 'ERROR: RegisterModule не сработал, модуль не зарегистрирован' . PHP_EOL);
        exit(1);
    }

    echo 'Модуль ' . $moduleId . ' успешно установлен' . PHP_EOL;
}

$controllersConfig = \Bitrix\Main\Config\Configuration::getInstance($moduleId)->get('controllers');
echo 'controllers_config: ' . ($controllersConfig ? 'yes' : 'no') . PHP_EOL;

if (!$controllersConfig) {
    fwrite(STDERR, 'ERROR: controllers не найден в конфигурации модуля' . PHP_EOL);
    exit(1);
}

exit(0);
