<?php

use Bitrix\Main\Loader;

class RaceSendHandler
{

    protected $arIb = ['prognosf1' => ['code' => 'prognosf1', 'id' => 13]];
    protected $arTypes = [
        'qual' => 86,
        'sprint' => 87,
        'race' => 88,
    ];
    protected $data;
    protected $arResult;

    public function __construct($data)
    {
        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        }

        $this->data = $data;

        $this->data['userId'] = (new GetUserIdForToken($this->data['userToken']))->getId();

        $this->checkPrognosis();

        if(!$this->data['old']){
            $this->createNew();
        } else {
            $this->updatePrognosis();
        }

        if ($this->arResult) {
            $this->setResult('ok', '');
        } else {
            $this->setResult('error', 'Ошибка запроса');
        }
    }

    protected function checkPrognosis()
    {
        $arFilter = [
            "IBLOCK_ID" => $this->arIb['prognosf1']['id'],
            'PROPERTY_events' => $this->data['events'],
            'PROPERTY_number' => $this->data['number']
        ];


        $res = CIBlockElement::GetList(
            [],
            $arFilter,
            false,
            [],
            ["ID",]
        )->GetNext();

        $this->data['old'] = $res["ID"];
    }

    protected function createNew()
    {
        $arFields = [
            83 => $this->data['number'], // number
            84 => $this->data['userId'], // user_id
            85 => $this->data['race_id'], // race_id
            89 => $this->data['events'], // events
        ];
        $arFields[$this->arTypes[$this->data['type']]] = json_encode($this->data['data']);

        $ib = new CIBlockElement;
        $data = [
            "IBLOCK_ID" => $this->arIb['prognosf1']['id'],
            'DATE_ACTIVE_FROM' => date(\CDatabase::DateFormatToPHP("DD.MM.YYYY HH:MI:SS"), time()),
            "PROPERTY_VALUES"=>$arFields,
            "NAME" => "Участник: " .$this->data['userId'] . " Добавил : " . date(\CDatabase::DateFormatToPHP("DD.MM.YYYY HH:MI:SS"), time())
        ];

        $ib->Add($data);

        $this->arResult['status'] = 'ok';
    }

    protected function updatePrognosis(){
        CIBlockElement::SetPropertyValueCode($this->data['old'], $this->arTypes[$this->data['type']] , json_encode($this->data['data']));

        $this->arResult['status'] = 'ok';
    }

    protected function setResult($status, $mes, $info = '')
    {
        $this->arResult['status'] = $status;
        $this->arResult['mes'] = $mes;
    }

    public function result()
    {
        return $this->arResult;
    }
}