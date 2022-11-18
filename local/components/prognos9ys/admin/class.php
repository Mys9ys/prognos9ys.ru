<?php

use Bitrix\Main\{Loader, UserTable};

class PrognosisProfile extends CBitrixComponent{

    protected $prognosisIb;

    public function __construct($component = null)
    {
        parent::__construct($component);

        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        };

        $this->prognosisIb = \CIBlock::GetList([], ['CODE' => 'prognosis'], false)->Fetch()['ID'] ?: 6;
    }


    public function executeComponent()
    {


        $this->includeComponentTemplate();
    }

    /**
     * @return string
     */
    public function getRefNik($ref)
    {

    }

    public function getRefUsers($ref)
    {

    }
    
    public function getCountPrognisis($id){


    }
}