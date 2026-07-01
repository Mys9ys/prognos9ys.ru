<?php

namespace Prognos9ys\Main\Service\Football;

class FootballMatchService
{
    public function getMatch(string $eventId, string $number, ?string $userToken = null): array
    {
        $handler = new \FootballMatchLoadInfo([
            'eventId' => $eventId,
            'number' => $number,
            'userToken' => $userToken ?? '',
        ]);

        $result = $handler->result();
        if (($result['status'] ?? '') !== 'ok' || !is_array($result['result'] ?? null)) {
            return $result;
        }

        $userId = 0;
        if ($userToken) {
            $userId = (int)((new \GetUserIdForToken($userToken))->getId() ?: 0);
        }

        $result['result']['premium_prognosis'] = (new FootballPrognosisEditService())
            ->buildState($userId, $result['result']);

        return $result;
    }
}
