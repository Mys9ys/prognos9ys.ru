<?php

namespace Sprint\Migration;

require_once __DIR__ . '/Cs2MigrationIblock.php';

/**
 * CS2: инфоблок карт (cs2maps) + сид active duty pool.
 */
class Version20260622120000 extends Version
{
    protected $description = 'CS2: инфоблок cs2maps и карты active duty';

    protected $moduleVersion = '4.1.1';

    public function up()
    {
        $helper = $this->getHelperManager();

        $iblockId = Cs2MigrationIblock::findId('cs2maps');
        if ($iblockId <= 0) {
            $iblockId = (int)$helper->Iblock()->saveIblock([
                'IBLOCK_TYPE_ID' => 'content',
                'LID' => ['s1'],
                'CODE' => 'cs2maps',
                'API_CODE' => 'cs2maps',
                'NAME' => 'CS2 — карты',
                'ACTIVE' => 'Y',
                'SORT' => '500',
                'VERSION' => '1',
                'INDEX_ELEMENT' => 'Y',
            ]);
        }

        if ($iblockId <= 0) {
            $this->outError('Не удалось создать инфоблок cs2maps');

            return false;
        }

        $helper->Iblock()->saveProperty($iblockId, [
            'NAME' => 'В активном пуле',
            'CODE' => 'in_pool',
            'PROPERTY_TYPE' => 'L',
            'LIST_TYPE' => 'C',
            'VALUES' => [
                ['VALUE' => 'Да', 'XML_ID' => 'Y', 'DEF' => 'Y'],
            ],
        ]);

        foreach ($this->mapDefinitions() as $map) {
            $existing = (int)$helper->Iblock()->getElementId($iblockId, ['=CODE' => $map['code']]);
            if ($existing > 0) {
                continue;
            }

            $helper->Iblock()->addElement($iblockId, [
                'NAME' => $map['name'],
                'CODE' => $map['code'],
                'XML_ID' => 'cs2map_' . $map['code'],
                'ACTIVE' => 'Y',
                'SORT' => $map['sort'],
                'PREVIEW_TEXT' => $map['description'],
                'PREVIEW_TEXT_TYPE' => 'text',
            ], [
                'in_pool' => 'Y',
            ]);
        }

        $this->outSuccess('cs2maps: ' . count($this->mapDefinitions()) . ' карт');

        return true;
    }

    public function down()
    {
        $helper = $this->getHelperManager();
        $iblockId = Cs2MigrationIblock::findId('cs2maps');
        if ($iblockId > 0) {
            $helper->Iblock()->deleteIblockIfExists($iblockId);
        }

        return true;
    }

    /** @return list<array{code:string,name:string,description:string,sort:int}> */
    private function mapDefinitions(): array
    {
        return [
            ['code' => 'ancient', 'name' => 'Ancient', 'description' => 'Руины в джунглях Перу', 'sort' => 100],
            ['code' => 'anubis', 'name' => 'Anubis', 'description' => 'Египетский храм у Нила', 'sort' => 110],
            ['code' => 'dust2', 'name' => 'Dust II', 'description' => 'Классика пустыни', 'sort' => 120],
            ['code' => 'inferno', 'name' => 'Inferno', 'description' => 'Итальянская деревня', 'sort' => 130],
            ['code' => 'mirage', 'name' => 'Mirage', 'description' => 'Ближний Восток', 'sort' => 140],
            ['code' => 'nuke', 'name' => 'Nuke', 'description' => 'Атомная станция', 'sort' => 150],
            ['code' => 'overpass', 'name' => 'Overpass', 'description' => 'Городской мост и парк', 'sort' => 160],
        ];
    }
}
