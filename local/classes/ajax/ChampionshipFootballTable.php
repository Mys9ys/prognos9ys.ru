<?php

use Bitrix\Main\Loader;
use Prognos9ys\Main\Service\Championship\FootballTableService;

/**
 * Legacy-обёртка: API mob_app и старые вызовы.
 * Логика — в Prognos9ys\Main\Service\Championship\FootballTableService (ORM).
 */
class ChampionshipFootballTable extends PrognosisGiveInfo
{
    public function __construct($data)
    {
        Loader::includeModule('prognos9ys.main');

        $this->arGive = (new FootballTableService())->getTable(
            (string)($data['events'] ?? ''),
            isset($data['token']) ? (string)$data['token'] : null
        );
    }
}
