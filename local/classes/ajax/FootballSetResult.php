<?php

use Bitrix\Main\Loader;

class FootballSetResult
{
    protected $data;
    protected $arResult;

    protected $arIbs = [
        'matches' => ['code' => 'matches', 'id' => 2]
    ];

    public function __construct($data)
    {
        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        }

        $this->data = $data;

        $role = (new GetUserRole($this->data['userToken']))->result();

        if ($role !== $this->data['role']) {
            $this->setResult('error', 'У вас нет доступа к данной операции');
        } else {
            $this->setResultEvent();
        }

    }

    protected function setResultEvent()
    {

        CIBlockElement::SetPropertyValuesEx($this->data['matchId'], $this->arIbs['matches']['id'], $this->data['data']);

        $this->setResult('ok', '');

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