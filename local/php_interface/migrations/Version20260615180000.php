<?php

namespace Sprint\Migration;

class Version20260615180000 extends Version
{
    protected $description = 'Регистрация модуля prognos9ys.main';

    protected $moduleVersion = '4.1.1';

    public function up()
    {
        if (\Bitrix\Main\ModuleManager::isModuleInstalled('prognos9ys.main')) {
            $this->out('Модуль prognos9ys.main уже установлен');

            return;
        }

        require_once $_SERVER['DOCUMENT_ROOT'] . '/local/modules/prognos9ys.main/install/index.php';

        $module = new \prognos9ys_main();
        $module->DoInstall();

        $this->outSuccess('Модуль prognos9ys.main установлен');
    }

    public function down()
    {
        if (!\Bitrix\Main\ModuleManager::isModuleInstalled('prognos9ys.main')) {
            return;
        }

        require_once $_SERVER['DOCUMENT_ROOT'] . '/local/modules/prognos9ys.main/install/index.php';

        $module = new \prognos9ys_main();
        $module->DoUninstall();

        $this->outSuccess('Модуль prognos9ys.main удалён');
    }
}
