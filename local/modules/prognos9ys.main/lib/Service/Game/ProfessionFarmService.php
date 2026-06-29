<?php

namespace Prognos9ys\Main\Service\Game;

use Bitrix\Main\Type\DateTime;
use Prognos9ys\Main\Model\Repository\GameEconomyRepository;
use Prognos9ys\Main\Model\Repository\ProfessionRepository;

class ProfessionFarmService
{
    private ProfessionRepository $repository;
    private WalletService $walletService;
    private TreasuryService $treasuryService;
    private GameEconomyRepository $economyRepository;
    private LevelService $levelService;
    private ProfessionLevelRewardService $levelRewardService;

    public function __construct(
        ?ProfessionRepository $repository = null,
        ?WalletService $walletService = null,
        ?GameEconomyRepository $economyRepository = null,
        ?LevelService $levelService = null,
        ?ProfessionLevelRewardService $levelRewardService = null,
        ?TreasuryService $treasuryService = null
    ) {
        $this->repository = $repository ?? new ProfessionRepository();
        $this->economyRepository = $economyRepository ?? new GameEconomyRepository();
        $this->walletService = $walletService ?? new WalletService($this->economyRepository);
        $this->treasuryService = $treasuryService ?? new TreasuryService($this->economyRepository);
        $this->levelService = $levelService ?? new LevelService($this->economyRepository);
        $this->levelRewardService = $levelRewardService ?? new ProfessionLevelRewardService(
            $this->economyRepository,
            $this->repository
        );
    }

    public function getState(int $userId): array
    {
        $this->processDueTicks($userId);

        return [
            'professions' => $this->formatProfessions($userId),
            'catalog' => $this->formatCatalog(),
            'materials' => $this->formatMaterials($userId),
            'gov_materials' => $this->formatGovMaterials(),
            'session' => $this->formatSession($this->repository->getActiveSessionByUserId($userId)),
            'last_shift' => $this->formatLastShift($userId),
            'slots' => $this->getSlotInfo($userId),
            'player_level' => $this->getPlayerLevel($userId),
            'economy' => [
                'pay_treasury' => ProfessionEconomyConfig::PAY_TREASURY_PER_ITERATION,
                'fee_self' => ProfessionEconomyConfig::FEE_SELF_PER_ITERATION,
                'iteration_minutes' => ProfessionEconomyConfig::ITERATION_MINUTES,
                'max_iterations' => ProfessionEconomyConfig::FREE_ITERATIONS_PER_SESSION,
                'profession_level_cap' => $this->getPlayerLevel($userId),
            ],
        ];
    }

    /**
     * @param string[] $codes
     */
    public function pickProfessions(int $userId, array $codes): array
    {
        $existing = $this->repository->getProfessionsByUserId($userId);
        $used = count($existing);
        $playerLevel = $this->getPlayerLevel($userId);
        $certBonus = $this->getCertificateBonus($userId);
        $max = ProfessionMaterialConfig::maxProfessionSlots($playerLevel, $certBonus);
        $available = $max - $used;

        if ($available <= 0) {
            throw new \RuntimeException('Нет свободных слотов профессий');
        }

        $codes = array_values(array_unique(array_filter(array_map('strval', $codes))));
        $pickCount = count($codes);

        if ($pickCount < ProfessionMaterialConfig::MIN_STARTER_PROFESSION_SLOTS) {
            throw new \InvalidArgumentException('Выберите хотя бы одну профессию');
        }

        if ($pickCount > $available) {
            throw new \InvalidArgumentException('Слишком много профессий для свободных слотов');
        }

        if ($used === 0) {
            $maxInitial = min(ProfessionMaterialConfig::STARTER_PROFESSION_SLOTS, $available);
            if ($pickCount > $maxInitial) {
                throw new \InvalidArgumentException(
                    'При первом выборе можно взять не более ' . $maxInitial . ' профессий'
                );
            }
        }

        $ownedCodes = [];
        foreach ($existing as $row) {
            $ownedCodes[] = (string)($row['UF_PROFESSION_CODE'] ?? '');
        }

        $gathering = ProfessionMaterialConfig::allProfessions();
        foreach ($codes as $code) {
            if (in_array($code, $ownedCodes, true)) {
                throw new \InvalidArgumentException('Профессия уже изучена: ' . $code);
            }
            if (!isset($gathering[$code])) {
                throw new \InvalidArgumentException('Неизвестная профессия: ' . $code);
            }
        }

        foreach ($codes as $index => $code) {
            $this->repository->addUserProfession($userId, $code, $used + $index);
        }

        return $this->getState($userId);
    }

