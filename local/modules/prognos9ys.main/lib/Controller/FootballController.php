<?php

namespace Prognos9ys\Main\Controller;

use Prognos9ys\Main\Service\Football\EventStatisticsService;
use Prognos9ys\Main\Service\Football\FootballEventListService;
use Prognos9ys\Main\Service\Football\FootballMatchService;
use Prognos9ys\Main\Service\Football\FootballPrognosisService;
use Prognos9ys\Main\Service\Football\MatchListService;

class FootballController extends BaseController
{
    public function configureActions(): array
    {
        return [
            'getEventMatches' => $this->getDefaultConfigureForPost(true),
            'getEventStatistics' => $this->getDefaultConfigureForPost(true),
            'getMatchesByEvent' => $this->getDefaultConfigureForPostPublic(),
            'getMatch' => $this->getDefaultConfigureForPost(true),
            'sendPrognosis' => $this->getDefaultConfigureForPostToken(),
        ];
    }

    /**
     * Полный список матчей события — совместим с legacy /mob_app/ajax/football/many/
     */
    public function getEventMatchesAction(string $events, ?string $userToken = null): array
    {
        return (new FootballEventListService())->getByEvent($events, $userToken);
    }

    /**
     * Сводная статистика события: факты матчей и прогнозы пользователя.
     */
    public function getEventStatisticsAction(string $events, ?string $userToken = null): array
    {
        $eventId = (int)$events;
        $userId = 0;

        if ($userToken) {
            $userId = (int)((new \GetUserIdForToken($userToken))->getId() ?: 0);
        }

        return (new EventStatisticsService())->getForEvent($eventId, $userId);
    }

    /**
     * Упрощённый ORM-список матчей — для публичных страниц и шаринга.
     */
    public function getMatchesByEventAction(int $eventId): array
    {
        return (new MatchListService())->getByEventId($eventId);
    }

    /**
     * Один матч — совместим с legacy /mob_app/ajax/football/one/
     */
    public function getMatchAction(string $eventId, string $number, ?string $userToken = null): array
    {
        return (new FootballMatchService())->getMatch($eventId, $number, $userToken);
    }

    /**
     * Сохранение прогноза пользователя — legacy /mob_app/ajax/football/send/
     */
    public function sendPrognosisAction(array $fields, ?string $userToken = null, $withBet = null): array
    {
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

        return (new FootballPrognosisService())->send($token, $fields, $withBetFlag);
    }
}
