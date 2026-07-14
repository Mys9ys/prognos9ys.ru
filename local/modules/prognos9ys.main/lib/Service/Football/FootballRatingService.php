<?php

namespace Prognos9ys\Main\Service\Football;

use Bitrix\Main\Data\Cache;
use Prognos9ys\Main\Service\Auth\TokenAuthService;
use Prognos9ys\Main\Service\Rating\FootballRatingCalculator;
use Prognos9ys\Main\Service\Rating\RatingResponseShaper;
use Prognos9ys\Main\Service\Rating\RatingSetFilter;
use Prognos9ys\Main\Service\Rating\RatingSetService;

class FootballRatingService
{
    private const CACHE_DIR = '/prognos9ys/football_ratings/';
    private const CACHE_TTL = 300;

    public function getByEvent(
        $eventId,
        ?int $setId = null,
        ?int $viewerUserId = null,
        ?string $selector = null,
        int $limit = 50,
        ?int $matchNumber = null
    ): array {
        $eventId = (int)$eventId;
        $payload = $this->getCachedCalculation($eventId);

        if ($setId) {
            $setService = new RatingSetService();
            $memberIds = $setService->getMemberIdsForRating($setId, $viewerUserId, $eventId);
            $setInfo = $setService->getById($setId, $viewerUserId);

            $payload['ratings'] = RatingSetFilter::filterRatings($payload['ratings'] ?? [], $memberIds);
            $payload['ratingSet'] = $setInfo['set'] ?? null;
        }

        return (new RatingResponseShaper())->shape(
            $payload,
            $selector,
            $limit,
            $viewerUserId,
            $matchNumber
        );
    }

    public function resolveViewerUserId(?string $token): ?int
    {
        if (!$token) {
            return TokenAuthService::getCurrentUserId();
        }

        return (new TokenAuthService())->getUserIdByToken($token);
    }

    public static function clearEventCache(int $eventId): void
    {
        if ($eventId <= 0) {
            return;
        }

        $cache = Cache::createInstance();
        $cache->clean(self::cacheId($eventId), self::CACHE_DIR);
    }

    private function getCachedCalculation(int $eventId): array
    {
        $cache = Cache::createInstance();
        $cacheId = self::cacheId($eventId);

        if ($cache->initCache(self::CACHE_TTL, $cacheId, self::CACHE_DIR)) {
            return $cache->getVars();
        }

        if ($cache->startDataCache()) {
            $payload = (new FootballRatingCalculator())->calculate($eventId);
            $cache->endDataCache($payload);

            return $payload;
        }

        return (new FootballRatingCalculator())->calculate($eventId);
    }

    private static function cacheId(int $eventId): string
    {
        return 'football_rating_v1_' . $eventId;
    }
}
