<?php

use Bitrix\Main\Loader;

class MainSlider extends CBitrixComponent
{
    protected $iBlockId = '';

    public function __construct($component = null)
    {
        parent::__construct($component);

        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        };

        if (!Loader::includeModule('highloadblock')) {
            ShowError('Модуль Highload блоков не установлен');
            return;
        };
        $this->iBlockId = \CIBlock::GetList([], ['TYPE' => 'content', 'CODE' => 'actions', 'SITE_ID' => SITE_ID], false)->Fetch()['ID'];

    }

    public function executeComponent()
    {
        if ($this->iBlockId) {
            $now = date(\CDatabase::DateFormatToPHP("DD.MM.YYYY HH:MI:SS"), time());

            $res = \CIBlockElement::GetList(['SORT' => 'ASC'],
                [
                    "IBLOCK_ID" => $this->iBlockId,
                    "ACTIVE" => "Y",
                    '!PROPERTY_SHOW_IN_PERSONAL_VALUE' => 'Да',
                    'PROPERTY_REGION' => ($GLOBALS['REGION_ID'] ?: 14),
                    "<=DATE_ACTIVE_FROM" => $now,
                    ">DATE_ACTIVE_TO" => $now
                ], false, false, [
                    'PREVIEW_PICTURE',
                    'DETAIL_PAGE_URL',
                    'NAME',
                    'PREVIEW_TEXT',
                    'DATE_ACTIVE_FROM',
                    'PROPERTY_SERVICE_PICTURE',
                    'PROPERTY_ACTION_TYPE',
                    'PROPERTY_ALT_LINK',
                ]);
            while ($response = $res->GetNext()) {
                $elem = [];
                $elem["title"] = strlen($response["NAME"])>31 ? iconv_substr($response["NAME"], 0, 31, "UTF-8") . '...' : $response["NAME"];
                $elem["text"] = strlen($response["PREVIEW_TEXT"])>111 ? iconv_substr($response["PREVIEW_TEXT"], 0, 111, "UTF-8") . '...' : $response["PREVIEW_TEXT"];

                $elem["mob_img"] = $this->imageFormatActionSlider($response["PROPERTY_SERVICE_PICTURE_VALUE"], 500, 191, 85);
                $elem["desc_img"] = $this->imageFormatActionSlider($response["PREVIEW_PICTURE"], 350, 222,  85);

                $elem["url"] = $response["PROPERTY_ALT_LINK_VALUE"] ? : $response["DETAIL_PAGE_URL"];

                if($response["PROPERTY_ACTION_TYPE_VALUE"] === 'Заглушка'){
                    $result['plug'][] = $elem;
                } else {
                    $result['items'][] = $elem;
                }
            }
        }

        $this->setSliderPlugs($result['items'], $result['plug']);

        $this->includeComponentTemplate();
    }

    protected function setSliderPlugs($items, $plugs){
        
        switch (count($items)){
            case 4:
                $this->arResult = array_merge($this->arResult, $items);;
                break;

            case 3:
                $this->arResult = array_merge($this->arResult, $items, $this->getCountPlug($plugs, 1));
                break;

            case 2:
                $this->arResult = array_merge($this->arResult, $items, $this->getCountPlug($plugs, 2));
                break;

            case 1:
                $this->arResult = array_merge($this->arResult, $items, $this->getCountPlug($plugs, 3));
                break;

            default:
                $this->arResult = array_merge($this->arResult, $items, $this->getCountPlug($plugs, 4));
        }
    }

    protected function imageFormatActionSlider($id, $width, $height, $jpgQuality)
    {

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
        return $arFileTmp["src"];
    }

    protected function getCountPlug($arr, $count){

        $res = [];

        for ($i = 0; $i<$count; $i++){
            if(count($arr)>0){
                $id = rand(0, count($arr)-1);
                $res[] = $arr[$id];
                unset($arr[$id]);
                $arr = array_values($arr);
            }
        }

        return $res;
    }
}