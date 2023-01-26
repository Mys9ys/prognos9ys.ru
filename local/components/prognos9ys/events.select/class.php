<?php

use Bitrix\Main\{Loader, UserTable};

class EventSelect extends CBitrixComponent
{

    protected $eventsIb;

    protected $userId;

    protected $actEvent = '';

    public function __construct($component = null)
    {
        parent::__construct($component);
        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        }

        $this->eventsIb = \CIBlock::GetList([], ['CODE' => 'events'], false)->Fetch()['ID'] ?: 1;

        $this->getUserInfo();

        var_dump($this->arEvents);

    }

    public function executeComponent()
    {

        $response = \Bitrix\Iblock\ElementTable::getList(
            [
                'select' => ['ID', 'PREVIEW_TEXT', 'PREVIEW_PICTURE', 'DETAIL_TEXT'],
                'filter' => [
                    "IBLOCK_ID" => $this->eventsIb,
                ]
            ]
        );

        while ($res = $response->fetch()) {
            $res["img"] = CFile::GetPath($res["PREVIEW_PICTURE"]);

            $res["e_active"] = '';
            if($res["ID"] === $this->actEvent) $res["e_active"] = 'e_active';

            $res["user"] = $this->userId;
            $this->arResult["events"][$res["ID"]] = $res;
        }

        $this->includeComponentTemplate();
    }

    protected function getUserInfo()
    {
        $uid = CUser::GetID();

        if ($uid) {
            $dbUser = UserTable::getList(array(
                'select' => array('ID', 'UF_EVENT'),
                'filter' => array('=ID' => $uid)
            ))->fetch();
            $this->userId = $dbUser["ID"];
            $this->actEvent = $dbUser["UF_EVENT"];
        }

    }

}
