<?php

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept');

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Loader;
use Bitrix\Main\UserTable;

$_REQUEST['date'] = date(\CDatabase::DateFormatToPHP("DD.MM.YYYY HH:MI:SS"), time());

file_put_contents('../_logs/recoverPass.log', json_encode($_REQUEST) . PHP_EOL, FILE_APPEND);

if ($_REQUEST) {

    $res = new LKSetPass($_REQUEST);

    echo json_encode($res->result());
}

class LKSetPass
{

    protected $data = [];

    protected $arResult = [];

    public function __construct($data)
    {
        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        }

        $this->data = $data;

        if ($this->data) {
            $this->checkUser();

            if($this->arResult["status"] === 'ok'){
                $this->updatePass();
            }
        }
    }

    protected function checkUser(){
        $arFilter['ID'] = $this->data['userId'];

        $dbUser = UserTable::getList(array(
            'select' => ['UF_SMSCODE'],
            'filter' => $arFilter
        ))->fetch();

        $code = str_replace('-', '',$this->data['code']);

        if($dbUser && $dbUser["UF_SMSCODE"] === $code) {
            $this->setResult('ok','');
        } else {
            $this->setResult('error','Код не верный');
        }
    }

    protected function updatePass(){
        $user = new CUser;
        $fields = array(
            "PASSWORD"          => $this->data['pass'],
            "CONFIRM_PASSWORD"  => $this->data['pass'],
        );

        if(!$user->Update($this->data['userId'], $fields)){
            $this->setResult('error','Не удалось сохранить пароль');
        }
    }

    protected function setResult($status,$mes)
    {
        $this->arResult['status'] = $status;
        $this->arResult['mes'] = $mes;
    }

    public function result()
    {
        return $this->arResult;
    }

}
