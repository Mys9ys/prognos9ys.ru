<?php

use Bitrix\Main\UserTable;

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept');

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

file_put_contents('../_logs/debug_new.json', json_encode($_REQUEST), FILE_APPEND);

$res= new VueRegistrationClass($_REQUEST);

echo json_encode($res->getStatus());

class VueRegistrationClass
{

    protected $regData = [];

    protected $arrError = [
        'status' => '',
        'mes' => ''
    ];

    public function __construct($data)
    {
        if ($data) $this->regData = $data;

        if($this->regData['type'] === 'reg') $this->userRegistration();

        if($this->regData['type'] === 'check') $this->checkImportantInput();

    }

    protected function userRegistration()
    {
        $arIMAGE = CFile::MakeFileArray($_SERVER["DOCUMENT_ROOT"].$this->regData['file']);

        $arrFio = explode(' ', $this->regData['nick']);

        $user = new CUser;
        $arFields = Array(
            "NAME"              => $this->regData['nick'],
            "EMAIL"             => $this->regData['mail'],
            "LOGIN"             => $this->regData['mail'],
            "LID"               => "ru",
            "ACTIVE"            => "Y",
            "GROUP_ID"          => array(3,4),
            "PASSWORD"          => $this->regData['pass'],
            "CONFIRM_PASSWORD"  => $this->regData['pass2'],
            "PERSONAL_PHOTO"    => $arIMAGE,
        );

        $ID = $user->Add($arFields);

        if($user->LAST_ERROR) {
            $this->arrError['status'] = 'error';
            $this->arrError['mes'] = explode('(', $user->LAST_ERROR)[0] . 'уже существует';
        } else {
            $this->arrError['status'] = 'ok';
        }

    }

    protected function checkImportantInput(){

        $filter = [];
        $mes = '';

        if($this->regData['name'] === 'mail') {
            $filter['=EMAIL'] = $this->regData["value"];
            $mes = 'E-mail уже используется';
        }

        $dbUser = UserTable::getList(array(
            'select' => ['ID'],
            'filter' => $filter
        ))->fetch();

        if($dbUser['ID']){
            $this->arrError['status'] = 'error';
            $this->arrError['result'] = [
                'name' => $this->regData['name'],
                'mes'=>$mes
            ];
        }

    }

    public function getStatus(){
        return $this->arrError;
    }
}