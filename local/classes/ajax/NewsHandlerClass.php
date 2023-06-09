<?php

use Bitrix\Main\Loader;

class NewsHandlerClass
{

    protected $arIbs = [
        'news' => ['code' => 'news', 'id' => 18],
    ];
    protected $arResult;
    public function __construct()
    {

        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        }

        $this->getLastNews();
    }

    protected function getLastNews(){
        $arFilter = [
            'IBLOCK_ID' => $this->arIbs['news']['id'],
        ];

        $res = CIBlockElement::GetList(
            ["DATE_ACTIVE_FROM" => "DESC"],
            $arFilter,
            false,
            ["nTopCount" => 1],
            [
                "ID",
                "NAME",
                "PREVIEW_TEXT",
                "PROPERTY_seen",
                "PROPERTY_likes",
                "PROPERTY_tags",
            ]
        )->GetNext();

        $this->setResult('ok', '', $res);

    }

    protected function setResult($status, $mes, $info = '')
    {
        $this->arResult['status'] = $status;
        $this->arResult['mes'] = $mes;
        $this->arResult['info'] =  $info;
    }

    public function result(){
        return $this->arResult;
    }
}