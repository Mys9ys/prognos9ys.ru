<?php

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept');

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Loader;
use Bitrix\Main\UserTable;

$_REQUEST['date'] = date(\CDatabase::DateFormatToPHP("DD.MM.YYYY HH:MI:SS"), time());

file_put_contents('../_logs/recover_code.log', json_encode($_REQUEST) . PHP_EOL, FILE_APPEND);

if ($_REQUEST) {

    $res = new LKGenerateRecoverCode($_REQUEST);

    echo json_encode($res->result());

}

class LKGenerateRecoverCode
{

    protected $data = [];

    protected $userId;
    protected $code;
    protected $phone;
    protected $mail;

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

            if ($this->userId) {

                $this->genCode();
                $this->writeCodeUserInfo();
                $this->sendCode();

            } else {
                $this->setResult('error', 'Пользователь не найден');
            }
        }
    }

    protected function checkUser()
    {

        if ($this->data['type'] === 'recoverMail') {
            $this->mail = $this->data['mail'];
            $arFilter['EMAIL'] = $this->mail;
        }
        if ($this->data['type'] === 'recoverPhone') {
            $this->phone = \NormalizePhone($this->data['phone']);
            $arFilter['PERSONAL_PHONE'] = $this->phone;
        }

        $dbUser = UserTable::getList(array(
            'select' => ['ID'],
            'filter' => $arFilter
        ))->fetch();

        if ($dbUser['ID']) {
            $this->userId = $dbUser['ID'];
        }

    }

    protected function sendCode()
    {
        if ($this->data['type'] === 'recoverMail') $this->sendMail();
    }

    protected function sendMail()
    {
        $to = $this->data['mail'];
        $subject = 'Восстановление пароля в системе Prognos9ys.ru';
        $message = 'Код для восстановления: ' . $this->code;
        $headers = 'From: a.avdiuk@eurokappa.ru' . "\r\n" .
            'X-Mailer: PHP/' . phpversion();

        $res = mail($to, $subject, $message, $headers);

        $this->setResult('ok', '', $this->userId);

        file_put_contents('../_logs/recover_send_mail.log', json_encode($res) . PHP_EOL, FILE_APPEND);

    }

//    protected function sendSMS()
//    {
//        $url = 'https://sms.ru/sms/send?';
//        $param = [
//            "api_id" => "2FD67E75-596E-A748-8AA2-BF50047A7A26",
//            "to" => $this->phone,
//            "msg" => $this->code . '- Код для смены пароля',
//            "json" => 1,
////            "test" => 1
//        ];
//
//        $res = file_get_contents($url . http_build_query($param));
//
//        $res = json_decode($res, true);
//
//        if($res["status"] === 'OK') $this->setResult('ok','', $this->userId);
//
//    }

    protected function genCode()
    { // генерация проверочного кода на основе последний 6 цифр времени unix
        $arr = str_split(substr(time(), -6), 1);
        shuffle($arr);
        $code = implode('', $arr);
        $this->code = $code;
    }

    protected function writeCodeUserInfo()
    { // сохраняем код в информации о пользователе
        if ($this->userId) {
            $user = new CUser;
            $fields = array(
                "UF_SMSCODE" => $this->code,
            );

            file_put_contents('../_logs/code.log', json_encode($this->code) . PHP_EOL, FILE_APPEND);
            $res = $user->Update($this->userId, $fields);
        }
    }

    protected function setResult($status, $mes, $id = '')
    {
        $this->arResult['status'] = $status;
        $this->arResult['mes'] = $mes;
        if ($id) $this->arResult['id'] = $id;
    }

    public function result()
    {
        return $this->arResult;
    }
}
