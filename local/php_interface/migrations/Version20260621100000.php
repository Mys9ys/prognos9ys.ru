<?php

namespace Sprint\Migration;

class Version20260621100000 extends Version
{
    protected $description = 'CS2: инфоблок cs2teams (команды)';

    protected $moduleVersion = '4.1.1';

    public function up()
    {
        $helper = $this->getHelperManager();

        $iblockId = $helper->Iblock()->getIblockIdIfExists('cs2teams', 'content');
        if (!$iblockId) {
            $iblockId = (int)$helper->Iblock()->saveIblock([
                'IBLOCK_TYPE_ID' => 'content',
                'LID' => ['s1'],
                'CODE' => 'cs2teams',
                'API_CODE' => 'cs2teams',
                'NAME' => 'CS2 — команды',
                'ACTIVE' => 'Y',
                'SORT' => '500',
                'VERSION' => '1',
                'INDEX_ELEMENT' => 'Y',
            ]);
        }

        if ($iblockId <= 0) {
            $this->outError('Не удалось создать инфоблок cs2teams');

            return false;
        }

        $helper->Iblock()->saveProperty($iblockId, [
            'NAME' => 'Короткий тег',
            'CODE' => 'short_tag',
            'PROPERTY_TYPE' => 'S',
        ]);

        $helper->Iblock()->saveProperty($iblockId, [
            'NAME' => 'HLTV slug',
            'CODE' => 'hltv_slug',
            'PROPERTY_TYPE' => 'S',
        ]);

        $helper->Iblock()->saveProperty($iblockId, [
            'NAME' => 'Регион',
            'CODE' => 'region',
            'PROPERTY_TYPE' => 'S',
        ]);

        return true;
    }

    public function down()
    {
        $helper = $this->getHelperManager();
        $id = $helper->Iblock()->getIblockIdIfExists('cs2teams', 'content');
        if ($id) {
            $helper->Iblock()->deleteIblockIfExists($id);
        }

        return true;
    }
}
