<?php

namespace Prognos9ys\Main\Service\Rating;

class RaceRatingService
{
    public function getByEvent(string $events): array
    {
        $handler = new \RaceRatingsHandler(['events' => $events]);

        return $handler->result();
    }
}
