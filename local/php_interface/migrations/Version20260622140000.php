<?php

namespace Sprint\Migration;

use Prognos9ys\Main\Service\Game\GameEconomyHlInstaller;

/**
 * Казна: UF_RUBLIUS в game_bank, лавка treasury_shop_wave, гос. вклад (UF_CONTRACT_TYPE).
 */
class Version20260622140000 extends Version
{
    protected $description = 'Game economy: treasury, shop waves, gov support deposit fields';

    protected $moduleVersion = '4.1.1';

    public function up()
    {
        $result = (new GameEconomyHlInstaller())->upgradeTreasuryFeatures();

        $this->out('Treasury upgrade:');
        foreach ($result as $key => $value) {
            $this->out('  ' . $key . ': ' . $value);
        }

        return true;
    }

    public function down()
    {
        $this->outWarning('Down not implemented for HL treasury fields');

        return true;
    }
}
