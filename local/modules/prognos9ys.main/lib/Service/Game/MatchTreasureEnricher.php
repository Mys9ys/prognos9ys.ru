<?php

namespace Prognos9ys\Main\Service\Game;

use Prognos9ys\Main\Model\Repository\GameEconomyRepository;

class MatchTreasureEnricher
{
    private GameEconomyRepository $repository;

    public function __construct(?GameEconomyRepository $repository = null)
    {
        $this->repository = $repository ?? new GameEconomyRepository();
    }

    /**
     * @param array<string, mixed> $sections
     */
    public function enrichEventMatches(int $userId, array &$sections): void
    {
        if ($userId <= 0 || !$sections) {
            return;
        }

        $matchIds = $this->collectMatchIds($sections);
        if (!$matchIds) {
            return;
        }

        try {
            $countMap = $this->repository->getTreasureChestCountMapForUser($userId, $matchIds);
        } catch (\Throwable $exception) {
            return;
        }

        foreach ($sections as &$section) {
            if (empty($section['items']) || !is_array($section['items'])) {
                continue;
            }

            foreach ($section['items'] as &$dates) {
                if (!is_array($dates)) {
                    continue;
                }

                foreach ($dates as &$match) {
                    $this->attachTreasure($match, $countMap);
                }
                unset($match);
            }
            unset($dates);
        }
        unset($section);
    }

    /**
     * @param array<string, mixed> $sections
     * @return int[]
     */
    private function collectMatchIds(array $sections): array
    {
        $ids = [];

        foreach ($sections as $section) {
            if (empty($section['items']) || !is_array($section['items'])) {
                continue;
            }

            foreach ($section['items'] as $dates) {
                if (!is_array($dates)) {
                    continue;
                }

                foreach ($dates as $match) {
                    if (!empty($match['id'])) {
                        $ids[] = (int)$match['id'];
                    }
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param array<string, mixed> $match
     * @param array<int, int> $countMap matchId => count
     */
    private function attachTreasure(array &$match, array $countMap): void
    {
        $matchId = (int)($match['id'] ?? 0);
        if ($matchId <= 0) {
            $match['treasure'] = ['count' => 0];

            return;
        }

        $match['treasure'] = [
            'count' => (int)($countMap[$matchId] ?? 0),
            'type' => 'match',
        ];
    }
}