    /**
     * @param string[] $codes
     * @deprecated use pickProfessions()
     */
    public function pickStarterProfessions(int $userId, array $codes): array
    {
        return $this->pickProfessions($userId, $codes);
    }

    public function startWork(int $userId, string $professionCode, string $workMode, int $iterations = 0): array
    {
        $profession = $this->repository->getProfessionByUserAndCode($userId, $professionCode);
        if (!$profession) {
            throw new \RuntimeException('Профессия не изучена');
        }

        $definition = ProfessionMaterialConfig::getProfession($professionCode);
        if (!$definition) {
            throw new \RuntimeException('Неизвестная профессия');
        }

        if (!in_array($workMode, [ProfessionMaterialConfig::WORK_MODE_SELF, ProfessionMaterialConfig::WORK_MODE_TREASURY], true)) {
            throw new \InvalidArgumentException('Некорректный режим работы');
        }

        if ($this->repository->getActiveSessionByUserId($userId)) {
            throw new \RuntimeException('Уже есть активная смена');
        }

        $iterations = $iterations > 0
            ? min(ProfessionEconomyConfig::FREE_ITERATIONS_PER_SESSION, $iterations)
            : ProfessionEconomyConfig::FREE_ITERATIONS_PER_SESSION;

        $iterations = $this->resolveIterationsForMaterials($userId, $professionCode, $workMode, $iterations);
        if ($iterations <= 0) {
            throw new \RuntimeException('Недостаточно сырья для начала смены');
        }

        $now = new DateTime();
        $shiftMinutes = $iterations * ProfessionEconomyConfig::ITERATION_MINUTES;
        $nextTick = (clone $now)->add('+' . $shiftMinutes . ' minutes');

        $this->repository->addProfessionSession([
            'UF_USER_ID' => $userId,
            'UF_PROFESSION_CODE' => $professionCode,
            'UF_WORK_MODE' => $workMode,
            'UF_STATUS' => ProfessionMaterialConfig::SESSION_STATUS_ACTIVE,
            'UF_ITERATIONS_DONE' => 0,
            'UF_ITERATIONS_TOTAL' => $iterations,
            'UF_NEXT_TICK_AT' => $nextTick,
            'UF_STARTED_AT' => $now,
            'UF_UPDATED_AT' => $now,
            'UF_LAST_RESULT_JSON' => '',
        ]);

        return $this->getState($userId);
    }

    /**
     * Досрочно прогоняет все циклы активной смены (для seed-ботов / модераторских массовых действий).
     */
    public function forceRunAllSessionTicks(int $userId): int
    {
        $session = $this->repository->getActiveSessionByUserId($userId);
        if (!$session) {
            return 0;
        }

        $this->repository->updateProfessionSession((int)$session['ID'], [
            'UF_NEXT_TICK_AT' => new DateTime(),
        ]);
        $this->processDueTicks($userId);

        $sessionAfter = $this->repository->getProfessionSessionById((int)$session['ID']);

        return (int)($sessionAfter['UF_ITERATIONS_DONE'] ?? 0);
    }

    public function cancelWork(int $userId): array
    {
        $session = $this->repository->getActiveSessionByUserId($userId);
        if (!$session) {
            throw new \RuntimeException('Нет активной смены');
        }

        $this->repository->updateProfessionSession((int)$session['ID'], [
            'UF_STATUS' => ProfessionMaterialConfig::SESSION_STATUS_CANCELLED,
            'UF_UPDATED_AT' => new DateTime(),
        ]);

        return $this->getState($userId);
    }

