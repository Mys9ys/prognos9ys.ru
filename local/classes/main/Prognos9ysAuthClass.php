<?php

use Bitrix\Main\UserTable;

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

        if ($this->data['type'] === 'newLogin') {
            $this->newLogin();
        }
        if ($this->data['type'] === 'tokenLogin' || $this->data['type'] === 'ava') {
            $this->tokenLogin();
        }
    }

    protected function newLogin()
    {
        $this->getUserLogin();

        if (!$this->data['login']) {
            $this->setResult('error', 'Пользователь не найден');
        } else {
            $this->setAuthorization();

            if ($this->arAuthResult['MESSAGE']) {
                $this->setResult('error', trim($this->arAuthResult['MESSAGE'], '<br>'));
            } else {
                $this->getToken();
                if (!$this->data['token']) {
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
            $this->loadUserInfo(false);
            $this->setResult('ok', '', $this->userInfo);
        }
    }

    protected function getUserLogin()
    {
        $dbUser = UserTable::getList([
            'select' => ['LOGIN', 'ID'],
            'filter' => ['=EMAIL' => $this->data['mail']],
        ])->fetch();
        $this->data['login'] = $dbUser['LOGIN'];
        $this->userId = $dbUser['ID'];
    }

    protected function setAuthorization()
    {
        $USER = new CUser();
        $this->arAuthResult = $USER->Login(
            $this->data['login'],
            $this->data['pass'],
            'Y'
        );
    }

    public function checkToken()
    {
        $dbUser = UserTable::getList([
            'select' => ['LOGIN', 'ID'],
            'filter' => ['=UF_TOKEN' => $this->data['token']],
        ])->fetch();

        $this->data['login'] = $dbUser['LOGIN'] ?? null;
        $this->userId = $dbUser['ID'] ?? null;
    }

    protected function getUserIdForToken($token)
    {
        $dbUser = UserTable::getList([
            'select' => ['ID'],
            'filter' => ['=UF_TOKEN' => $token],
        ])->fetch();

        $this->userId = $dbUser['ID'];
    }

    public function setAvaImg($arr, $token)
    {
        $this->getUserIdForToken($token);

        $user = new CUser();
        $fields = [
            'PERSONAL_PHOTO' => $arr,
        ];

        $user->Update($this->userId, $fields);
    }

    protected function loadUserInfo(bool $includeGameInfo = true)
    {
        $dbUser = UserTable::getList([
            'select' => ['ID', 'EMAIL', 'PERSONAL_PHONE', 'UF_TOKEN', 'NAME', 'LAST_NAME', 'PERSONAL_PHOTO'],
            'filter' => ['=LOGIN' => $this->data['login']],
        ])->fetch();

        $dbUser['ava'] = CFile::GetPath($dbUser['PERSONAL_PHOTO']);
        unset($dbUser['PERSONAL_PHOTO']);

        $token = $dbUser['UF_TOKEN'] ?: ($this->data['token'] ?? '');
        $role = $token ? (new GetUserRole($token))->result() : 'user';
        $dbUser['role'] = $role;
        $dbUser['can_impersonate'] = $this->canUserImpersonate((int)$dbUser['ID'])
            || in_array($role, ['admin', 'super_moder'], true);

        if ($includeGameInfo && \Bitrix\Main\Loader::includeModule('prognos9ys.main')) {
            $dbUser['game_info'] = (new \Prognos9ys\Main\Service\Game\GameProfileService())
                ->getSummary((int)$dbUser['ID']);
        }

        $this->userInfo = $dbUser;
    }

    protected function setToken()
    {
        $user = new CUser();
        $fields = [
            'UF_TOKEN' => $this->data['token'],
        ];

        return $user->Update($this->userId, $fields);
    }

    protected function getToken()
    {
        $dbUser = UserTable::getList([
            'select' => ['UF_TOKEN'],
            'filter' => ['=LOGIN' => $this->data['login']],
        ])->fetch();
        $this->data['token'] = $dbUser['UF_TOKEN'];
    }

    protected function genToken()
    {
        $token = random_bytes(16);
        $this->data['token'] = implode('-', str_split(bin2hex($token), 4));
    }

    protected function canUserImpersonate(int $userId): bool
    {
        if (!\Bitrix\Main\Loader::includeModule('prognos9ys.main')) {
            return false;
        }

        return (new \Prognos9ys\Main\Service\Auth\ImpersonationService())->canImpersonate($userId);
    }

    protected function setResult($status, $mes, $info = '')
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
