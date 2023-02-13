<?php

use Bitrix\Main\{Loader, UserTable};

class HeaderBlock extends CBitrixComponent {

    protected $user_id;
    protected $matchesIb;
    protected $eventTypeIb;

    public function __construct($component = null)
    {
        parent::__construct($component);

        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        };

//        $this->matchesIb = \CIBlock::GetList([], ['CODE' => 'matches'], false)->Fetch()['ID'] ?: 2;
        $this->eventTypeIb = \CIBlock::GetList([], ['CODE' => 'eventtype'], false)->Fetch()['ID'] ?: 19;
        $this->user_id = CUser::GetID()?: '';

    }

    public function executeComponent()
    {
        if($this->user_id) {

            $dbUser = UserTable::getList(array(
                'select' => array('ID', 'NAME', 'PERSONAL_PHOTO', 'UF_EVENT'),
                'filter' => array('ID' => $this->user_id)
            ));
            if ($arUser = $dbUser->fetch()){
                $this->arResult['img'] = $this->imageFormat($arUser['PERSONAL_PHOTO'], 60, 60, 85);
                $this->arResult['name'] = $arUser['NAME'];
                $this->arResult['id'] = $arUser['ID'];
                $this->arResult['event'] = $arUser['UF_EVENT'];
            }

//            $this->getActualMatchId();
            $this->getEventsInfo();
        }

        $this->includeComponentTemplate();
    }

    protected function imageFormat($id, $width, $height, $jpgQuality)
    {
        if($id){
            $arFileTmp = CFile::ResizeImageGet(
                $id,
                array("width" => $width, "height" => $height),
                BX_RESIZE_IMAGE_PROPORTIONAL,
                true,
                array(
                    "name" => "sharpen",
                    "precision" => 15
                ),
                false,
                $jpgQuality
            );
        } else {
            $arFileTmp["src"] =  '/local/components/prognos9ys/header.block/templates/.default/assets/img/ava.jpg';
        }

        return $arFileTmp["src"];
    }

    protected function getEventsInfo(){

        $arFilter["IBLOCK_ID"] = $this->eventTypeIb;

        $response = \Bitrix\Iblock\ElementTable::getList(
            [
                'select' => ['ID', 'NAME', 'PREVIEW_PICTURE', 'CODE'],
                'filter' => $arFilter
            ]
        );

        while ($res = $response->fetch()) {
            $res["img"] = CFile::GetPath($res["PREVIEW_PICTURE"]);
            $this->arResult['events'][] = $res;
        }

    }

    protected function getActualMatchId(){

        $now = new DateTime();
        $arFilter[">DATE_ACTIVE_FROM"] = $now->format('d.m.Y H:i:s');
        $arFilter["IBLOCK_ID"] = $this->matchesIb;

        $response = CIBlockElement::GetList(
            [],
            $arFilter,
            false,
            [],
            [
                "PROPERTY_NUMBER",
            ]
        );

         $res= $response->GetNext();

         $this->arResult['pr_link'] = $res["PROPERTY_NUMBER_VALUE"];
    }

}