    private function processDueTicks(int $userId): void
    {
        $session = $this->repository->getActiveSessionByUserId($userId);
        if (!$session) {
            return;
        }

        $now = time();
        $guard = 0;

        while ($guard < ProfessionEconomyConfig::FREE_ITERATIONS_PER_SESSION) {
            $guard++;
            $session = $this->repository->getActiveSessionByUserId($userId);
            if (!$session) {
                break;
            }

            $nextTick = $session['UF_NEXT_TICK_AT'] ?? null;
            if (!$nextTick instanceof DateTime) {
                break;
            }

            if ($nextTick->getTimestamp() > $now) {
                break;
            }

            $this->runSessionCompletion($userId, $session);
        }
    }

    /**
     * Завершение смены: все циклы за один расчёт (один журнал казны / кошелька).
     *
     * @param array<string, mixed> $session
     */
    private function runSessionCompletion(int $userId, array $session): void
    {
        $sessionId = (int)$session['ID'];
        $reserved = $this->repository->tryReserveDueSessionTick($sessionId);
        if (!$reserved) {
            return;
        }

        $session = $reserved;
        $professionCode = (string)($session['UF_PROFESSION_CODE'] ?? '');
        $workMode = (string)($session['UF_WORK_MODE'] ?? '');
        $iterations = (int)($session['UF_ITERATIONS_TOTAL'] ?? 0);
        $definition = ProfessionMaterialConfig::getProfession($professionCode);
        $professionRow = $this->repository->getProfessionByUserAndCode($userId, $professionCode);

        if (!$definition || !$professionRow || $iterations <= 0) {
            $this->repository->updateProfessionSession($sessionId, [
                'UF_STATUS' => ProfessionMaterialConfig::SESSION_STATUS_CANCELLED,
                'UF_UPDATED_AT' => new DateTime(),
            ]);

            return;
        }

        $playerLevel = $this->getPlayerLevel($userId);
        $level = min((int)($professionRow['UF_LEVEL'] ?? 0), $playerLevel);
        $comboLevel = max(1, $level);
        $outputCode = $definition['output'];
        $premiumCode = $definition['premium'];
        $isProcessing = ProfessionMaterialConfig::isProcessingProfession($definition);
        $inputCode = ProfessionMaterialConfig::getProfessionInput($professionCode);
        $inputLabel = $isProcessing ? (string)($definition['input_label'] ?? '') : '';

        $totalComboYield = 0;
        $totalGovOutput = 0;
        $totalUserBonusOutput = 0;
        $totalUserSelfOutput = 0;
        $totalPremiumQty = 0;
        $totalXpGain = 0;

        for ($i = 0; $i < $iterations; $i++) {
            $combo = $this->rollComboMultiplier($comboLevel);
            $premiumQty = $this->rollPremiumDrop($comboLevel) ? 1 : 0;
            $totalComboYield += $combo;
            $totalPremiumQty += $premiumQty;
            $totalXpGain += $combo * ProfessionEconomyConfig::XP_PER_NORMAL_UNIT
                + $premiumQty * ProfessionEconomyConfig::XP_PER_PREMIUM_UNIT;

            if ($workMode === ProfessionMaterialConfig::WORK_MODE_TREASURY) {
                $totalGovOutput += 1;
                $totalUserBonusOutput += max(0, $combo - 1);
            } else {
                $totalUserSelfOutput += $combo;
            }
        }

        $payCoins = 0.0;
        $feeCoins = 0.0;
        $message = '';
        $inputConsumed = 0;
        $isLabor = in_array($workMode, [
            ProfessionMaterialConfig::WORK_MODE_LABOR,
            ProfessionMaterialConfig::WORK_MODE_LABOR_POSTER,
        ], true);

        try {
            if ($isLabor) {
                $laborResult = (new LaborExchangeService(
                    null,
                    $this->repository,
                    $this->walletService,
                    $this->treasuryService
                ))->applySessionCompletion(
                    $userId,
                    $session,
                    $iterations,
                    $totalComboYield,
                    $totalPremiumQty,
                    $outputCode,
                    $premiumCode,
                    $inputCode ?? '',
                    $isProcessing
                );
                $message = (string)($laborResult['message'] ?? '');
                $payCoins = (float)($laborResult['pay_coins'] ?? 0);
                $feeCoins = (float)($laborResult['fee_coins'] ?? 0);
                $inputConsumed = $isProcessing && $inputCode ? $iterations : 0;
            } elseif ($workMode === ProfessionMaterialConfig::WORK_MODE_SELF) {
                $feeCoins = $iterations * ProfessionEconomyConfig::FEE_SELF_PER_ITERATION;
                $wallet = $this->walletService->ensureWallet($userId);
                if ((float)($wallet['prognobaks'] ?? 0) < $feeCoins) {
                    throw new \RuntimeException('Недостаточно 🪙 для оплаты мастерской после смены');
                }
            } else {
                $payCoins = $iterations * ProfessionEconomyConfig::PAY_TREASURY_PER_ITERATION;
                if (!$this->treasuryService->hasFunds(GameEconomyConfig::CURRENCY_PROGNOBAKS, $payCoins)) {
                    throw new \RuntimeException('В казне недостаточно средств для оплаты смены');
                }
            }

            if (!$isLabor && $isProcessing && $inputCode) {
                $inputConsumed = $iterations;
                if ($workMode === ProfessionMaterialConfig::WORK_MODE_TREASURY) {
                    $this->repository->consumeGovWarehouseQty($inputCode, $inputConsumed);
                } else {
                    $this->repository->consumeUserMaterialQty($userId, $inputCode, $inputConsumed, false);
                }
            }

            if (!$isLabor && $workMode === ProfessionMaterialConfig::WORK_MODE_TREASURY) {
                if ($totalGovOutput > 0) {
                    $this->repository->addGovWarehouseQty($outputCode, $totalGovOutput);
                }
                if ($totalUserBonusOutput > 0) {
                    $this->repository->addUserMaterialQty($userId, $outputCode, $totalUserBonusOutput, false);
                }
                $this->treasuryService->debit(
                    GameEconomyConfig::CURRENCY_PROGNOBAKS,
                    $payCoins,
                    'profession_work_pay',
                    $sessionId,
                    $userId,
                    'profession_session'
                );
                $this->walletService->credit(
                    $userId,
                    GameEconomyConfig::CURRENCY_PROGNOBAKS,
                    $payCoins,
                    'profession_work_pay',
                    'profession_session',
                    $sessionId
                );
                if ($totalPremiumQty > 0) {
                    $this->repository->addUserMaterialQty($userId, $premiumCode, $totalPremiumQty, true);
                }
                $message = '+' . $payCoins . ' 🪙, ' . $totalGovOutput . ' '
                    . $definition['output_label'] . ($isProcessing ? ' на госсклад' : ' на склад');
                if ($inputConsumed > 0) {
                    $message .= ', −' . $inputConsumed . ' ' . $inputLabel;
                }
                if ($totalUserBonusOutput > 0) {
                    $message .= ', +' . $totalUserBonusOutput . ' ' . $definition['output_label'] . ' вам (комбо)';
                }
                if ($totalPremiumQty > 0) {
                    $message .= ', +' . $totalPremiumQty . ' ' . $definition['premium_label'];
                }
            } elseif (!$isLabor) {
                if ($totalUserSelfOutput > 0) {
                    $this->repository->addUserMaterialQty($userId, $outputCode, $totalUserSelfOutput, false);
                }
                if ($totalPremiumQty > 0) {
                    $this->repository->addUserMaterialQty($userId, $premiumCode, $totalPremiumQty, true);
                }
                $this->walletService->debit(
                    $userId,
                    GameEconomyConfig::CURRENCY_PROGNOBAKS,
                    $feeCoins,
                    'profession_work_fee',
                    'profession_session',
                    $sessionId
                );
                $this->treasuryService->credit(
                    GameEconomyConfig::CURRENCY_PROGNOBAKS,
                    $feeCoins,
                    'profession_work_fee',
                    $sessionId,
                    $userId,
                    'profession_session'
                );
                $message = $totalUserSelfOutput . ' ' . $definition['output_label'] . ' в инвентарь';
                if ($inputConsumed > 0) {
                    $message .= ', −' . $inputConsumed . ' ' . $inputLabel;
                }
                if ($feeCoins > 0) {
                    $message .= ', −' . $feeCoins . ' 🪙 (мастерская)';
                }
                if ($totalPremiumQty > 0) {
                    $message .= ', +' . $totalPremiumQty . ' ' . $definition['premium_label'];
                }
            }
        } catch (\Throwable $exception) {
            $this->repository->updateProfessionSession($sessionId, [
                'UF_STATUS' => ProfessionMaterialConfig::SESSION_STATUS_CANCELLED,
                'UF_UPDATED_AT' => new DateTime(),
                'UF_LAST_RESULT_JSON' => json_encode([
                    'error' => $exception->getMessage(),
                ], JSON_UNESCAPED_UNICODE),
            ]);

            return;
        }

        $professionRowId = (int)$professionRow['ID'];
        $this->repository->incrementYield($professionRowId, $totalComboYield, $totalPremiumQty);

        $xpResult = $this->repository->addProfessionXp(
            $professionRowId,
            $totalXpGain,
            $playerLevel,
            $professionCode
        );

        $levelRewards = [];
        if ($xpResult && $xpResult['new_level'] > $xpResult['old_level']) {
            $levelRewards = $this->levelRewardService->grantForLevelRange(
                $userId,
                $professionCode,
                $xpResult['old_level'],
                $xpResult['new_level']
            );
        }

        $resultJson = json_encode([
            'iterations' => $iterations,
            'output_code' => $outputCode,
            'input_code' => $inputCode ?? '',
            'input_consumed' => $inputConsumed,
            'gov_output_qty' => $totalGovOutput,
            'user_output_qty' => $workMode === ProfessionMaterialConfig::WORK_MODE_TREASURY
                ? $totalUserBonusOutput
                : $totalUserSelfOutput,
            'premium_code' => $totalPremiumQty > 0 ? $premiumCode : '',
            'premium_qty' => $totalPremiumQty,
            'pay_coins' => $payCoins,
            'fee_coins' => $feeCoins,
            'xp_gain' => $totalXpGain,
            'profession_level_rewards' => $levelRewards,
            'message' => $message,
        ], JSON_UNESCAPED_UNICODE);

        $this->repository->updateProfessionSession($sessionId, [
            'UF_LAST_RESULT_JSON' => $resultJson,
            'UF_UPDATED_AT' => new DateTime(),
        ]);
    }

