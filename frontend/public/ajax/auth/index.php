<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept');

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\UserTable;

$_REQUEST['date'] = date(\CDatabase::DateFormatToPHP("DD.MM.YYYY HH:MI:SS"), time());

file_put_contents('../_logs/auth_data.log', json_encode($_REQUEST) . PHP_EOL, FILE_APPEND);

$arFile = '';

if ($_FILES['file']) {

    $fileId = CFile::SaveFile($_FILES['file'], '/main');

    $arFile = CFile::MakeFileArray($fileId);

    file_put_contents('../_logs/auth_file_id.log', json_encode(CFile::GetPath($fileId)) . PHP_EOL, FILE_APPEND);
}

if($_REQUEST){
    $auth = new Prognos9ysAuthClass($_REQUEST);

    $response = $auth->result();

    if ($response['status'] === 'ok' && $_FILES['file']) {

        $auth->setAvaImg($arFile, $_REQUEST['token']);
    }

    echo json_encode($response);
}


class Prognos9ysAuthClass
{

    protected $data;

    protected $userId;

    protected $arAuthResult;

    protected $userInfo = [];

    protected $arResult = [];

    public function __construct($data)
    {
        $this->data = $data;

        if ($this->data['type'] === 'newLogin') $this->newLogin();
        if ($this->data['type'] === 'tokenLogin' || $this->data['type'] === 'ava') $this->tokenLogin();
    }

    protected function newLogin()
    {
        $this->getUserLogin();

        if (!$this->data['login']) {
            $this->setResult('error', 'Пользователь не найден');
        } else {
            $this->setAuthorization();

            if ($this->arAuthResult["MESSAGE"]) {
                $this->setResult('error', trim($this->arAuthResult["MESSAGE"], '<br>'));
            } else {
                $this->getToken();
                if(!$this->data['token']) {
                    $this->genToken();
                    $this->setToken();
                }
                $this->loadUserInfo();
                $this->setResult('ok', '', $this->userInfo);
            }
        }
    }

    protected function tokenLogin()
    {
        $this->checkToken();
        if (!$this->data['login']) {
            $this->setResult('error', 'Токен не верный');
        } else {
            $this->loadUserInfo();
            $this->setResult('ok', '', $this->userInfo);
        }
    }

    protected function getUserLogin()
    {
        $dbUser = UserTable::getList(array(
            'select' => array('LOGIN', 'ID'),
            'filter' => array('=EMAIL' => $this->data['mail'])
        ))->fetch();
        $this->data['login'] = $dbUser["LOGIN"];
        $this->userId = $dbUser["ID"];
    }

    protected function setAuthorization()
    {
        $USER = new CUser;
        $this->arAuthResult = $USER->Login(
            $this->data['login'],
            $this->data['pass'],
            "Y"
        );
    }

    public function checkToken()
    {
        $dbUser = UserTable::getList(array(
            'select' => array('LOGIN'),
            'filter' => array('=UF_TOKEN' => $this->data['token'])
        ))->fetch();

        $this->data['login'] = $dbUser['LOGIN'];
    }

    protected function getUserIdForToken($token)
    {
        $dbUser = UserTable::getList(array(
            'select' => array('ID'),
            'filter' => array('=UF_TOKEN' => $token)
        ))->fetch();

        $this->userId = $dbUser['ID'];
    }

    public function setAvaImg($arr, $token)
    {
        $this->getUserIdForToken($token);

        $user = new CUser;
        $fields = array(
            "PERSONAL_PHOTO" => $arr,
        );

        $user->Update($this->userId, $fields);
    }

    protected function loadUserInfo()
    {
        $dbUser = UserTable::getList(array(
            'select' => array('ID', 'EMAIL', 'PERSONAL_PHONE', 'UF_TOKEN', 'NAME', 'LAST_NAME', 'PERSONAL_PHOTO'),
            'filter' => array('=LOGIN' => $this->data['login'])
        ))->fetch();

        $dbUser['ava'] = CFile::GetPath($dbUser['PERSONAL_PHOTO']);
        unset($dbUser['PERSONAL_PHOTO']);
        $this->userInfo = $dbUser;
    }

    protected function setToken()
    {
        $user = new CUser;
        $fields = array(
            "UF_TOKEN" => $this->data['token'],
        );
        return $user->Update($this->userId, $fields);
    }

    protected function getToken(){
        $dbUser = UserTable::getList(array(
            'select' => array('UF_TOKEN'),
            'filter' => array('=LOGIN' => $this->data['login'])
        ))->fetch();
        $this->data['token'] = $dbUser['UF_TOKEN'];
    }

    protected function genToken()
    {
        $token = random_bytes(16);
        $this->data['token'] = implode('-', str_split(bin2hex($token), 4));
    }

    protected function setResult($status,$mes, $info = '')
    {
        $this->arResult['status'] = $status;
        $this->arResult['mes'] = $mes;
        $this->arResult['info'] = $info;
    }

    public function result()
    {
        return $this->arResult;
    }

}
