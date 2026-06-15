<?php

namespace Prognos9ys\Main\Service\Football;

class FootballEventListService
{
    public function getByEvent(string $events, ?string $userToken = null): array
    {
        $handler = new \FootballHandlerClass([
            'events' => $events,
            'userToken' => $userToken ?? '',
        ]);

        return $handler->result();
    }
}
