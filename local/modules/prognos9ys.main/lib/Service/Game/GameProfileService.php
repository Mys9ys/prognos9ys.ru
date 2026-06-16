<?php

namespace Prognos9ys\Main\Service\Game;

class GameProfileService
{
    private WalletService $walletService;
    private UserProgressService $progressService;
    private TreasureService $treasureService;

    public function __construct(
        ?WalletService $walletService = null,
        ?UserProgressService $progressService = null,
        ?TreasureService $treasureService = null
    ) {
        $this->walletService = $walletService ?? new WalletService();
        $this->progressService = $progressService ?? new UserProgressService();
        $this->treasureService = $treasureService ?? new TreasureService();
    }

    public function getSummary(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        try {
            return [
                'wallet' => $this->walletService->getWalletSummary($userId),
                'progress' => $this->progressService->getSummary($userId),
                'treasure' => $this->treasureService->getTreasureSummary($userId),
            ];
        } catch (\Throwable $exception) {
            return [
                'wallet' => [
                    'prognobaks' => 0,
                    'rublius' => 0,
                    'rublius_rate' => GameEconomyConfig::RUBLIUS_TO_PROGNOBAKS,
                ],
                'progress' => (new LevelService())->getProgressSummary(0),
                'treasure' => [
                    'closed_chests' => 0,
                ],
            ];
        }
    }
}
