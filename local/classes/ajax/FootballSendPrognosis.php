<?php

use Bitrix\Main\Loader;

class FootballSendPrognosis extends PrognosisGiveInfo
{
    protected $prognIb;
    protected $userId;

    protected $checkOld = '';

    protected $arFields = [];

    protected $arResult = [
        'status' => 'ok'
    ];

    public function __construct($data)
    {
        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        }
        $this->prognIb = \CIBlock::GetList([], ['CODE' => 'prognosis'], false)->Fetch()['ID'] ?: 6; //прогнозы

        if ($data['userToken']) {
            $userRes = new GetUserIdForToken($data['userToken']);
            $this->userId = $userRes->getId();

            $this->arFields = $data['fields'];
        }

        if($this->userId) {

            $this->arFields[31] = $this->userId;
            $this->uploadUserPrognosis();

        } else {
            $this->setResult('error', 'Ошибка авторизации');
        }

    }

    protected function checkOldPrognosis(){

        $arFilter = [
            "IBLOCK_ID" => $this->prognIb,
            "PROPERTY_USER_ID" => $this->userId,
            "PROPERTY_MATCH_ID" => $this->arFields["17"]
        ];

        $res = CIBlockElement::GetList(
            [],
            $arFilter,
            false,
            [],
            [   "ID",
            ]
        )->GetNext();

        $this->checkOld = $res["ID"];
    }

    protected function uploadUserPrognosis(){
        $this->checkOldPrognosis();

        $ib = new CIBlockElement;
        $data = [
            "IBLOCK_ID" => $this->prognIb,
            'DATE_ACTIVE_FROM' => date(\CDatabase::DateFormatToPHP("DD.MM.YYYY HH:MI:SS"), time()),
            "PROPERTY_VALUES"=>$this->arFields
        ];

        if($this->checkOld){
            if($ib->Update($this->checkOld, $data)){
                $this->setResult('ok', '');
            } else {
                $this->setResult('error', 'Ошибка записи');
            }
        } else {
            $data["NAME"] = "Участник: " .$this->arFields[31] . " Прогноз на матч: " . $this->arFields[30];
            if($ib->Add($data)){
                $this->setResult('ok', '');
            } else {
                $this->setResult('error', 'Ошибка записи');
            }
        }
    }

}