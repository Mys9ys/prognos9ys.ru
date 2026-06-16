<?php

namespace Prognos9ys\Main\Service\Game;

class GameProfileService
{
    private WalletService $walletService;
    private UserProgressService $progressService;

    public function __construct(
        ?WalletService $walletService = null,
        ?UserProgressService $progressService = null
    ) {
        $this->walletService = $walletService ?? new WalletService();
        $this->progressService = $progressService ?? new UserProgressService();
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
            ];
        } catch (\Throwable $exception) {
            return [
                'wallet' => [
                    'prognobaks' => 0,
                    'rublius' => 0,
                    'rublius_rate' => GameEconomyConfig::RUBLIUS_TO_PROGNOBAKS,
                ],
                'progress' => (new LevelService())->getProgressSummary(0),
            ];
        }
    }
}
