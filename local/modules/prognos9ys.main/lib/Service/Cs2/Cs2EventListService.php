<?php

namespace Prognos9ys\Main\Service\Cs2;

use Prognos9ys\Main\Service\Game\GameEventScopeService;
use Prognos9ys\Main\Service\Game\MatchBetRewardEnricher;
use Prognos9ys\Main\Service\Game\MatchTreasureEnricher;
use Prognos9ys\Main\Service\Game\MatchXpEnricher;

class Cs2EventListService
{
    public function getByEvent(string $events, ?string $userToken = null): array
    {
        $handler = new \Cs2HandlerClass([
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
            (new MatchTreasureEnricher())->enrichEventMatches($userId, $result['info']);
        }

        return $result;
    }
}
