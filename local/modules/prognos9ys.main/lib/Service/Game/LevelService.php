<?php

namespace Prognos9ys\Main\Service\Game;

use Prognos9ys\Main\Model\Repository\GameEconomyRepository;

class LevelService
{
    private GameEconomyRepository $repository;

    public function __construct(?GameEconomyRepository $repository = null)
    {
        $this->repository = $repository ?? new GameEconomyRepository();
    }

    public function seedDefaultTiers(): void
    {
        $existing = $this->repository->getLevelTiers();

        if ($existing) {
            return;
        }

        $thresholds = GameEconomyConfig::defaultLevelThresholds();

        foreach ($thresholds as $level => $minXp) {
            $this->repository->addLevelTier([
                'UF_LEVEL' => (int)$level,
                'UF_MIN_XP' => (int)$minXp,
                'UF_TITLE' => $this->defaultTitleForLevel((int)$level),
            ]);
        }
    }

    /**
     * @return array<int, array{level:int,min_xp:int,title:string}>
     */
    public function getTiers(): array
    {
        $rows = $this->repository->getLevelTiers();
        $tiers = [];

        foreach ($rows as $row) {
            $level = (int)$row['UF_LEVEL'];
            $tiers[$level] = [
                'level' => $level,
                'min_xp' => (int)$row['UF_MIN_XP'],
                'title' => (string)($row['UF_TITLE'] ?? ''),
            ];
        }

        if (!$tiers) {
            foreach (GameEconomyConfig::defaultLevelThresholds() as $level => $minXp) {
                $tiers[$level] = [
                    'level' => $level,
                    'min_xp' => $minXp,
                    'title' => $this->defaultTitleForLevel($level),
                ];
            }
        }

        ksort($tiers);

        return $tiers;
    }

    public function getLevelFromXp(float $xp): int
    {
        $tiers = $this->getTiers();
        $level = 0;

        foreach ($tiers as $tierLevel => $tier) {
            if ($xp >= $tier['min_xp']) {
                $level = $tierLevel;
            }
        }

        return $level;
    }

    public function getProgressSummary(float $xp): array
    {
        $tiers = $this->getTiers();
        $level = $this->getLevelFromXp($xp);
        $currentMinXp = $tiers[$level]['min_xp'] ?? 0;
        $nextLevel = $level + 1;
        $nextMinXp = $tiers[$nextLevel]['min_xp'] ?? null;

        $progressPercent = 100.0;

        if ($nextMinXp !== null && $nextMinXp > $currentMinXp) {
            $progressPercent = round(
                (($xp - $currentMinXp) / ($nextMinXp - $currentMinXp)) * 100,
                1
            );
            $progressPercent = max(0, min(100, $progressPercent));
        }

        return [
            'level' => $level,
            'title' => $tiers[$level]['title'] ?? ('Уровень ' . $level),
            'xp' => round($xp, 1),
            'current_min_xp' => $currentMinXp,
            'next_level' => $nextMinXp !== null ? $nextLevel : null,
            'next_min_xp' => $nextMinXp,
            'xp_to_next' => $nextMinXp !== null ? max(0, round($nextMinXp - $xp, 1)) : 0,
            'progress_percent' => $progressPercent,
        ];
    }

    private function defaultTitleForLevel(int $level): string
    {
        $titles = [
            0 => 'Новичок',
            1 => 'Участник',
            2 => 'Познающий',
            3 => 'Болельщик',
            4 => 'Заядлый',
            5 => 'Эксперт',
        ];

        if (isset($titles[$level])) {
            return $titles[$level];
        }

        return 'Мастер ' . $level;
    }
}
