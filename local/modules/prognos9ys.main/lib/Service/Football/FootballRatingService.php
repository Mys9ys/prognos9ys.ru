<?php

namespace Prognos9ys\Main\Service\Football;

use Prognos9ys\Main\Service\Auth\TokenAuthService;
use Prognos9ys\Main\Service\Rating\FootballRatingCalculator;
use Prognos9ys\Main\Service\Rating\RatingSetFilter;
use Prognos9ys\Main\Service\Rating\RatingSetService;

class FootballRatingService
{
    public function getByEvent($eventId, ?int $setId = null, ?int $viewerUserId = null): array
    {
        $payload = (new FootballRatingCalculator())->calculate((int)$eventId);

        if (!$setId) {
            return $payload;
        }

        $setService = new RatingSetService();
        $memberIds = $setService->getMemberIdsForRating($setId, $viewerUserId, (int)$eventId);
        $setInfo = $setService->getById($setId, $viewerUserId);

        $payload['ratings'] = RatingSetFilter::filterRatings($payload['ratings'] ?? [], $memberIds);
        $payload['ratingSet'] = $setInfo['set'] ?? null;

        return $payload;
    }

    public function resolveViewerUserId(?string $token): ?int
    {
        if (!$token) {
            return TokenAuthService::getCurrentUserId();
        }

        return (new TokenAuthService())->getUserIdByToken($token);
    }
}
