<?php

namespace Sprint\Migration;

require_once __DIR__ . '/Cs2MigrationIblock.php';

class Version20260621102000 extends Version
{
    protected $description = 'CS2: тип события cs2 в каталоге (eventtype)';

    protected $moduleVersion = '4.1.1';

    public function up()
    {
        $helper = $this->getHelperManager();
        $typeIblockId = Cs2MigrationIblock::findId('eventtype') ?: 19;

        if ($typeIblockId <= 0) {
            $this->outError('Инфоблок eventtype не найден');

            return false;
        }

        $existing = $helper->Iblock()->getElementId($typeIblockId, ['=CODE' => 'cs2']);
        if ($existing) {
            $this->out('Тип cs2 уже существует: #' . $existing);

            return true;
        }

        $id = $helper->Iblock()->addElement($typeIblockId, [
            'NAME' => 'CS2',
            'CODE' => 'cs2',
            'XML_ID' => 'eventtype_cs2',
            'ACTIVE' => 'Y',
            'SORT' => '450',
        ]);

        if (!$id) {
            $this->outError('Не удалось создать тип cs2');

            return false;
        }

        $this->outSuccess('Тип cs2 создан: #' . $id);

        return true;
    }

    public function down()
    {
        $helper = $this->getHelperManager();
        $typeIblockId = Cs2MigrationIblock::findId('eventtype') ?: 19;
        $id = $helper->Iblock()->getElementId($typeIblockId, ['=CODE' => 'cs2']);
        if ($id) {
            \CIBlockElement::Delete($id);
        }

        return true;
    }
}
