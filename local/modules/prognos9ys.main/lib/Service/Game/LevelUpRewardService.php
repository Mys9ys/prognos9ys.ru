<?php

namespace Prognos9ys\Main\Service\Game;

use Prognos9ys\Main\Model\Repository\GameEconomyRepository;

class LevelUpRewardService
{
    private GameEconomyRepository $repository;
    private WalletService $walletService;
    private TreasureService $treasureService;
    private UserProgressService $progressService;

    public function __construct(?GameEconomyRepository $repository = null)
    {
        $this->repository = $repository ?? new GameEconomyRepository();
        $this->walletService = new WalletService($this->repository);
        $this->treasureService = new TreasureService($this->repository);
        $this->progressService = new UserProgressService($this->repository);
    }

    /**
     * @return array<int, array{level:int,prognobaks:float,rublius:float,chests:int}>
     */
    public function grantForLevelRange(int $userId, int $oldLevel, int $newLevel): array
    {
        if ($userId <= 0 || $newLevel <= $oldLevel) {
            return [];
        }

        $granted = [];

        for ($level = $oldLevel + 1; $level <= $newLevel; $level++) {
            $reward = $this->grantForLevel($userId, $level);

            if ($reward) {
                $granted[] = $reward;
            }
        }

        return $granted;
    }

    /**
     * Выдать пропущенные награды за уже достигнутые уровни (идемпотентно).
     *
     * @return array<int, array{level:int,prognobaks:float,rublius:float,chests:int}>
     */
    public function grantMissedRewards(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $currentLevel = (int)($this->progressService->getSummary($userId)['level'] ?? 0);

        return $this->grantForLevelRange($userId, 0, $currentLevel);
    }

    /**
     * Доначислить только сундуки за уровни 1…текущий (без кошелька).
     */
    public function grantMissedLevelChests(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        $currentLevel = (int)($this->progressService->getSummary($userId)['level'] ?? 0);
        $granted = 0;

        for ($level = 1; $level <= $currentLevel; $level++) {
            if ($this->treasureService->grantLevelUpChest($userId, $level)) {
                $granted++;
            }
        }

        return $granted;
    }

    /**
     * @return array{level:int,prognobaks:float,rublius:float,chests:int}|null
     */
    private function grantForLevel(int $userId, int $level): ?array
    {
        $amounts = GameEconomyConfig::getLevelUpReward($level);
        $alreadyGranted = $this->repository->hasWalletTx(
            $userId,
            'level_up_reward',
            'level',
            $level
        );

        if (!$alreadyGranted) {
            if ($amounts['prognobaks'] > 0) {
                $this->walletService->credit(
                    $userId,
                    GameEconomyConfig::CURRENCY_PROGNOBAKS,
                    $amounts['prognobaks'],
                    'level_up_reward',
                    'level',
                    $level
                );
            }

            if ($amounts['rublius'] > 0) {
                $this->walletService->credit(
                    $userId,
                    GameEconomyConfig::CURRENCY_RUBLIUS,
                    $amounts['rublius'],
                    'level_up_reward',
                    'level',
                    $level
                );
            }
        }

        $chestGranted = $this->treasureService->grantLevelUpChest($userId, $level);
        $certsGranted = $this->grantLevelUpCerts($userId, $level);

        if ($alreadyGranted && !$chestGranted && $certsGranted === []) {
            return null;
        }

        return [
            'level' => $level,
            'prognobaks' => $alreadyGranted ? 0.0 : $amounts['prognobaks'],
            'rublius' => $alreadyGranted ? 0.0 : $amounts['rublius'],
            'chests' => $chestGranted ? 1 : 0,
            'chest_type' => $chestGranted ? TreasureService::CHEST_TYPE_LEVEL : null,
            'certs' => $certsGranted,
        ];
    }

    /**
     * @return string[] выданные коды cert_profession | cert_estate
     */
    private function grantLevelUpCerts(int $userId, int $level): array
    {
        $granted = [];

        foreach (GameEconomyConfig::getLevelUpCertCodes($level) as $certCode) {
            if ($this->grantLevelUpCert($userId, $certCode, $level)) {
                $granted[] = $certCode;
            }
        }

        return $granted;
    }

    private function grantLevelUpCert(int $userId, string $certCode, int $level): bool
    {
        if ($this->repository->hasLevelUpCertGrant($userId, $certCode, $level)) {
            return false;
        }

        $this->repository->incrementLootItem(
            $userId,
            ChestLootConfig::LOOT_EVENT_GLOBAL,
            $certCode,
            ChestLootConfig::CATEGORY_CERT,
            1
        );
        $this->repository->markLevelUpCertGrant($userId, $certCode, $level);
        GameProfileService::invalidateSummaryCache($userId);

        return true;
    }
}
