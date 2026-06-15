<?php

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header("Access-Control-Allow-Headers: X-Requested-With");

header('Content-Type: text/html; charset=utf-8');

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Loader;

$_REQUEST['date'] = date(\CDatabase::DateFormatToPHP("DD.MM.YYYY HH:MI:SS"), time());

file_put_contents('../../_logs/send.log', json_encode($_REQUEST) . PHP_EOL, FILE_APPEND);

if($_REQUEST){
    $res = new SendUserPrognosis($_REQUEST);

    echo json_encode($res->getArResult());
}


class SendUserPrognosis {

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
            $this->arResult['status'] = 'error';
            $this->arResult['mes'] = 'Ошибка авторизации';
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
            $this->arResult['mes'] = $ib->Update($this->checkOld, $data);
        } else {
            $data["NAME"] = "Участник: " .$this->arFields[31] . " Прогноз на матч: " . $this->arFields[30];
            $this->arResult['mes'] = $ib->Add($data);
        }
    }

    /**
     * @return string[]
     */
    public function getArResult(): array
    {
        return $this->arResult;
    }
}