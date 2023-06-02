<?php

use Bitrix\Main\Loader;
use Bitrix\Main\UserTable;

class GetUserIdForToken
{
    protected $userId;
    protected $token;

    protected $arError;

    public function __construct($token)
    {
        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        }
        $this->token = $token;
        $this->getUserId();

        if (!$this->userId) {
            $this->arError['status'] = 'error';
            $this->arError['mes'] = 'Пользователь не найден';
        }

    }

    protected function getUserId()
    {
        $dbUser = UserTable::getList(array(
            'select' => array('ID'),
            'filter' => array('=UF_TOKEN' => $this->token)
        ))->fetch();

        $this->userId = $dbUser['ID'];
    }

    public function getId()
    {
        return $this->userId;
    }

    public function getError(){
        return $this->arError;
    }
}