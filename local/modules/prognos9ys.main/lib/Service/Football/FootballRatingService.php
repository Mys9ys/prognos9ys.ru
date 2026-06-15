<?php

namespace Prognos9ys\Main\Service\Football;

class FootballRatingService
{
    public function getByEvent($eventId): array
    {
        $handler = new \CreateFootballRatings(['event' => $eventId]);
        $result = $handler->result();

        if (isset($result['result']) && !isset($result['ratings'])) {
            $result['ratings'] = $result['result'];
            unset($result['result']);
        }

        return $result;
    }
}
