<?php use Bitrix\Main\Loader;

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

file_put_contents('debug_request.json',json_encode($_REQUEST));

//$_REQUEST = json_decode(file_get_contents('debug_request.json'), true);

if ($_REQUEST['type'] === 'check_mail') {
    $res = new myRegisterNewUser($_REQUEST);

    echo json_encode($res->getResult());
}

if ($_REQUEST['type'] === 'reg') {

    $res = new myRegisterNewUser($_REQUEST);

    echo json_encode($res->getResult());
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
            $this->result = ["status" => "err", "mes" => "уже есть пользователь с таким e-mail"];
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

        return $row["ID"] ?: false;
    }

    protected function setUserInfo()
    {
        $user = new CUser;
        $arFields = Array(
            "NAME"              => $this->info["name"],
            "EMAIL"             => $this->info["login"],
            "LOGIN"             => $this->info["login"],
            "PASSWORD"          => $this->info["password"],
            "CONFIRM_PASSWORD"  => $this->info["password"],
            "PERSONAL_PAGER"    => substr(time(), 4) . rand(0,20),
            "WORK_PAGER"        => $this->info["ref"],
        );

        $res = $user->Add($arFields);

        if($res) {
            $this->result = ["status" => "ok", "mes" => "регистрация успешна"];
            $user->Authorize($res);
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
