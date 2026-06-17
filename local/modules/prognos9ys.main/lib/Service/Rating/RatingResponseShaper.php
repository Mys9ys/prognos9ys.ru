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
    public function shape(array $payload, ?string $selector, int $limit, ?int $viewerUserId): array
    {
        $ratings = $payload['ratings'] ?? [];
        if (!$ratings) {
            return $payload;
        }

        $existingMeta = $payload['meta'] ?? [];

        $selectors = $this->resolveSelectors($selector);
        $shaped = [];

        foreach ($selectors as $key) {
            if (!isset($ratings[$key])) {
                continue;
            }

            $shaped[$key] = $this->shapeSelectorTours($ratings[$key], $limit, $viewerUserId);
        }

        $payload['ratings'] = $shaped;
        $payload['meta'] = array_merge(
            is_array($existingMeta) ? $existingMeta : [],
            [
                'selector' => $selector ?: 'all',
                'limit' => $limit,
                'selectors' => array_keys($shaped),
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
     * @param array<int|string, array<int, array>> $tours
     * @return array<int|string, array<int, array>>
     */
    private function shapeSelectorTours(array $tours, int $limit, ?int $viewerUserId): array
    {
        $limit = max(1, min(200, $limit));
        $shaped = [];

        foreach ($tours as $tourId => $rows) {
            if (!is_array($rows)) {
                continue;
            }

            $list = array_values($rows);
            $shaped[$tourId] = $this->limitRows($list, $limit, $viewerUserId);
        }

        return $shaped;
    }

    /**
     * @param array<int, array> $rows
     * @return array<int, array>
     */
    private function limitRows(array $rows, int $limit, ?int $viewerUserId): array
    {
        if (count($rows) <= $limit) {
            return $rows;
        }

        $top = array_slice($rows, 0, $limit);
        if ($viewerUserId <= 0) {
            return $top;
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

        return $top;
    }
}
