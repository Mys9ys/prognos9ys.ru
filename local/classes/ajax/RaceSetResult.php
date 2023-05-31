<?php

use Bitrix\Main\Loader;

class RaceSetResult
{
    protected $arResult;
    protected $data;

    protected $arTypes = [
        'qual' => 80,
        'sprint' => 81,
        'race' => 82,
        'best_lap' => 91,
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

        CIBlockElement::SetPropertyValueCode($this->data['race_id'], $this->arTypes[$this->data['type']] , json_encode($this->data['data']));

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