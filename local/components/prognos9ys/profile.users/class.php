<?php

use Bitrix\Main\{Loader, UserTable};

class PrognosisProfile extends CBitrixComponent{

    protected $userId;
    protected $prognosisIb;
    public function __construct($component = null)
    {
        parent::__construct($component);

        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        };

        $this->userId = CUser::GetID()?: '';

        $this->prognosisIb = \CIBlock::GetList([], ['CODE' => 'prognosis'], false)->Fetch()['ID'] ?: 6;
    }


    public function executeComponent()
    {
        if($this->userId) {

            $user =[];
            $dbUser = UserTable::getList(array(
                'select' => array('ID', 'NAME', 'PERSONAL_PHOTO', 'PERSONAL_PAGER', 'WORK_PAGER'),
                'filter' => array('ID' => $this->userId)
            ));
            if ($arUser = $dbUser->fetch()){

                $user['name'] = $arUser['NAME'];
                $user['id'] = $arUser['ID'];
                $user['ref_link'] = 'https://prognos9ys.ru/auth/?register=yes&ref=' . $arUser['PERSONAL_PAGER'];
                $user['ref_nik'] = '';
               
                if($arUser['WORK_PAGER']) $user['ref_nik'] = $this->getRefNik($arUser['WORK_PAGER']) ?: '';

                if($arUser['PERSONAL_PAGER']) $user['you_ref'] = $this->getRefUsers($arUser['PERSONAL_PAGER']);
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

    public function getRefUsers($ref)
    {
        $arr = [];
        $arrActive = [];
        $dbUser = UserTable::getList(array(
            'select' => array('ID'),
            'filter' => array('WORK_PAGER' => $ref)
        ));
        
        while($res = $dbUser->fetch()){
            $arr[] = $res["ID"];
            if($this->getCountPrognisis($res["ID"])) $arrActive[] = ["ID"];
        }

        return ["count" => count($arr), "active" => count($arrActive)];
    }
    
    public function getCountPrognisis($id){

        $this->arFilter["IBLOCK_ID"] = $this->prognosisIb;
        $this->arFilter["PROPERTY_USER_ID"] = $id;

        $arr = [];
        $response = CIBlockElement::GetList(
            ["DATE_ACTIVE_FROM" => "ASC"],
            $this->arFilter,
            false,
            [
//                "nTopCount" => 6
            ],
            [
                "ID",
            ]
        );

        while ($res = $response->GetNext()) {

            $arr[] = $res["ID"];

        }

        return (count($arr) > 4) ? true : false;
    }
}