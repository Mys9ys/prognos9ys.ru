<?php

use Bitrix\Main\{Loader, UserTable};

class PrognosisProfile extends CBitrixComponent{

    protected $user_id;
    public function __construct($component = null)
    {
        parent::__construct($component);

        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        };

        $this->user_id = CUser::GetID()?: '';
    }


    public function executeComponent()
    {
        if($this->user_id) {

            $user =[];
            $dbUser = UserTable::getList(array(
                'select' => array('ID', 'NAME', 'PERSONAL_PHOTO', 'PERSONAL_PAGER', 'WORK_PAGER'),
                'filter' => array('ID' => $this->user_id)
            ));
            if ($arUser = $dbUser->fetch()){

                $user['name'] = $arUser['NAME'];
                $user['id'] = $arUser['ID'];
                $user['ref_link'] = 'https://prognos9ys.ru/auth/?register=yes&ref=' . $arUser['PERSONAL_PAGER'];
                $user['ref_nik'] = $this->getRefNik($arUser['WORK_PAGER']) ?: '';
                $this->arResult = $user;
            }
        }

        $this->includeComponentTemplate();
    }

    /**
     * @return string
     */
    public function getRefNik($ref)
    {
        $dbUser = UserTable::getList(array(
            'select' => array('NAME'),
            'filter' => array('PERSONAL_PAGER' => $ref)
        ))->fetch();

        return $dbUser["NAME"];
    }
}