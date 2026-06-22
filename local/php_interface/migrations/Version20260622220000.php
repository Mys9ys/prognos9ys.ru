<?php

namespace Sprint\Migration;

use Prognos9ys\Main\Service\Game\GameEconomyHlInstaller;

/**
 * Реестр матчей с внесённым результатом и прогнанным пересчётом (тур экономики).
 */
class Version20260622220000 extends Version
{
    protected $description = 'Game economy: match settlement registry for tour/deposits/shop';

    protected $moduleVersion = '4.1.1';

    public function up()
    {
        $result = (new GameEconomyHlInstaller())->upgradeMatchEconomySettlement();

        $this->out('Match economy settlement HL:');
        foreach ($result as $key => $value) {
            $this->out('  ' . $key . ': ' . $value);
        }

        return true;
    }

    public function down()
    {
        $this->outWarning('Down not implemented for match economy settlement HL');

        return true;
    }
}