    private function rollComboMultiplier(int $level): int
    {
        $p3 = ProfessionEconomyConfig::comboTripleChance($level);
        $p2 = ProfessionEconomyConfig::comboDoubleChance($level);
        $roll = mt_rand(0, 1000000) / 1000000;

        if ($roll < $p3) {
            return 3;
        }

        $roll2 = mt_rand(0, 1000000) / 1000000;
        if ($roll2 < $p2) {
            return 2;
        }

        return 1;
    }

    private function rollPremiumDrop(int $level): bool
    {
        $chance = ProfessionEconomyConfig::premiumDropChance($level);
        $roll = mt_rand(0, 1000000) / 1000000;

        return $roll < $chance;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function formatProfessions(int $userId): array
    {
        $playerLevel = $this->getPlayerLevel($userId);
        $items = [];
        foreach ($this->repository->getProfessionsByUserId($userId) as $row) {
            $code = (string)($row['UF_PROFESSION_CODE'] ?? '');
            $definition = ProfessionMaterialConfig::getProfession($code);
            if (!$definition) {
                continue;
            }

            $storedLevel = (int)($row['UF_LEVEL'] ?? 0);
            $effectiveLevel = min($storedLevel, $playerLevel);
            $xp = (float)($row['UF_XP'] ?? 0);
            $progress = $this->levelService->getProgressSummary($xp);
            $maxXp = $this->repository->maxXpForProfessionLevel($playerLevel);
            $chanceLevel = max(1, $effectiveLevel);
            $chances = ProfessionEconomyConfig::chancesForLevel($chanceLevel);

            $items[] = [
                'code' => $code,
                'label' => $definition['label'],
                'type' => (string)($definition['type'] ?? 'gather'),
                'input' => (string)($definition['input'] ?? ''),
                'input_label' => (string)($definition['input_label'] ?? ''),
                'level' => $effectiveLevel,
                'stored_level' => $storedLevel,
                'level_cap' => $playerLevel,
                'is_capped' => $xp >= $maxXp,
                'xp' => round($xp, 1),
                'xp_to_next' => $progress['xp_to_next'],
                'xp_in_level' => round(max(0, $xp - (float)$progress['current_min_xp']), 1),
                'xp_level_total' => $progress['next_min_xp'] !== null
                    ? (float)($progress['next_min_xp'] - $progress['current_min_xp'])
                    : null,
                'next_profession_level' => $progress['next_level'],
                'progress_percent' => $progress['progress_percent'],
                'normal_yield' => (int)($row['UF_NORMAL_YIELD'] ?? 0),
                'premium_yield' => (int)($row['UF_PREMIUM_YIELD'] ?? 0),
                'slot' => (int)($row['UF_SLOT_INDEX'] ?? 0),
                'output' => $definition['output'],
                'output_label' => $definition['output_label'],
                'output_emoji' => ProfessionMaterialConfig::materialEmoji($definition['output']),
                'premium' => $definition['premium'],
                'premium_label' => $definition['premium_label'],
                'premium_emoji' => ProfessionMaterialConfig::materialEmoji($definition['premium']),
                'combo_x2_percent' => $chances['combo_x2'],
                'combo_x3_percent' => $chances['combo_x3'],
                'premium_percent' => $chances['premium'],
                'has_premium_drop' => true,
                'premium_min_level' => ProfessionEconomyConfig::PREMIUM_DROP_MIN_LEVEL,
            ];
        }

        return $items;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function formatCatalog(): array
    {
        $items = [];
        foreach (ProfessionMaterialConfig::allProfessions() as $definition) {
            $items[] = [
                'code' => $definition['code'],
                'label' => $definition['label'],
                'type' => (string)($definition['type'] ?? 'gather'),
                'output' => $definition['output'],
                'output_label' => $definition['output_label'],
                'premium' => $definition['premium'],
                'premium_label' => $definition['premium_label'],
                'input' => (string)($definition['input'] ?? ''),
                'input_label' => (string)($definition['input_label'] ?? ''),
                'output_emoji' => ProfessionMaterialConfig::materialEmoji($definition['output']),
                'premium_emoji' => ProfessionMaterialConfig::materialEmoji($definition['premium']),
            ];
        }

        return $items;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function formatGovMaterials(): array
    {
        $qtyMap = $this->repository->getGovWarehouseQtyMap();
        $catalog = ProfessionMaterialConfig::materialCatalog();
        $items = [];

        foreach ($qtyMap as $code => $qty) {
            if ($qty <= 0) {
                continue;
            }

            $meta = $catalog[$code] ?? null;
            if (!$meta) {
                continue;
            }

            $items[] = [
                'code' => $code,
                'label' => $meta['label'] ?? $code,
                'qty' => $qty,
                'nominal' => $meta['nominal'] ?? 0,
                'emoji' => $meta['emoji'] ?? ProfessionMaterialConfig::materialEmoji($code),
            ];
        }

        return $items;
    }

    private function resolveIterationsForMaterials(
        int $userId,
        string $professionCode,
        string $workMode,
        int $requestedIterations
    ): int {
        $inputCode = ProfessionMaterialConfig::getProfessionInput($professionCode);
        if (!$inputCode) {
            return $requestedIterations;
        }

        $available = $workMode === ProfessionMaterialConfig::WORK_MODE_TREASURY
            ? $this->repository->getGovWarehouseQty($inputCode)
            : $this->repository->getUserMaterialQty($userId, $inputCode, false);

        if ($available <= 0) {
            return 0;
        }

        return min($requestedIterations, $available);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function formatMaterials(int $userId): array
    {
        $catalog = ProfessionMaterialConfig::materialCatalog();
        $items = [];

        foreach ($this->repository->getMaterialsByUserId($userId) as $row) {
            $code = (string)($row['UF_MATERIAL_CODE'] ?? '');
            $qty = (int)($row['UF_QTY'] ?? 0);
            if ($qty <= 0) {
                continue;
            }

            $meta = $catalog[$code] ?? null;
            $items[] = [
                'code' => $code,
                'label' => $meta['label'] ?? $code,
                'qty' => $qty,
                'is_premium' => ($row['UF_IS_PREMIUM'] ?? '') === 'Y',
                'nominal' => $meta['nominal'] ?? 0,
                'emoji' => $meta['emoji'] ?? ProfessionMaterialConfig::materialEmoji($code),
            ];
        }

        return $items;
    }

    /**
     * @param array<string, mixed>|null $session
     * @return array<string, mixed>|null
     */
    private function formatSession(?array $session): ?array
    {
        if (!$session) {
            return null;
        }

        $definition = ProfessionMaterialConfig::getProfession((string)($session['UF_PROFESSION_CODE'] ?? ''));
        $nextTick = $session['UF_NEXT_TICK_AT'] ?? null;
        $secondsLeft = 0;

        if ($nextTick instanceof DateTime) {
            $secondsLeft = max(0, $nextTick->getTimestamp() - time());
        }

        $lastResult = [];
        $json = (string)($session['UF_LAST_RESULT_JSON'] ?? '');
        if ($json !== '') {
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                $lastResult = $decoded;
            }
        }

        return [
            'session_id' => (int)($session['ID'] ?? 0),
            'profession_code' => (string)($session['UF_PROFESSION_CODE'] ?? ''),
            'profession_label' => $definition['label'] ?? '',
            'work_mode' => (string)($session['UF_WORK_MODE'] ?? ''),
            'labor_order_id' => (int)($session['UF_LABOR_ORDER_ID'] ?? 0),
            'status' => (string)($session['UF_STATUS'] ?? ''),
            'iterations_done' => (int)($session['UF_ITERATIONS_DONE'] ?? 0),
            'iterations_total' => (int)($session['UF_ITERATIONS_TOTAL'] ?? 0),
            'shift_minutes' => (int)($session['UF_ITERATIONS_TOTAL'] ?? 0) * ProfessionEconomyConfig::ITERATION_MINUTES,
            'seconds_left' => $secondsLeft,
            'next_tick_at' => $nextTick instanceof DateTime ? $nextTick->getTimestamp() : 0,
            'last_result' => $lastResult,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function formatLastShift(int $userId): ?array
    {
        $session = $this->repository->getLastSessionByUserId($userId);
        if (!$session) {
            return null;
        }

        if ((string)($session['UF_STATUS'] ?? '') !== ProfessionMaterialConfig::SESSION_STATUS_COMPLETED) {
            return null;
        }

        return $this->formatSession($session);
    }

    /**
     * @return array{
     *   max:int,
     *   pick_min:int,
     *   pick_max:int,
     *   pick_count:int,
     *   used:int,
     *   available:int,
     *   needs_pick:bool,
     *   can_add_profession:bool
     * }
     */
    private function getSlotInfo(int $userId): array
    {
        $professions = $this->repository->getProfessionsByUserId($userId);
        $used = count($professions);
        $playerLevel = $this->getPlayerLevel($userId);
        $certBonus = $this->getCertificateBonus($userId);
        $max = ProfessionMaterialConfig::maxProfessionSlots($playerLevel, $certBonus);
        $available = max(0, $max - $used);
        $maxInitialPick = min(ProfessionMaterialConfig::STARTER_PROFESSION_SLOTS, $available);
        $pickMax = $used === 0 ? $maxInitialPick : $available;

        return [
            'max' => $max,
            'pick_min' => $used === 0
                ? ProfessionMaterialConfig::MIN_STARTER_PROFESSION_SLOTS
                : 1,
            'pick_max' => $pickMax,
            'pick_count' => ProfessionMaterialConfig::STARTER_PROFESSION_SLOTS,
            'used' => $used,
            'available' => $available,
            'needs_pick' => $used === 0,
            'can_add_profession' => $used > 0 && $available > 0,
            'slots_full' => $used >= $max,
            'certificate_bonus' => $certBonus,
        ];
    }

    public function getCertificateBonus(int $userId): int
    {
        return $this->economyRepository->getProfessionCertSlots($userId);
    }

    private function getPlayerLevel(int $userId): int
    {
        $progress = $this->economyRepository->getProgressByUserId($userId);
        $xp = (float)($progress['UF_XP'] ?? 0);

        return $this->levelService->getLevelFromXp($xp);
    }
}
