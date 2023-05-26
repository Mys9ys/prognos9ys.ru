<?php

use Bitrix\Main\Loader;

class RaceSendHandler
{

    protected $arIb = ['prognosf1'=>['code' => 'prognosf1', 'id' =>13]];
    protected $arTypes = [
        'qual' => 23,
        'sprint' => 23,
        'race' => 23,
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

        $this->setData();

        if ($this->arResult) {
            $this->setResult('ok', '');
        } else {
            $this->setResult('error', 'Ошибка запроса');
        }
    }

    protected function setData(){

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