<?php

namespace Prognos9ys\Main\Service\Game;

use Prognos9ys\Main\Model\Repository\GameEconomyRepository;

class MatchBetRewardEnricher
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
            $betMap = $this->repository->getMatchBetMapForUser($userId, $matchIds);
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
                    $this->attachBetReward($match, $betMap);
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
     * @param array<int, array{status:string,payout:float}> $betMap
     */
    private function attachBetReward(array &$match, array $betMap): void
    {
        $matchId = (int)($match['id'] ?? 0);
        $default = [
            'status' => '',
            'payout' => 0.0,
        ];

        if ($matchId <= 0) {
            $match['bet_reward'] = $default;

            return;
        }

        $match['bet_reward'] = $betMap[$matchId] ?? $default;
    }
}
