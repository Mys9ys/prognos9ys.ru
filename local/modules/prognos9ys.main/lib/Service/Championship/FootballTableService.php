<?php

namespace Prognos9ys\Main\Service\Championship;

class FootballTableService
{
    public function getTable(string $events, ?string $token = null): array
    {
        $handler = new \ChampionshipFootballTable([
            'events' => $events,
            'token' => $token ?? '',
        ]);

        return $handler->result();
    }
}
