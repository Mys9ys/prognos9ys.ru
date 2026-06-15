<?php

/**
 * Одноразовая установка модуля prognos9ys.main без админки.
 * Запуск из корня сайта:
 * php local/modules/prognos9ys.main/install_module.php
 */

$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../../..');
$DOCUMENT_ROOT = $_SERVER['DOCUMENT_ROOT'];

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
define('BX_CRONTAB', true);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

if (\Bitrix\Main\ModuleManager::isModuleInstalled('prognos9ys.main')) {
    echo "Модуль prognos9ys.main уже установлен\n";
    exit(0);
}

require_once __DIR__ . '/install/index.php';

$module = new prognos9ys_main();
$module->DoInstall();

echo "Модуль prognos9ys.main успешно установлен\n";
