<?php

namespace Prognos9ys\Main\Service\Football;

use Prognos9ys\Main\Service\Rating\FootballRatingCalculator;

class FootballRatingService
{
    public function getByEvent($eventId): array
    {
        return (new FootballRatingCalculator())->calculate((int)$eventId);
    }
}
