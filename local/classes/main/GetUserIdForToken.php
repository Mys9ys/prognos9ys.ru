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

        $this->token = is_string($token) ? trim($token) : '';
        if ($this->token === '') {
            $this->userId = null;
            return;
        }

        $this->getUserId();

        if (!$this->userId) {
            $this->arError['status'] = 'error';
            $this->arError['mes'] = 'Пользователь не найден';
        }

    }

    protected function getUserId()
    {
        $dbUser = UserTable::getList([
            'select' => ['ID'],
            'filter' => ['=UF_TOKEN' => $this->token],
            'limit' => 1,
        ])->fetch();

        $this->userId = $dbUser ? (int)$dbUser['ID'] : null;
    }

    public function getId()
    {
        return $this->userId;
    }

    public function getError(){
        return $this->arError;
    }
}