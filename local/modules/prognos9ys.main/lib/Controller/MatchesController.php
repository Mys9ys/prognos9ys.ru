<?php

namespace Prognos9ys\Main\Controller;

use Prognos9ys\Main\Service\Football\MatchListService;

class MatchesController extends BaseController
{
    public function configureActions(): array
    {
        return [
            'getByEvent' => $this->getDefaultConfigureForPostPublic(),
        ];
    }

    /**
     * Список матчей соревнования — публичный read-only эндпоинт.
     */
    public function getByEventAction(int $eventId): array
    {
        return (new MatchListService())->getByEventId($eventId);
    }
}
