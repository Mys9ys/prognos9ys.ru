<?php

namespace Prognos9ys\Main\Service\Football;

use Prognos9ys\Main\Service\Game\GameEventScopeService;
use Prognos9ys\Main\Service\Game\MatchBetRewardEnricher;
use Prognos9ys\Main\Service\Game\MatchXpEnricher;

class FootballEventListService
{
    public function getByEvent(string $events, ?string $userToken = null): array
    {
        $handler = new \FootballHandlerClass([
            'events' => $events,
            'userToken' => $userToken ?? '',
        ]);

        $result = $handler->result();

        $eventId = (int)$events;

        if (
            ($result['status'] ?? '') !== 'ok'
            || empty($result['info'])
            || $eventId <= 0
            || !(new GameEventScopeService())->isEventEligible($eventId)
        ) {
            return $result;
        }

        $userId = 0;
        if ($userToken) {
            $userId = (int)((new \GetUserIdForToken($userToken))->getId() ?: 0);
        }

        if ($userId > 0) {
            (new MatchXpEnricher())->enrichEventMatches($userId, $result['info']);
            (new MatchBetRewardEnricher())->enrichEventMatches($userId, $result['info']);
        }

        return $result;
    }
}
