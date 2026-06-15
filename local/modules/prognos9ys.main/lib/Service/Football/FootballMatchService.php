<?php

namespace Prognos9ys\Main\Service\Football;

class FootballMatchService
{
    public function getMatch(string $eventId, string $number, ?string $userToken = null): array
    {
        $handler = new \FootballMatchLoadInfo([
            'eventId' => $eventId,
            'number' => $number,
            'userToken' => $userToken ?? '',
        ]);

        return $handler->result();
    }
}
