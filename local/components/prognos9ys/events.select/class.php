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
        $this->eTypeIb = \CIBlock::GetList([], ['CODE' => 'eventtype'], false)->Fetch()['ID'] ?: 19;

//        $this->getUserInfo();

    }

    public function executeComponent()
    {
        $arFilter["IBLOCK_ID"] = $this->eventsIb;
        $response = CIBlockElement::GetList(
            ["DATE_ACTIVE_FROM" => "ASC"],
            $arFilter,
            false,
            [
//                "nTopCount" => 6
            ],
            [
                "ID",
                "ACTIVE",
                "NAME",
                "PREVIEW_PICTURE",
                "DATE_ACTIVE_FROM",
                "PROPERTY_e_type",

            ]
        );

        while ($res = $response->GetNext()) {

            $res["img"] = CFile::GetPath($res["PREVIEW_PICTURE"]);

            $res["link"] = $this->getEventLink($res["PROPERTY_E_TYPE_VALUE"]);

//            $res["e_active"] = '';
//            if($res["ID"] === $this->actEvent) $res["e_active"] = 'e_active';

            $res["user"] = $this->userId;
            if($res["ACTIVE"] === 'Y') $this->arResult["events"][$res["ID"]] = $res;

        }

        $this->includeComponentTemplate();
    }

    protected function getEventLink($id){

        $response = \Bitrix\Iblock\ElementTable::getList(
            [
                'select' => ['CODE'],
                'filter' => [
                    "IBLOCK_ID" => $this->eTypeIb,
                    "=ID" => $id
                ]
            ]
        )->fetch();

        return $response["CODE"];
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
