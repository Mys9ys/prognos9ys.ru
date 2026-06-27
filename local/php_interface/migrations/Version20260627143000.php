<?php

namespace Sprint\Migration;

class Version20260627143000 extends Version
{
    protected $description = 'Футбол: метки слотов плей-офф (bracket_code, home_label, guest_label)';

    protected $moduleVersion = '4.1.1';

    public function up()
    {
        $helper = $this->getHelperManager();
        $iblockId = (int)$helper->Iblock()->getIblockIdIfExists('matches', 'content');
        if ($iblockId <= 0) {
            $this->outError('Инфоблок matches не найден');

            return false;
        }

        foreach ([
            ['CODE' => 'bracket_code', 'NAME' => 'Код матча в сетке'],
            ['CODE' => 'home_label', 'NAME' => 'Слот хозяев (если команда неизвестна)'],
            ['CODE' => 'guest_label', 'NAME' => 'Слот гостей (если команда неизвестна)'],
        ] as $property) {
            $helper->Iblock()->saveProperty($iblockId, [
                'NAME' => $property['NAME'],
                'ACTIVE' => 'Y',
                'SORT' => '520',
                'CODE' => $property['CODE'],
                'PROPERTY_TYPE' => 'S',
                'ROW_COUNT' => '1',
                'COL_COUNT' => '30',
                'LIST_TYPE' => 'L',
                'MULTIPLE' => 'N',
                'SEARCHABLE' => 'N',
                'FILTRABLE' => 'Y',
                'IS_REQUIRED' => 'N',
                'VERSION' => '1',
            ]);
        }

        return true;
    }

    public function down()
    {
        $helper = $this->getHelperManager();
        $iblockId = (int)$helper->Iblock()->getIblockIdIfExists('matches', 'content');
        if ($iblockId <= 0) {
            return true;
        }

        foreach (['bracket_code', 'home_label', 'guest_label'] as $code) {
            $helper->Iblock()->deletePropertyIfExists($iblockId, $code);
        }

        return true;
    }
}
