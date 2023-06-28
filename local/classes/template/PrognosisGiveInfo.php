<?php

class PrognosisGiveInfo
{
    protected $arGive = [
        'status' => '',
        'mes' => ''
    ];

    protected function setResult($status, $mes, $data = '')
    {
        $this->arGive['status'] = $status;
        $this->arGive['mes'] = $mes;
        if ($data) $this->arGive['data'] = $data;
    }

    public function result()
    {
        return $this->arGive;
    }

}