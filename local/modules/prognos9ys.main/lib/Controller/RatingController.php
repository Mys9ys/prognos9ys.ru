<?php

namespace Prognos9ys\Main\Controller;

use Prognos9ys\Main\Service\Rating\RaceRatingService;
use Prognos9ys\Main\Service\Football\FootballRatingService;

class RatingController extends BaseController
{
    public function configureActions(): array
    {
        return [
            'getFootballRatings' => $this->getDefaultConfigureForPostPublic(),
            'getRaceRatings' => $this->getDefaultConfigureForPostPublic(),
        ];
    }

    public function getFootballRatingsAction($event): array
    {
        return (new FootballRatingService())->getByEvent($event);
    }

    public function getRaceRatingsAction(string $events): array
    {
        return (new RaceRatingService())->getByEvent($events);
    }
}
