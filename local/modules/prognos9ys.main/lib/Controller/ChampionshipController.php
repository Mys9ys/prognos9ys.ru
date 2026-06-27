<?php

namespace Prognos9ys\Main\Controller;

use Prognos9ys\Main\Service\Championship\FootballTableService;

class ChampionshipController extends BaseController
{
    public function configureActions(): array
    {
        return [
            'getFootballTable' => $this->getDefaultConfigureForPostPublic(),
        ];
    }

    /**
     * Турнирная таблица чемпионата (ORM + сервис).
     */
    public function getFootballTableAction(string $events, ?string $token = null): array
    {
        return (new FootballTableService())->getTable($events, $token);
    }
}
