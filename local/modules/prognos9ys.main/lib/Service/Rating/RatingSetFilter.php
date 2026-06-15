<?php

namespace Prognos9ys\Main\Service\Rating;

class RatingSetFilter
{
    /**
     * @param array<string, array<int|string, array<int|string, array<string, mixed>>>> $ratings
     * @param int[] $allowedUserIds
     */
    public static function filterRatings(array $ratings, array $allowedUserIds): array
    {
        if (!$allowedUserIds) {
            return [];
        }

        $allowed = array_flip(array_map('intval', $allowedUserIds));
        $filtered = [];

        foreach ($ratings as $selector => $tours) {
            if (!is_array($tours)) {
                continue;
            }

            $prevPlacesByUser = [];
            $tourKeys = array_keys($tours);
            sort($tourKeys, SORT_NUMERIC);

            foreach ($tourKeys as $tourNumber) {
                $rows = $tours[$tourNumber];
                if (!is_array($rows)) {
                    continue;
                }

                $subset = [];

                foreach ($rows as $uid => $row) {
                    if (!is_array($row)) {
                        continue;
                    }

                    $userId = (int)($row['user']['id'] ?? $uid);
                    if ($userId && isset($allowed[$userId])) {
                        $subset[$userId] = $row;
                    }
                }

                [$filteredRows, $prevPlacesByUser] = self::reassignPlaces(
                    $subset,
                    (string)$selector,
                    $prevPlacesByUser
                );

                $filtered[$selector][$tourNumber] = $filteredRows;
            }
        }

        return $filtered;
    }

    /**
     * @param array<int, array<string, mixed>> $rows keyed by user id
     * @param array<int, int> $prevPlacesByUser
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, int>}
     */
    private static function reassignPlaces(
        array $rows,
        string $selector,
        array $prevPlacesByUser
    ): array {
        if (!$rows) {
            return [[], $prevPlacesByUser];
        }

        uasort($rows, static function ($a, $b) {
            $scoreA = (float)($a['score'] ?? 0);
            $scoreB = (float)($b['score'] ?? 0);

            if ($scoreA == $scoreB) {
                return 0;
            }

            return ($scoreA > $scoreB) ? -1 : 1;
        });

        $place = 1;
        $count = 1;
        $prevScore = null;
        $sorted = [];

        foreach ($rows as $userId => $row) {
            $userId = (int)($row['user']['id'] ?? $userId);
            $score = (float)($row['score'] ?? 0);

            if ($prevScore === null || $score !== $prevScore) {
                $place = $count;
            }

            $row['place'] = $place;
            $row['diff'] = 0;

            if ($selector !== 'best' && isset($prevPlacesByUser[$userId])) {
                $row['diff'] = $prevPlacesByUser[$userId] - $place;
                if ($place === abs($row['diff'])) {
                    $row['diff'] = 0;
                }
            }

            $sorted[$userId] = $row;
            $prevScore = $score;
            $count++;
        }

        $newPrevPlaces = [];
        foreach ($sorted as $userId => $row) {
            $newPrevPlaces[(int)$userId] = (int)$row['place'];
        }

        $sorted = array_values($sorted);
        array_multisort(array_column($sorted, 'score'), SORT_DESC, $sorted);

        return [$sorted, $newPrevPlaces];
    }
}
