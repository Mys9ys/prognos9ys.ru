<?php

namespace Prognos9ys\Main\Service\Cs2;

class Cs2MatchService
{
    public function getMatch(string $eventId, string $number, ?string $userToken = null): array
    {
        $handler = new \Cs2MatchLoadInfo([
            'eventId' => $eventId,
            'number' => $number,
            'userToken' => $userToken ?? '',
        ]);

        return $handler->result();
    }
}
