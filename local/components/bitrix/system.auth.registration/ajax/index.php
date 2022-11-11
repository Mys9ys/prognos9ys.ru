<?php use Bitrix\Main\Loader;

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

//file_put_contents('debug_request.json',json_encode($_REQUEST));

//$_REQUEST = json_decode(file_get_contents('debug_request.json'), true);

if ($_REQUEST['type'] === 'check_mail') {
    $res = new myRegisterNewUser($_REQUEST);

    echo $res->getResult();
}

if ($_REQUEST['type'] === 'reg') {

    $res = new myRegisterNewUser($_REQUEST);

    echo $res->getResult();
}

class myRegisterNewUser
{
    protected $info = [];
    protected $result;

    public function __construct($arr)
    {

        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        }

        $this->info = $arr;

        $check = $this->findUserProfile();

        if($check) {
            $this->result = ["status" => "err", "mes" => "такой пользователь уже существует"];
        } else {
            if($this->info["type"] === "reg") $this->setUserInfo();
        }

    }

    protected function findUserProfile()
    {

        $row = Bitrix\Main\UserTable::getList([
            "select" => ["ID"],
            "filter" => ["?EMAIL" => $this->info["login"]],
        ])->fetch();

        return $this->info["company"] ?: false;
    }

    protected function setUserInfo()
    {
        $user = new CUser;
        $arFields = Array(
            "NAME"              => $this->info["USER_NAME"],
            "EMAIL"             => $this->info["USER_EMAIL"],
            "LOGIN"             => $this->info["USER_EMAIL"],
            "PASSWORD"          => $this->info["USER_PASSWORD"],
            "CONFIRM_PASSWORD"  => $this->info["USER_PASSWORD"],
        );

        $res = $user->Add($arFields);

        if($res) {
            $this->result = ["status" => "ok", "mes" => "регистрация успешна"];
        } else {
            $this->result = ["status" => "err", "mes" => "Ошибка регистрации"];
        }

    }

    /**
     * @return mixed
     */
    public function getResult()
    {
        return $this->result;
    }
}
