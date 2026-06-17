<?php

namespace Prognos9ys\Main\Service\Rating;

use Bitrix\Main\Loader;
use Bitrix\Main\UserTable;
use Prognos9ys\Main\Model\Repository\FootballResultsRepository;

/**
 * Расчёт футбольных рейтингов с накоплением по турам (номер матча).
 */
class FootballRatingCalculator
{
    private const SELECTORS = [
        'all',
        'score',
        'result',
        'sum',
        'diff',
        'domination',
        'yellow',
        'red',
        'corner',
        'penalty',
        'otime',
        'spenalty',
    ];

    private int $eventId;

    /** @var array<int|string, array{id: int|string, name: string, img: ?string}> */
    private array $users = [];

    /** @var array<string, array<int, array<int, array<string, mixed>>>> */
    private array $results = [];

    /** @var array<string, array<int, array<int, float>>> */
    private array $middleResults = [];

    /** @var array<int|string, int> */
    private array $userScore = [];

    /** @var array<int|string, int|string> */
    private array $matchIdToNumber = [];

    /** @var array<int, string> */
    private array $matchTitleByNumber = [];

    private MatchNumberMapService $matchNumberMapService;

    private FootballResultsRepository $resultsRepository;

    public function __construct(
        ?MatchNumberMapService $matchNumberMapService = null,
        ?FootballResultsRepository $resultsRepository = null
    ) {
        $this->matchNumberMapService = $matchNumberMapService ?? new MatchNumberMapService();
        $this->resultsRepository = $resultsRepository ?? new FootballResultsRepository();
    }

    public function calculate(int $eventId): array
    {
        if ($eventId <= 0) {
            return ['status' => 'ok', 'ratings' => []];
        }

        if (!Loader::includeModule('iblock')) {
            throw new \RuntimeException('Модуль iblock не установлен');
        }

        $this->eventId = $eventId;
        $this->users = [];
        $this->results = [];
        $this->middleResults = [];
        $this->userScore = [];

        $this->matchIdToNumber = $this->matchNumberMapService->getMapForEvent($eventId);
        $this->matchTitleByNumber = $this->loadMatchTitlesByNumber($eventId);

        $this->loadResults();
        $this->loadUsers(array_keys($this->userScore));
        $this->accumulateByTour();
        $this->assignPlacesAndSort();

        return [
            'status' => 'ok',
            'ratings' => $this->results,
            'meta' => [
                'match_titles' => $this->matchTitleByNumber,
            ],
        ];
    }

    /**
     * @return array<int, string> number => "Team — Team"
     */
    private function loadMatchTitlesByNumber(int $eventId): array
    {
        if ($eventId <= 0 || !Loader::includeModule('iblock')) {
            return [];
        }

        // Use existing teams dictionary.
        $teams = (new \GetFootballTeams())->result();

        $rs = \CIBlockElement::GetList(
            ['DATE_ACTIVE_FROM' => 'ASC', 'ID' => 'ASC'],
            [
                'IBLOCK_ID' => 2, // matches
                'PROPERTY_EVENTS' => $eventId,
            ],
            false,
            false,
            [
                'ID',
                'PROPERTY_number',
                'PROPERTY_home',
                'PROPERTY_guest',
            ]
        );

        $map = [];
        while ($row = $rs->GetNext()) {
            $number = (int)($row['PROPERTY_NUMBER_VALUE'] ?? 0);
            if ($number <= 0) {
                continue;
            }

            $homeId = (int)($row['PROPERTY_HOME_VALUE'] ?? 0);
            $guestId = (int)($row['PROPERTY_GUEST_VALUE'] ?? 0);

            $homeName = (string)($teams[$homeId]['NAME'] ?? '');
            $guestName = (string)($teams[$guestId]['NAME'] ?? '');

            if ($homeName !== '' && $guestName !== '') {
                $map[$number] = $homeName . ' — ' . $guestName;
            }
        }

        return $map;
    }

