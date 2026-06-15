<?php

namespace Prognos9ys\Main\Controller;

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
    public function sendPrognosisAction(array $fields, ?string $userToken = null): array
    {
        $token = $userToken
            ?: (string)$this->getRequest()->get('userToken')
            ?: (string)$this->getRequest()->getPost('userToken');

        if (!$token) {
            throw new ApiException('Требуется токен авторизации', 401);
        }

        return (new FootballPrognosisService())->send($token, $fields);
    }
}
