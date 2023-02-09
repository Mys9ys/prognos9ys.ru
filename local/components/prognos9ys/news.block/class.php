<?php

use Bitrix\Main\{Loader, UserTable};

class NewsBlock extends CBitrixComponent
{

    protected $newsIb;

    protected $userId;

    protected $actEvent = '';

    public function __construct($component = null)
    {
        parent::__construct($component);
        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        }

        $this->newsIb = \CIBlock::GetList([], ['CODE' => 'news'], false)->Fetch()['ID'] ?: 18;

    }

    public function executeComponent()
    {

        $arFilter["IBLOCK_ID"] = $this->newsIb;

        $response = CIBlockElement::GetList(
            ["DATE_ACTIVE_FROM" => "DESC"],
            $arFilter,
            false,
            [
//                "nTopCount" => 6
            ],
            [
                "ID",
                "ACTIVE",
                "DATE_ACTIVE_FROM",
                'PREVIEW_TEXT',
                'PREVIEW_PICTURE',
                'DETAIL_PICTURE',
                'DETAIL_TEXT',
                "PROPERTY_link",
                "PROPERTY_bg_color",
                "PROPERTY_btn",
            ]
        );

        while ($res = $response->GetNext()) {
            $el = [];
            $el["img"] = $res["DETAIL_PICTURE"] ? CFile::GetPath($res["DETAIL_PICTURE"]) : '';
            $el["link"] = $res["PROPERTY_LINK_VALUE"];
            $el["bg_color"] = $res["PROPERTY_BG_COLOR_VALUE"];
            $el["btn"] = $res["~PROPERTY_BTN_VALUE"];
            $el["title"] = $res["PREVIEW_TEXT"];
            $el["small_title"] = $res["~DETAIL_TEXT"];
            $el["bcgrnd"] = $res["PREVIEW_PICTURE"] ? CFile::GetPath($res["PREVIEW_PICTURE"]) : '';

            if($res['ACTIVE'] === 'Y') {
                $this->arResult['active'][] = $el;
            } else {
                $this->arResult['old'][] = $el;
            }
        }

        $this->includeComponentTemplate();
    }

}
