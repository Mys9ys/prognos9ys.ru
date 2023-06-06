<?php

use Bitrix\Main\Loader;

class DeactivateEventElement
{

    public function __construct()
    {
        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        }
    }

    public function inActiveElement($iblock_id)
    {
        $now = date(\CDatabase::DateFormatToPHP("DD.MM.YYYY HH:MI:SS"), time());
        $arFilter["IBLOCK_ID"] = $iblock_id;
        $arFilter["<=DATE_ACTIVE_FROM"] = $now;
        $arFilter["ACTIVE"] = 'Y';

        $response = CIBlockElement::GetList(
            [],
            $arFilter,
            false,
            [],
            [
                "ID",
            ]
        );

        while ($res = $response->GetNext()) {
            $obEl = new CIBlockElement();
            // Деактивация элемента
            $boolResult = $obEl->Update($res['ID'], array('ACTIVE' => 'N'));
        }

    }

}