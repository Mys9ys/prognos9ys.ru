<?php

namespace Prognos9ys\Main\Service\Championship;

use Bitrix\Main\Loader;
use Prognos9ys\Main\Model\Repository\CountriesRepository;
use Prognos9ys\Main\Model\Repository\EventsRepository;
use Prognos9ys\Main\Model\Repository\MatchesRepository;
use Prognos9ys\Main\Model\Repository\UserMatchMarksRepository;
use Prognos9ys\Main\Service\Auth\TokenAuthService;

class FootballTableService
{
    private MatchesRepository $matchesRepository;
    private EventsRepository $eventsRepository;
    private CountriesRepository $countriesRepository;
    private UserMatchMarksRepository $userMarksRepository;
    private FootballTableBuilder $builder;
    private TokenAuthService $tokenAuth;

    public function __construct(
        ?MatchesRepository $matchesRepository = null,
        ?EventsRepository $eventsRepository = null,
        ?CountriesRepository $countriesRepository = null,
        ?UserMatchMarksRepository $userMarksRepository = null,
        ?FootballTableBuilder $builder = null,
        ?TokenAuthService $tokenAuth = null
    ) {
        $this->matchesRepository = $matchesRepository ?? new MatchesRepository();
        $this->eventsRepository = $eventsRepository ?? new EventsRepository();
        $this->countriesRepository = $countriesRepository ?? new CountriesRepository();
        $this->userMarksRepository = $userMarksRepository ?? new UserMatchMarksRepository();
        $this->builder = $builder ?? new FootballTableBuilder();
        $this->tokenAuth = $tokenAuth ?? new TokenAuthService();
    }

    public function getTable(string $events, ?string $token = null): array
    {
        if (!Loader::includeModule('iblock')) {
            return ['status' => 'error', 'mes' => 'Модуль Информационных блоков не установлен'];
        }

        $eventId = (int)$events;
        if ($eventId <= 0) {
            return ['status' => 'error', 'mes' => 'Некорректное событие'];
        }

        $eventInfo = $this->eventsRepository->getChampionshipInfo($eventId);
        if (!$eventInfo) {
            return ['status' => 'error', 'mes' => 'Событие не найдено'];
        }

        $matches = $this->matchesRepository->findForChampionshipTable($eventId);
        $teamIds = $this->collectTeamIds($matches);
        $teamsById = $this->countriesRepository->findIndexedByIds($teamIds);

        $userId = $this->tokenAuth->getUserIdByToken($token);
        $userMarks = $userId
            ? $this->userMarksRepository->loadForEvent($eventId, $userId)
            : ['prognosis' => [], 'results' => []];

        $table = $this->builder->build(
            $matches,
            $teamsById,
            $userMarks['prognosis'],
            $userMarks['results']
        );

        return [
            'status' => 'ok',
            'mes' => '',
            'result' => array_merge($table, ['info' => $eventInfo]),
        ];
    }

    /**
     * @param list<array<string, mixed>> $matches
     * @return list<int>
     */
    private function collectTeamIds(array $matches): array
    {
        $ids = [];

        foreach ($matches as $match) {
            foreach (['home_id', 'guest_id'] as $field) {
                $teamId = (int)($match[$field] ?? 0);
                if ($teamId > 0) {
                    $ids[$teamId] = $teamId;
                }
            }
        }

        return array_values($ids);
    }
}
