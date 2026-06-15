<?php

use Bitrix\Main\Application;
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

class prognos9ys_main extends CModule
{
    public $MODULE_ID = 'prognos9ys.main';

    public $MODULE_NAME;
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_DESCRIPTION;
    public $PARTNER_NAME;
    public $PARTNER_URI;

    public $MODULE_GROUP_RIGHTS = 'Y';

    public function __construct()
    {
        include __DIR__ . '/version.php';

        $this->MODULE_VERSION = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
        $this->MODULE_NAME = Loc::getMessage('PROGNOS9YS_MAIN_MODULE_NAME');
        $this->MODULE_DESCRIPTION = Loc::getMessage('PROGNOS9YS_MAIN_MODULE_DESCRIPTION');
        $this->PARTNER_NAME = Loc::getMessage('PROGNOS9YS_MAIN_PARTNER_NAME');
        $this->PARTNER_URI = Loc::getMessage('PROGNOS9YS_MAIN_PARTNER_URI');
    }

    public function DoInstall(): void
    {
        Application::getConnection()->startTransaction();

        try {
            RegisterModule($this->MODULE_ID);
            Application::getConnection()->commitTransaction();
        } catch (\Throwable $exception) {
            Application::getConnection()->rollbackTransaction();
            throw $exception;
        }
    }

    public function DoUninstall(): void
    {
        if (!check_bitrix_sessid()) {
            return;
        }

        UnRegisterModule($this->MODULE_ID);
    }
}
