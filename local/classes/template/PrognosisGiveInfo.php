<?php

class PrognosisGiveInfo
{
    protected $arGive = [
        'status' => '',
        'mes' => ''
    ];

    protected function convertData($data){
        $date = explode("+", ConvertDateTime($data, "DD.MM+HH:Mi"));

        return [
            "date" => $date[0],
            "time" => $date[1]
        ];
    }

    protected function setResult($status, $mes, $data = '')
    {
        $this->arGive['status'] = $status;
        $this->arGive['mes'] = $mes;
        if ($data) $this->arGive['result'] = $data;
    }

    public function result()
    {
        return $this->arGive;
    }

}