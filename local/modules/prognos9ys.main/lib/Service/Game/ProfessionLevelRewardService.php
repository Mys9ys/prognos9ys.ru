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
     * @return array{
     *   user_id:int,
     *   scanned_levels:int,
     *   granted_chests:int,
     *   lines:array<int,string>
     * }
     */
    public function backfillLevelChestsForUser(int $userId): array
    {
        $result = [
            'user_id' => $userId,
            'scanned_levels' => 0,
            'granted_chests' => 0,
            'lines' => [],
        ];
        if ($userId <= 0) {
            return $result;
        }

        $professions = $this->professionRepository->getProfessionsByUserId($userId);
        foreach ($professions as $row) {
            $professionCode = trim((string)($row['UF_PROFESSION_CODE'] ?? ''));
            $level = max(0, (int)($row['UF_LEVEL'] ?? 0));
            if ($professionCode === '' || $level <= 0) {
                continue;
            }

            for ($i = 1; $i <= $level; $i++) {
                $config = ProfessionEconomyConfig::getProfessionLevelReward($i);
                $count = (int)($config['chests'] ?? 0);
                if ($count <= 0) {
                    continue;
                }
                $result['scanned_levels']++;
                if ($this->treasureService->grantProfessionLevelChest($userId, $professionCode, $i)) {
                    $result['granted_chests'] += $count;
                    $result['lines'][] = $professionCode . ' lvl ' . $i . ' +' . $count;
                }
            }
        }

        return $result;
    }

    /**
     * @return array{
     *   users_scanned:int,
     *   granted_chests:int,
     *   granted_rows:int,
     *   details:array<int,array<string,mixed>>
     * }
     */
    public function backfillLevelChestsForAll(int $limitUsers = 0): array
    {
        $userIds = $this->professionRepository->getDistinctProfessionUserIds();
        if ($limitUsers > 0) {
            $userIds = array_slice($userIds, 0, $limitUsers);
        }

        $summary = [
            'users_scanned' => count($userIds),
            'granted_chests' => 0,
            'granted_rows' => 0,
            'details' => [],
        ];

        foreach ($userIds as $userId) {
            $row = $this->backfillLevelChestsForUser((int)$userId);
            if ((int)($row['granted_chests'] ?? 0) <= 0) {
                continue;
            }
            $summary['granted_chests'] += (int)$row['granted_chests'];
            $summary['granted_rows'] += 1;
            $summary['details'][] = $row;
        }

        return $summary;
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
            'chest_type' => TreasureService::resolveProfessionChestTypeByLevel($level),
            'title' => $config['title'] ?? null,
        ];
    }
}
