<?php



use Bitrix\Main\{Loader, UserTable};

class HeaderBlock extends CBitrixComponent {

    protected $user_id;
    protected $matchesIb;

    public function __construct($component = null)
    {
        parent::__construct($component);

        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        };

        $this->matchesIb = \CIBlock::GetList([], ['CODE' => 'matches'], false)->Fetch()['ID'] ?: 2;
        $this->user_id = CUser::GetID()?: '';
    }

    public function executeComponent()
    {
        if($this->user_id) {

            $user =[];
            $dbUser = UserTable::getList(array(
                'select' => array('ID', 'NAME', 'PERSONAL_PHOTO'),
                'filter' => array('ID' => $this->user_id)
            ));
            if ($arUser = $dbUser->fetch()){
                $user['img'] = $this->imageFormat($arUser['PERSONAL_PHOTO'], 60, 60, 85);
                $user['name'] = $arUser['NAME'];
                $user['id'] = $arUser['ID'];
                $this->arResult = $user;
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
            $arFileTmp["src"] =  '/local/components/prognos9ys/header.block/templates/.default/assets/img/ava.jpg';
        }

        return $arFileTmp["src"];
    }

    protected function getActualMatchId(){

        $arFilter["IBLOCK_ID"] = $this->matchesIb;

        $response = CIBlockElement::GetList(
            [],
            $arFilter,
            false,
            [],
            [
                "ID",
                "ACTIVE",
                "DATE_ACTIVE_FROM",
                "PROPERTY_home",
                "PROPERTY_guest",
                "PROPERTY_group",
                "PROPERTY_stage",
                "PROPERTY_number",
            ]
        );

        $res = $response->GetNext();
    }

}
