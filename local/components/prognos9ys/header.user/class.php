<?php

use Bitrix\Main\{Loader, UserTable};

class Header_user extends CBitrixComponent {

    protected $user_id;
    public function __construct($component = null)
    {
        parent::__construct($component);

        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        };

        $this->user_id = CUser::GetID()?: '';
    }

    public function executeComponent()
    {
        if($this->user_id) {
            $dbUser = UserTable::getList(array(
                'select' => array('ID', 'NAME', 'PERSONAL_PHOTO', 'PERSONAL_WWW'),
                'filter' => array('ID' => $this->user_id)
            ));
            if ($arUser = $dbUser->fetch()){
                $arUser['img'] = $this->imageFormat($arUser['PERSONAL_PHOTO'], 60, 60, 85);
                $this->arResult = $arUser;
            }
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
            $arFileTmp["src"] =  "/local/components/prognos9ys/header.user/templates/.default/assets/img/no_photo.jpg";
        }

        return $arFileTmp["src"];
    }

}
