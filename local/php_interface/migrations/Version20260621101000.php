<?php

namespace Sprint\Migration;

require_once __DIR__ . '/Cs2MigrationIblock.php';

class Version20260621101000 extends Version
{
    protected $description = 'CS2: привязка home/guest в cs2matches к cs2teams';

    protected $moduleVersion = '4.1.1';

    public function up()
    {
        $helper = $this->getHelperManager();

        $matchesId = Cs2MigrationIblock::findId('cs2matches');
        $teamsId = Cs2MigrationIblock::findId('cs2teams');

        if ($matchesId <= 0) {
            $this->out('cs2matches ещё не создан — пропуск (запустите Version20260620120000)');

            return true;
        }

        if ($teamsId <= 0) {
            $this->outError('Инфоблок cs2teams не найден');

            return false;
        }

        foreach (['home' => 'Команда 1', 'guest' => 'Команда 2'] as $code => $name) {
            $helper->Iblock()->saveProperty($matchesId, [
                'NAME' => $name,
                'CODE' => $code,
                'PROPERTY_TYPE' => 'E',
                'LINK_IBLOCK_ID' => $teamsId,
            ]);
        }

        return true;
    }

    public function down()
    {
        return true;
    }
}
