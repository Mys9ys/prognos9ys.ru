<?php

use Bitrix\Main\Loader;

class GetUserRole
{
    protected $userId;
    protected $arRole = [
        'moder' => 7,
        'admin' => 1, // если до нее доходит у этой роли приоритет
    ];

    protected $arResult;

    public function __construct($token)
    {
        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        }

        $this->userId = (new GetUserIdForToken($token))->getId();

        $this->getRole();

    }

    protected function getRole(){

        foreach ($this->arRole as $role=>$id){
            if(in_array($id,CUser::GetUserGroup($this->userId))) {
                $this->arResult = $role;
            }
        }

        if(!$this->arResult) $this->arResult = 'user';

    }

    public function result(){
        return $this->arResult ?? 'user';
    }
}