<?php

namespace Prognos9ys\Main\Service\Game;

use Bitrix\Main\Loader;
use Prognos9ys\Main\Model\Repository\GameEconomyRepository;

class AchievementService
{
    private GameEconomyRepository $repository;
    private GameEventScopeService $scopeService;

    public function __construct(
        ?GameEconomyRepository $repository = null,
        ?GameEventScopeService $scopeService = null
    ) {
        $this->repository = $repository ?? new GameEconomyRepository();
        $this->scopeService = $scopeService ?? new GameEventScopeService();
    }

    public function getForUser(int $userId): array
    {
        if ($userId <= 0) {
            return [
                'stats' => [],
                'items' => [],
                'unlocked_count' => 0,
                'total_count' => 0,
            ];
        }

        $stats = $this->collectStats($userId);
        $items = [];

        foreach (AchievementConfig::getCatalog() as $code => $definition) {
            $target = (int)($definition['threshold'] ?? 1);
            $progress = $this->resolveProgress($definition, $stats);
            $unlocked = $progress >= $target;

            $items[] = [
                'code' => $code,
                'title' => $definition['title'],
                'description' => $definition['description'],
                'group' => $definition['group'],
                'unlocked' => $unlocked,
                'progress' => $progress,
                'target' => $target,
            ];
        }

        $unlockedCount = 0;
        foreach ($items as $item) {
            if ($item['unlocked']) {
                $unlockedCount++;
            }
        }

        return [
            'stats' => $stats,
            'items' => $items,
            'unlocked_count' => $unlockedCount,
            'total_count' => count($items),
        ];
    }

    private function collectStats(int $userId): array
    {
        $welcome = $this->repository->hasWalletTx($userId, 'registration_bonus', 'user', $userId)
            || $this->repository->getWalletByUserId($userId) !== null;

        $footballPrognosis = $this->countFootballPrognosis($userId);
        $chmPrognosis = $this->countChmPrognosis($userId);

        return [
            'welcome' => $welcome ? 1 : 0,
            'football_prognosis' => $footballPrognosis,
            'chm_prognosis' => $chmPrognosis,
        ];
    }

    private function resolveProgress(array $definition, array $stats): int
    {
        $statKey = (string)($definition['stat'] ?? '');
        if ($statKey === '' || !isset($stats[$statKey])) {
            return 0;
        }

        return (int)$stats[$statKey];
    }

    private function countFootballPrognosis(int $userId): int
    {
        $prognosisIbId = $this->getPrognosisIblockId();
        if ($prognosisIbId <= 0) {
            return 0;
        }

        return (int)\CIBlockElement::GetList(
            [],
            [
                'IBLOCK_ID' => $prognosisIbId,
                'PROPERTY_user_id' => $userId,
            ],
            []
        );
    }

    private function countChmPrognosis(int $userId): int
    {
        $prognosisIbId = $this->getPrognosisIblockId();
        $anchorEventId = $this->scopeService->getAnchorEventId();
        if ($prognosisIbId <= 0 || $anchorEventId <= 0) {
            return 0;
        }

        return (int)\CIBlockElement::GetList(
            [],
            [
                'IBLOCK_ID' => $prognosisIbId,
                'PROPERTY_user_id' => $userId,
                'PROPERTY_events' => $anchorEventId,
            ],
            []
        );
    }

    private function getPrognosisIblockId(): int
    {
        if (!Loader::includeModule('iblock')) {
            return 0;
        }

        return (int)(\CIBlock::GetList([], ['CODE' => 'prognosis'], false)->Fetch()['ID'] ?? 0);
    }
}
