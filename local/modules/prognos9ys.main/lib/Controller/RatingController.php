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

    public function getFootballRatingsAction(
        $event,
        ?int $setId = null,
        ?string $userToken = null,
        ?string $token = null,
        ?string $selector = null,
        ?int $limit = null
    ): array {
        $service = new FootballRatingService();
        $viewerUserId = $service->resolveViewerUserId($userToken ?: $token);

        return $service->getByEvent(
            $event,
            $setId,
            $viewerUserId,
            $selector,
            $limit ?? 50
        );
    }

    public function getRaceRatingsAction(string $events): array
    {
        return (new RaceRatingService())->getByEvent($events);
    }
}
