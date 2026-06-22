<?php

namespace Prognos9ys\Main\Controller;

use Prognos9ys\Main\Service\Cs2\Cs2EventListService;
use Prognos9ys\Main\Service\Cs2\Cs2MapsService;
use Prognos9ys\Main\Service\Cs2\Cs2MatchService;
use Prognos9ys\Main\Service\Cs2\Cs2PrognosisService;

class Cs2Controller extends BaseController
{
    public function configureActions(): array
    {
        return [
            'getEventMatches' => $this->getDefaultConfigureForPost(true),
            'getMatch' => $this->getDefaultConfigureForPost(true),
            'getMaps' => $this->getDefaultConfigureForPost(true),
            'sendPrognosis' => $this->getDefaultConfigureForPostToken(),
        ];
    }

    public function getEventMatchesAction(string $events, ?string $userToken = null): array
    {
        return (new Cs2EventListService())->getByEvent($events, $userToken);
    }

    public function getMatchAction(string $eventId, string $number, ?string $userToken = null): array
    {
        return (new Cs2MatchService())->getMatch($eventId, $number, $userToken);
    }

    public function getMapsAction(): array
    {
        return [
            'maps' => (new Cs2MapsService())->getPoolMaps(),
        ];
    }

    public function sendPrognosisAction(
        array $fields,
        ?string $userToken = null,
        $withBet = null,
        ?string $map_scores_json = null
    ): array {
        $token = $userToken
            ?: (string)$this->getRequest()->get('userToken')
            ?: (string)$this->getRequest()->getPost('userToken');

        if (!$token) {
            throw new ApiException('Требуется токен авторизации', 401);
        }

        $rawWithBet = $withBet;
        if ($rawWithBet === null) {
            $rawWithBet = $this->getRequest()->get('withBet');
        }
        if ($rawWithBet === null) {
            $rawWithBet = $this->getRequest()->getPost('withBet');
        }

        $withBetFlag = null;
        if ($rawWithBet !== null && $rawWithBet !== '') {
            $withBetFlag = filter_var($rawWithBet, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($withBetFlag === null) {
                $withBetFlag = (string)$rawWithBet === '1';
            }
        }

        return (new Cs2PrognosisService())->send($token, $fields, $map_scores_json, $withBetFlag);
    }
}
