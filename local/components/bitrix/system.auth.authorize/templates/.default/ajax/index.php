<?php use Bitrix\Main\Loader;

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

//file_put_contents('debug_request.json',json_encode($_REQUEST));

//$_REQUEST = json_decode(file_get_contents('debug_request.json'), true);


if ($_REQUEST['type'] === 'auth') {

    $res = new myAuthUser($_REQUEST);

    echo json_encode($res->getResult());
}

class myAuthUser
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
            $this->authUser();
        } else {
            $this->result = ["status" => "err", "mes" => "неверный e-mail или пароль"];
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

    protected function authUser()
    {
        $user = new CUser;
        $res = $user->Login($this->info["login"], $this->info["password"], "Y");

        if($res === true){
            $this->result = ["status" => "ok", "mes" => "Авторизация успешна"];
//            $user->Authorize($res);
        } else {
            if($res["MESSAGE"]){
                $this->result = ["status" => "err", "mes" => $res["MESSAGE"]];
            }
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
