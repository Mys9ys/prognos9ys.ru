<?php

namespace Prognos9ys\Main\Service\Rating;

class RatingResponseShaper
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
        'best',
    ];

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function shape(
        array $payload,
        ?string $selector,
        int $limit,
        ?int $viewerUserId,
        ?int $matchNumber = null
    ): array {
        $ratings = $payload['ratings'] ?? [];
        if (!$ratings) {
            return $payload;
        }

        $existingMeta = $payload['meta'] ?? [];
        $matchTitles = is_array($existingMeta['match_titles'] ?? null)
            ? $existingMeta['match_titles']
            : [];

        $selectors = $this->resolveSelectors($selector);
        $shaped = [];
        $availableNumbers = $this->collectTourNumbers($ratings, $selectors);

        if ($matchTitles === [] && $availableNumbers !== []) {
            foreach ($availableNumbers as $n) {
                $matchTitles[(string)$n] = 'матч';
            }
        }

        $resolvedMatchNumber = $this->resolveMatchNumber($matchNumber, $availableNumbers);

        foreach ($selectors as $key) {
            if (!isset($ratings[$key]) || !is_array($ratings[$key])) {
                continue;
            }

            $shaped[$key] = $this->shapeSelectorTours(
                $ratings[$key],
                $limit,
                $viewerUserId,
                $resolvedMatchNumber
            );
        }

        $payload['ratings'] = $shaped;
        $payload['meta'] = array_merge(
            is_array($existingMeta) ? $existingMeta : [],
            [
                'selector' => $selector ?: 'all',
                'limit' => $limit,
                'selectors' => array_keys($shaped),
                'match_titles' => $matchTitles,
                'match_numbers' => $availableNumbers,
                'match_number' => $resolvedMatchNumber,
            ]
        );

        return $payload;
    }

    /**
     * @return string[]
     */
    private function resolveSelectors(?string $selector): array
    {
        $selector = trim((string)$selector);
        if ($selector === '' || $selector === 'all') {
            return ['all'];
        }

        if (!in_array($selector, self::SELECTORS, true)) {
            return ['all'];
        }

        return [$selector];
    }

    /**
     * @param array<string, mixed> $ratings
     * @param string[] $selectors
     * @return list<int>
     */
    private function collectTourNumbers(array $ratings, array $selectors): array
    {
        $numbers = [];

        foreach ($selectors as $key) {
            if (!isset($ratings[$key]) || !is_array($ratings[$key])) {
                continue;
            }

            foreach (array_keys($ratings[$key]) as $tourId) {
                $n = (int)$tourId;
                if ($n > 0) {
                    $numbers[$n] = $n;
                }
            }
        }

        $list = array_values($numbers);
        rsort($list, SORT_NUMERIC);

        return $list;
    }

    /**
     * @param list<int> $availableNumbers
     */
    private function resolveMatchNumber(?int $matchNumber, array $availableNumbers): ?int
    {
        if ($availableNumbers === []) {
            return null;
        }

        if ($matchNumber !== null && $matchNumber > 0) {
            if (in_array($matchNumber, $availableNumbers, true)) {
                return $matchNumber;
            }
        }

        return (int)$availableNumbers[0];
    }

    /**
     * @param array<int|string, array<int, array>> $tours
     * @return array<int|string, array<int, array>>
     */
    private function shapeSelectorTours(
        array $tours,
        int $limit,
        ?int $viewerUserId,
        ?int $matchNumber
    ): array {
        $limit = max(1, min(200, $limit));
        $shaped = [];

        if ($matchNumber === null || $matchNumber <= 0) {
            return $shaped;
        }

        $rows = $tours[$matchNumber] ?? $tours[(string)$matchNumber] ?? null;
        if (!is_array($rows)) {
            return $shaped;
        }

        $list = array_values($rows);
        $shaped[$matchNumber] = $this->limitRows($list, $limit, $viewerUserId);

        return $shaped;
    }

    /**
     * @param array<int, array> $rows
     * @return array<int, array>
     */
    private function limitRows(array $rows, int $limit, ?int $viewerUserId): array
    {
        if (count($rows) <= $limit) {
            return $this->compactRows($rows);
        }

        $top = array_slice($rows, 0, $limit);
        if ($viewerUserId <= 0) {
            return $this->compactRows($top);
        }

        foreach ($rows as $row) {
            $userId = (int)($row['user']['id'] ?? 0);
            if ($userId === $viewerUserId) {
                $alreadyInTop = false;
                foreach ($top as $topRow) {
                    if ((int)($topRow['user']['id'] ?? 0) === $viewerUserId) {
                        $alreadyInTop = true;
                        break;
                    }
                }

                if (!$alreadyInTop) {
                    $top[] = $row;
                }
                break;
            }
        }

        return $this->compactRows($top);
    }

    /**
     * Drop empty avatar paths to shrink JSON.
     *
     * @param array<int, array> $rows
     * @return array<int, array>
     */
    private function compactRows(array $rows): array
    {
        foreach ($rows as &$row) {
            if (!isset($row['user']) || !is_array($row['user'])) {
                continue;
            }

            $img = $row['user']['img'] ?? null;
            if ($img === null || $img === '' || $img === false) {
                unset($row['user']['img']);
            }
        }
        unset($row);

        return $rows;
    }
}
