<?php

namespace Sprint\Migration;

require_once __DIR__ . '/Cs2MigrationIblock.php';

/**
 * Флаг «участвовать в ставке» на элементе прогноза (для отличия ботов/legacy от явного отказа).
 */
class Version20260624120000 extends Version
{
    protected $description = 'Прогноз: свойство bet_enabled (ставка на исход)';

    protected $moduleVersion = '4.1.1';

    public function up()
    {
        $helper = $this->getHelperManager();

        foreach (['prognosis' => 'Футбол — прогнозы', 'prognoscs2' => 'CS2 — прогнозы'] as $code => $label) {
            $iblockId = (int)(\CIBlock::GetList([], ['CODE' => $code], false)->Fetch()['ID'] ?? 0);
            if ($iblockId <= 0) {
                $iblockId = Cs2MigrationIblock::findId($code);
            }
            if ($iblockId <= 0) {
                $this->outWarning("Инфоблок {$code} не найден — пропуск");

                continue;
            }

            $helper->Iblock()->saveProperty($iblockId, [
                'NAME' => 'Ставка на исход',
                'CODE' => 'bet_enabled',
                'PROPERTY_TYPE' => 'S',
                'ROW_COUNT' => '1',
                'COL_COUNT' => '5',
                'HINT' => 'Y — ставка, N — отказ. Пусто — legacy/бот (backfill при расчёте матча).',
            ]);

            $this->out("bet_enabled → {$label} (ID {$iblockId})");
        }

        return true;
    }

    public function down()
    {
        return true;
    }
}
