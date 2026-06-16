<?php

namespace Prognos9ys\Main\Service\Game;

use Prognos9ys\Main\Model\Repository\GameEconomyRepository;

class UserProgressService
{
    private GameEconomyRepository $repository;
    private LevelService $levelService;

    public function __construct(?GameEconomyRepository $repository = null, ?LevelService $levelService = null)
    {
        $this->repository = $repository ?? new GameEconomyRepository();
        $this->levelService = $levelService ?? new LevelService($this->repository);
    }

    public function ensureProgress(int $userId): array
    {
        $row = $this->repository->getProgressByUserId($userId);

        if ($row) {
            return $this->formatProgress((float)$row['UF_XP']);
        }

        $id = $this->repository->addProgress([
            'UF_USER_ID' => $userId,
            'UF_XP' => 0,
        ]);

        return $this->formatProgress(0);
    }

    public function addXp(int $userId, float $points): array
    {
        $row = $this->repository->getProgressByUserId($userId);

        if (!$row) {
            $this->ensureProgress($userId);
            $row = $this->repository->getProgressByUserId($userId);
        }

        $newXp = round((float)$row['UF_XP'] + $points, 1);

        $this->repository->updateProgress((int)$row['ID'], [
            'UF_XP' => $newXp,
        ]);

        return $this->formatProgress($newXp);
    }

    public function getSummary(int $userId): array
    {
        $row = $this->repository->getProgressByUserId($userId);
        $xp = $row ? (float)$row['UF_XP'] : 0.0;

        return $this->formatProgress($xp);
    }

    private function formatProgress(float $xp): array
    {
        return $this->levelService->getProgressSummary($xp);
    }
}
