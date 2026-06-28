<?php

namespace Prognos9ys\Main\Service\Game;

use Prognos9ys\Main\Model\Repository\GameEconomyRepository;
use Prognos9ys\Main\Model\Repository\ProfessionRepository;

class ProfessionLevelRewardService
{
    private GameEconomyRepository $repository;
    private WalletService $walletService;
    private TreasureService $treasureService;
    private ProfessionRepository $professionRepository;

    public function __construct(
        ?GameEconomyRepository $repository = null,
        ?ProfessionRepository $professionRepository = null
    ) {
        $this->repository = $repository ?? new GameEconomyRepository();
        $this->walletService = new WalletService($this->repository);
        $this->treasureService = new TreasureService($this->repository);
        $this->professionRepository = $professionRepository ?? new ProfessionRepository();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function grantForLevelRange(
        int $userId,
        string $professionCode,
        int $oldLevel,
        int $newLevel
    ): array {
        if ($userId <= 0 || $professionCode === '' || $newLevel <= $oldLevel) {
            return [];
        }

        $granted = [];
        for ($level = $oldLevel + 1; $level <= $newLevel; $level++) {
            $reward = $this->grantForLevel($userId, $professionCode, $level);
            if ($reward) {
                $granted[] = $reward;
            }
        }

        return $granted;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function grantForLevel(int $userId, string $professionCode, int $level): ?array
    {
        if ($level <= 0) {
            return null;
        }

        $config = ProfessionEconomyConfig::getProfessionLevelReward($level);
        $definition = ProfessionMaterialConfig::getProfession($professionCode);
        if (!$definition) {
            return null;
        }

        $alreadyGranted = $this->repository->hasWalletTx(
            $userId,
            'profession_level_up_reward',
            'profession:' . $professionCode,
            $level
        );

        $prognobaks = 0.0;
        $rublius = 0.0;

        if (!$alreadyGranted) {
            if ($config['prognobaks'] > 0) {
                $this->walletService->credit(
                    $userId,
                    GameEconomyConfig::CURRENCY_PROGNOBAKS,
                    $config['prognobaks'],
                    'profession_level_up_reward',
                    'profession:' . $professionCode,
                    $level
                );
                $prognobaks = $config['prognobaks'];
            }

            if ($config['rublius'] > 0) {
                $this->walletService->credit(
                    $userId,
                    GameEconomyConfig::CURRENCY_RUBLIUS,
                    $config['rublius'],
                    'profession_level_up_reward',
                    'profession:' . $professionCode,
                    $level
                );
                $rublius = $config['rublius'];
            }

            if ($config['material_qty'] > 0) {
                $this->professionRepository->addUserMaterialQty(
                    $userId,
                    (string)$definition['output'],
                    $config['material_qty'],
                    false
                );
            }
        }

        $chestGranted = false;
        if ($config['chests'] > 0) {
            $chestGranted = $this->treasureService->grantProfessionLevelChest(
                $userId,
                $professionCode,
                $level
            );
        }

        if ($alreadyGranted && !$chestGranted && $config['material_qty'] <= 0) {
            return null;
        }

        return [
            'profession_code' => $professionCode,
            'profession_label' => $definition['label'],
            'level' => $level,
            'prognobaks' => $alreadyGranted ? 0.0 : $prognobaks,
            'rublius' => $alreadyGranted ? 0.0 : $rublius,
            'material_code' => $definition['output'],
            'material_label' => $definition['output_label'],
            'material_qty' => $alreadyGranted ? 0 : $config['material_qty'],
            'chests' => $chestGranted ? $config['chests'] : 0,
            'chest_type' => TreasureService::CHEST_TYPE_PROFESSION,
            'title' => $config['title'] ?? null,
        ];
    }
}
