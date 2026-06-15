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

        $this->loadResults();
        $this->loadUsers(array_keys($this->userScore));
        $this->accumulateByTour();
        $this->assignPlacesAndSort();

        return [
            'status' => 'ok',
            'ratings' => $this->results,
        ];
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
            $this->users[$res['ID']] = [
                'id' => $res['ID'],
                'name' => $res['NAME'],
                'img' => $res['PERSONAL_PHOTO'] ? \CFile::GetPath($res['PERSONAL_PHOTO']) : null,
            ];
        }
    }

    private function loadResults(): void
    {
        foreach ($this->resultsRepository->fetchByEvent($this->eventId) as $res) {
            $this->userScore[$res['PROPERTY_USER_ID_VALUE']] += 1;

            foreach (self::SELECTORS as $selector) {
                $number = $this->matchIdToNumber[$res['PROPERTY_MATCH_ID_VALUE']];

                $this->middleResults[$selector][$number][$res['PROPERTY_USER_ID_VALUE']] = (float)$res['PROPERTY_' . strtoupper($selector) . '_VALUE'] ?? 0;

                if ($res['PROPERTY_ALL_VALUE'] > 30) {
                    $this->results['best'][$number][$res['PROPERTY_USER_ID_VALUE']] = $res['PROPERTY_ALL_VALUE'];
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
                        'user' => $this->users[$uid],
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
