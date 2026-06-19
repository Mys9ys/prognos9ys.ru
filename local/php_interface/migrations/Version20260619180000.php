<?php

namespace Sprint\Migration;

class Version20260619180000 extends Version
{
    protected $description = 'CS2: свойство bo_format (Bo1/Bo3/Bo5) в инфоблоке matches';

    protected $moduleVersion = '4.1.1';

    public function up()
    {
        $helper = $this->getHelperManager();
        $iblockId = $helper->Iblock()->getIblockIdIfExists('matches', 'content');

        if (!$iblockId) {
            $this->outError('Iblock matches not found');
            return false;
        }

        $helper->Iblock()->saveProperty($iblockId, [
            'NAME' => 'Формат серии CS',
            'ACTIVE' => 'Y',
            'SORT' => '500',
            'CODE' => 'bo_format',
            'DEFAULT_VALUE' => '',
            'PROPERTY_TYPE' => 'L',
            'ROW_COUNT' => '1',
            'COL_COUNT' => '30',
            'LIST_TYPE' => 'L',
            'MULTIPLE' => 'N',
            'IS_REQUIRED' => 'N',
            'VALUES' => [
                ['VALUE' => 'Bo1', 'DEF' => 'N', 'SORT' => '100', 'XML_ID' => 'bo1'],
                ['VALUE' => 'Bo3', 'DEF' => 'Y', 'SORT' => '200', 'XML_ID' => 'bo3'],
                ['VALUE' => 'Bo5', 'DEF' => 'N', 'SORT' => '300', 'XML_ID' => 'bo5'],
            ],
        ]);

        $helper->Iblock()->saveProperty($iblockId, [
            'NAME' => 'Счёт по картам (JSON)',
            'ACTIVE' => 'Y',
            'SORT' => '510',
            'CODE' => 'map_scores',
            'DEFAULT_VALUE' => '',
            'PROPERTY_TYPE' => 'S',
            'ROW_COUNT' => '5',
            'COL_COUNT' => '80',
            'MULTIPLE' => 'N',
            'IS_REQUIRED' => 'N',
        ]);

        return true;
    }

    public function down()
    {
        $helper = $this->getHelperManager();
        $iblockId = $helper->Iblock()->getIblockIdIfExists('matches', 'content');

        if ($iblockId) {
            $helper->Iblock()->deletePropertyIfExists($iblockId, 'bo_format');
            $helper->Iblock()->deletePropertyIfExists($iblockId, 'map_scores');
        }

        return true;
    }
}
