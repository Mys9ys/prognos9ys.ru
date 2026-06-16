<?php

namespace Prognos9ys\Main\Service\Game;

class MatchXpEnricher
{
    private ExperienceService $experienceService;

    public function __construct(?ExperienceService $experienceService = null)
    {
        $this->experienceService = $experienceService ?? new ExperienceService();
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
        $finishedWithScore = $this->collectFinishedMatchIdsWithScore($sections);

        if ($finishedWithScore) {
            foreach ($finishedWithScore as $matchId) {
                $this->experienceService->syncPendingForMatch($matchId);
            }
        }

        if (!$matchIds) {
            return;
        }

        $pendingMap = $this->experienceService->getPendingMapForUser($userId, $matchIds);

        foreach ($sections as &$section) {
            if (empty($section['items']) || !is_array($section['items'])) {
                continue;
            }

            foreach ($section['items'] as &$dates) {
                if (!is_array($dates)) {
                    continue;
                }

                foreach ($dates as &$match) {
                    $this->attachXpReward($match, $pendingMap);
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
                    if (!empty($match['id']) && $this->isMatchInScope($match)) {
                        $ids[] = (int)$match['id'];
                    }
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param array<string, mixed> $sections
     * @return int[]
     */
    private function collectFinishedMatchIdsWithScore(array $sections): array
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
                    if (
                        !empty($match['id'])
                        && (string)($match['active'] ?? '') === 'N'
                        && $this->isMatchInScope($match)
                    ) {
                        $ids[] = (int)$match['id'];
                    }
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param array<string, mixed> $match
     * @param array<int, array> $pendingMap
     */
    private function attachXpReward(array &$match, array $pendingMap): void
    {
        $matchId = (int)($match['id'] ?? 0);

        if ($matchId <= 0 || (string)($match['active'] ?? '') !== 'N' || !$this->isMatchInScope($match)) {
            $match['xp_reward'] = null;

            return;
        }

        $pending = $pendingMap[$matchId] ?? null;

        if (!$pending) {
            $match['xp_reward'] = [
                'points' => 0,
                'status' => null,
                'can_claim' => false,
            ];

            return;
        }

        $match['xp_reward'] = $pending;
    }

    /**
     * @param array<string, mixed> $match
     */
    private function isMatchInScope(array $match): bool
    {
        return (new GameEventScopeService())->isMatchInScope(
            (int)($match['event'] ?? 0),
            (int)($match['number'] ?? 0)
        );
    }
}
