<?php

use Bitrix\Main\{Loader, UserTable};

class PrognosisAdminInfo extends CBitrixComponent{

    protected $prognosisIb;


    protected $arUsers = [];
    protected $arRef = [];

    public function __construct($component = null)
    {
        parent::__construct($component);

        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        };

        $this->prognosisIb = \CIBlock::GetList([], ['CODE' => 'prognosis'], false)->Fetch()['ID'] ?: 6;

        $this->getUsers();

        if($this->arUsers) $this->calcRefUsers();

        $this->calcUserPrognosis();


        ksort($this->arResult["matches"], SORT_NUMERIC );

    }


    public function executeComponent()
    {


        $this->includeComponentTemplate();
    }

    protected function getUsers(){
        $row = UserTable::getList([
            "select" => ["ID","NAME", "PERSONAL_PAGER", "WORK_PAGER", "LOGIN"],
        ]);

        while ($res = $row->fetch()){
            $res["name"] = $res["NAME"] . " - " . explode('@', $res["LOGIN"])[0];
            $this->arUsers[$res["ID"]] = $res;
            if($res["WORK_PAGER"]) $this->arRef[$res["WORK_PAGER"]][] = $res["name"];
        }
    }

    public function calcRefUsers()
    {
        foreach ($this->arUsers as $user){
            if($this->arRef[$user["PERSONAL_PAGER"]]) {
                $this->arResult["ref"][$user["name"]] = $this->arRef[$user["PERSONAL_PAGER"]];
            }
        }
    }

    public function calcUserPrognosis()
    {

        $arFilter = [];
        $arFilter["IBLOCK_ID"] = $this->prognosisIb;

        $response = CIBlockElement::GetList(
            [],
            $arFilter,
            false,
            [],
            [
                "ID",
                "PROPERTY_user_id",
                "PROPERTY_id",
            ]
        );

        while ($res = $response->GetNext()) {
            $this->arResult["prognosis"][$this->arUsers[$res["PROPERTY_USER_ID_VALUE"]]["name"]][] = $res["ID"];
            $this->arResult["matches"][(int)$res["PROPERTY_ID_VALUE"]][] = $res["ID"];
        }

    }
    
    public function calcMatchPrognosis($id){

    }
}