    /**
     * @param array<int|string> $userIds
     */
    private function loadUsers(array $userIds): void
    {
        if (!$userIds) {
            return;
        }

        $row = UserTable::getList([
            'filter' => ['@ID' => $userIds],
            'select' => ['ID', 'NAME', 'PERSONAL_PHOTO'],
        ]);

        while ($res = $row->fetch()) {
            $id = (int)$res['ID'];
            $this->users[$id] = [
                'id' => $id,
                'name' => $res['NAME'] ?: ('Игрок #' . $id),
                'img' => $res['PERSONAL_PHOTO'] ? \CFile::GetPath($res['PERSONAL_PHOTO']) : null,
            ];
        }
    }

    private function resolveUser($userId): array
    {
        $id = (int)$userId;
        if ($id <= 0) {
            return [
                'id' => 0,
                'name' => 'Неизвестный',
                'img' => null,
            ];
        }

        if (isset($this->users[$id])) {
            return $this->users[$id];
        }

        return [
            'id' => $id,
            'name' => 'Игрок #' . $id,
            'img' => null,
        ];
    }

    private function loadResults(): void
    {
        foreach ($this->resultsRepository->fetchByEvent($this->eventId) as $res) {
            $userId = (int)($res['PROPERTY_USER_ID_VALUE'] ?? 0);
            if ($userId <= 0) {
                continue;
            }

            $this->userScore[$userId] = ($this->userScore[$userId] ?? 0) + 1;

            foreach (self::SELECTORS as $selector) {
                $matchId = (int)($res['PROPERTY_MATCH_ID_VALUE'] ?? 0);
                $number = $this->matchIdToNumber[$matchId] ?? null;
                if ($number === null) {
                    continue;
                }

                $propKey = 'PROPERTY_' . strtoupper($selector) . '_VALUE';
                $this->middleResults[$selector][$number][$userId] = (float)($res[$propKey] ?? 0);

                if ((float)($res['PROPERTY_ALL_VALUE'] ?? 0) >= 30) {
                    $this->results['best'][$number][$userId] = $res['PROPERTY_ALL_VALUE'];
                }
            }
        }
    }

    /**
     * Накопительная сумма баллов по номеру тура — «отпечаток» рейтинга на каждый матч.
     */
    private function accumulateByTour(): void
    {
        foreach ($this->middleResults as $selector => $category) {
            foreach ($category as $number => $scores) {
                foreach ($this->userScore as $userId => $count) {
                    if ($scores[$userId]) {
                        $this->results[$selector][$number][$userId] = $scores[$userId];
                    }

                    if ($this->results[$selector][$number - 1][$userId]) {
                        $this->results[$selector][$number][$userId] += $this->results[$selector][$number - 1][$userId];
                    } else {
                        $this->results[$selector][$number][$userId] += $this->results[$selector][$number - 2][$userId];
                    }
                }
            }
        }
    }

    private function assignPlacesAndSort(): void
    {
        foreach ($this->results as $selector => $match) {
            foreach ($match as $id => $scores) {
                uasort($scores, [self::class, 'sortByScoreDesc']);

                $place = 1;
                $prev = '';
                $count = 1;
                $sorted = [];

                foreach ($scores as $uid => $score) {
                    if ($score !== $prev) {
                        $place = $count;
                    }

                    $row = [
                        'place' => $place,
                        'user' => $this->resolveUser($uid),
                        'score' => $score,
                        'diff' => 0,
                    ];

                    if ($id > 1 && $selector !== 'best') {
                        $row['diff'] = $this->results[$selector][$id - 1][$uid]['place'] - $place;
                        if ($place === abs($row['diff'])) {
                            $row['diff'] = 0;
                        }
                    }

                    $sorted[$uid] = $row;
                    $prev = $score;
                    $count++;
                }

                $this->results[$selector][$id] = $sorted;
            }
        }

        foreach ($this->results as $selector => $match) {
            foreach ($match as $id => $scores) {
                array_multisort(array_column($scores, 'score'), SORT_DESC, $scores);
                $this->results[$selector][$id] = $scores;
            }
        }
    }

    private static function sortByScoreDesc($a, $b): int
    {
        if ($a == $b) {
            return 0;
        }

        return ($a > $b) ? -1 : 1;
    }
}
