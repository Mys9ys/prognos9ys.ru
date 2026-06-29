<?php

namespace Prognos9ys\Main\Service\Game;

use Bitrix\Main\Type\DateTime;
use Prognos9ys\Main\Model\Repository\LaborOrderRepository;
use Prognos9ys\Main\Model\Repository\ProfessionRepository;

class LaborExchangeService
{
    private LaborOrderRepository $orderRepository;
    private ProfessionRepository $professionRepository;
    private WalletService $walletService;
    private TreasuryService $treasuryService;

    public function __construct(
        ?LaborOrderRepository $orderRepository = null,
        ?ProfessionRepository $professionRepository = null,
        ?WalletService $walletService = null,
        ?TreasuryService $treasuryService = null
    ) {
        $this->orderRepository = $orderRepository ?? new LaborOrderRepository();
        $this->professionRepository = $professionRepository ?? new ProfessionRepository();
        $this->walletService = $walletService ?? new WalletService();
        $this->treasuryService = $treasuryService ?? new TreasuryService();
    }

    public function getLaborMeta(): array
    {
        return [
            'max_cycles_per_claim' => LaborExchangeConfig::MAX_CYCLES_PER_CLAIM,
            'iteration_minutes' => ProfessionEconomyConfig::ITERATION_MINUTES,
            'default_pay_per_cycle' => LaborExchangeConfig::DEFAULT_PAY_PER_CYCLE,
            'min_pay_per_cycle' => LaborExchangeConfig::MIN_PAY_PER_CYCLE,
            'fee_poster_workshop' => ProfessionEconomyConfig::FEE_SELF_PER_ITERATION,
        ];
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, pagination: array<string, int|bool>}
     */
    public function getOpenOrders(int $userId, int $offset = 0, int $limit = 25): array
    {
        $limit = max(1, min(50, $limit));
        $offset = max(0, $offset);
        $rows = $this->orderRepository->getOpenOrders($limit + 1, $offset);
        $hasMore = count($rows) > $limit;
        if ($hasMore) {
            array_pop($rows);
        }

        $rows = array_values(array_filter($rows, function (array $row) use ($userId): bool {
            if ($this->isTreasuryOrder($row)) {
                return true;
            }

            return (int)($row['UF_POSTER_USER_ID'] ?? 0) !== $userId;
        }));

        $professionCodes = $this->getUserProfessionCodes($userId);

        return [
            'items' => array_map(
                fn (array $row) => $this->formatOrder($row, $userId, $professionCodes),
                $rows
            ),
            'pagination' => [
                'offset' => $offset,
                'limit' => $limit,
                'has_more' => $hasMore,
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getMyOrders(int $userId): array
    {
        $professionCodes = $this->getUserProfessionCodes($userId);
        $items = [];

        foreach ($this->orderRepository->getOrdersByPosterUserId($userId, 50) as $row) {
            $items[] = $this->formatOrder($row, $userId, $professionCodes);
        }

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    public function createOrder(int $userId, string $professionCode, int $iterations, float $payPerCycle): array
    {
        $definition = $this->resolveProfessionDefinition($professionCode);
        $iterations = max(LaborExchangeConfig::MIN_ITERATIONS, min(LaborExchangeConfig::MAX_ITERATIONS, $iterations));
        $payPerCycle = round($payPerCycle, 1);

        if ($payPerCycle < LaborExchangeConfig::MIN_PAY_PER_CYCLE) {
            throw new \InvalidArgumentException(
                'Оплата за цикл не меньше ' . LaborExchangeConfig::MIN_PAY_PER_CYCLE . ' 🪙'
            );
        }

        $inputCode = ProfessionMaterialConfig::getProfessionInput($professionCode) ?? '';
        $outputCode = (string)($definition['output'] ?? '');
        $coinEscrow = round($iterations * $payPerCycle, 1);

        $wallet = $this->walletService->ensureWallet($userId);
        if ((float)($wallet['prognobaks'] ?? 0) < $coinEscrow) {
            throw new \RuntimeException('Недостаточно 🪙 для оплаты труда');
        }

        if ($inputCode !== '') {
            $available = $this->professionRepository->getUserMaterialQty($userId, $inputCode, false);
            if ($available < $iterations) {
                throw new \RuntimeException('Недостаточно сырья для заказа');
            }
        }

        $now = new DateTime();
        $orderId = $this->orderRepository->addOrder([
            'UF_POSTER_USER_ID' => $userId,
            'UF_POSTER_KIND' => LaborExchangeConfig::POSTER_KIND_USER,
            'UF_PROFESSION_CODE' => $professionCode,
            'UF_OUTPUT_CODE' => $outputCode,
            'UF_INPUT_CODE' => $inputCode,
            'UF_ITERATIONS_TOTAL' => $iterations,
            'UF_ITERATIONS_DONE' => 0,
            'UF_INPUT_ESCROW_QTY' => $inputCode !== '' ? $iterations : 0,
            'UF_PAY_PER_CYCLE' => $payPerCycle,
            'UF_COIN_ESCROW' => $coinEscrow,
            'UF_STATUS' => LaborExchangeConfig::STATUS_OPEN,
            'UF_CREATED_AT' => $now,
            'UF_UPDATED_AT' => $now,
        ]);

        if ($inputCode !== '') {
            $this->professionRepository->consumeUserMaterialQty($userId, $inputCode, $iterations, false);
        }

        $this->walletService->debit(
            $userId,
            GameEconomyConfig::CURRENCY_PROGNOBAKS,
            $coinEscrow,
            'labor_order_escrow',
            'labor_order',
            $orderId
        );

        return $this->formatOrder($this->orderRepository->getOrderById($orderId) ?? [], $userId);
    }

    /**
     * Заказ от казны: сырьё с госсклада, оплата из казны, продукт на госсклад.
     *
     * @return array<string, mixed>
     */
    public function createTreasuryOrder(string $professionCode, int $iterations, float $payPerCycle): array
    {
        $definition = $this->resolveProfessionDefinition($professionCode);
        $iterations = max(LaborExchangeConfig::MIN_ITERATIONS, min(LaborExchangeConfig::MAX_ITERATIONS, $iterations));
        $payPerCycle = round($payPerCycle, 1);

        if ($payPerCycle < LaborExchangeConfig::MIN_PAY_PER_CYCLE) {
            throw new \InvalidArgumentException(
                'Оплата за цикл не меньше ' . LaborExchangeConfig::MIN_PAY_PER_CYCLE . ' 🪙'
            );
        }

        $inputCode = ProfessionMaterialConfig::getProfessionInput($professionCode) ?? '';
        $outputCode = (string)($definition['output'] ?? '');
        $coinEscrow = round($iterations * $payPerCycle, 1);

        if (!$this->treasuryService->hasFunds(GameEconomyConfig::CURRENCY_PROGNOBAKS, $coinEscrow)) {
            throw new \RuntimeException('В казне недостаточно 🪙 для оплаты труда');
        }

        if ($inputCode !== '') {
            $available = $this->professionRepository->getGovWarehouseQty($inputCode);
            if ($available < $iterations) {
                throw new \RuntimeException('На госскладе недостаточно сырья для заказа');
            }
        }

        $now = new DateTime();
        $orderId = $this->orderRepository->addOrder([
            'UF_POSTER_USER_ID' => 0,
            'UF_POSTER_KIND' => LaborExchangeConfig::POSTER_KIND_TREASURY,
            'UF_PROFESSION_CODE' => $professionCode,
            'UF_OUTPUT_CODE' => $outputCode,
            'UF_INPUT_CODE' => $inputCode,
            'UF_ITERATIONS_TOTAL' => $iterations,
            'UF_ITERATIONS_DONE' => 0,
            'UF_INPUT_ESCROW_QTY' => $inputCode !== '' ? $iterations : 0,
            'UF_PAY_PER_CYCLE' => $payPerCycle,
            'UF_COIN_ESCROW' => $coinEscrow,
            'UF_STATUS' => LaborExchangeConfig::STATUS_OPEN,
            'UF_CREATED_AT' => $now,
            'UF_UPDATED_AT' => $now,
        ]);

        if ($inputCode !== '') {
            $this->professionRepository->consumeGovWarehouseQty($inputCode, $iterations);
        }

        $this->treasuryService->debit(
            GameEconomyConfig::CURRENCY_PROGNOBAKS,
            $coinEscrow,
            'labor_order_escrow',
            $orderId,
            null,
            'labor_order'
        );

        return $this->formatOrder(
            $this->orderRepository->getOrderById($orderId) ?? [],
            0,
            null,
            true
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getTreasuryOrders(): array
    {
        $items = [];
        foreach ($this->orderRepository->getOrdersByPosterKind(LaborExchangeConfig::POSTER_KIND_TREASURY, 50) as $row) {
            $items[] = $this->formatOrder($row, 0, null, true);
        }

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    public function cancelTreasuryOrder(int $orderId): array
    {
        $order = $this->orderRepository->getOrderById($orderId);
        if (!$order || !$this->isTreasuryOrder($order)) {
            throw new \RuntimeException('Заказ казны не найден');
        }

        if ((string)($order['UF_STATUS'] ?? '') !== LaborExchangeConfig::STATUS_OPEN) {
            throw new \RuntimeException('Заказ уже закрыт');
        }

        if ($this->orderRepository->orderHasActiveSession($orderId)) {
            throw new \RuntimeException('Нельзя снять заказ — исполнитель уже работает');
        }

        $inputCode = (string)($order['UF_INPUT_CODE'] ?? '');
        $inputEscrow = (int)($order['UF_INPUT_ESCROW_QTY'] ?? 0);
        $coinEscrow = (float)($order['UF_COIN_ESCROW'] ?? 0);

        if ($inputCode !== '' && $inputEscrow > 0) {
            $this->professionRepository->addGovWarehouseQty($inputCode, $inputEscrow);
        }

        if ($coinEscrow > 0) {
            $this->treasuryService->credit(
                GameEconomyConfig::CURRENCY_PROGNOBAKS,
                $coinEscrow,
                'labor_order_refund',
                $orderId,
                null,
                'labor_order'
            );
        }

        $this->orderRepository->updateOrder($orderId, [
            'UF_STATUS' => LaborExchangeConfig::STATUS_CANCELLED,
            'UF_INPUT_ESCROW_QTY' => 0,
            'UF_COIN_ESCROW' => 0,
            'UF_UPDATED_AT' => new DateTime(),
        ]);

        return $this->formatOrder($this->orderRepository->getOrderById($orderId) ?? [], 0, null, true);
    }

    /**
     * @return array<string, mixed>
     */
    public function cancelOrder(int $userId, int $orderId): array
    {
        $order = $this->requirePosterOrder($userId, $orderId);

        if ((string)($order['UF_STATUS'] ?? '') !== LaborExchangeConfig::STATUS_OPEN) {
            throw new \RuntimeException('Заказ уже закрыт');
        }

        if ($this->orderRepository->orderHasActiveSession($orderId)) {
            throw new \RuntimeException('Нельзя снять заказ — исполнитель уже работает');
        }

        $inputCode = (string)($order['UF_INPUT_CODE'] ?? '');
        $inputEscrow = (int)($order['UF_INPUT_ESCROW_QTY'] ?? 0);
        $coinEscrow = (float)($order['UF_COIN_ESCROW'] ?? 0);

        if ($inputCode !== '' && $inputEscrow > 0) {
            $this->professionRepository->addUserMaterialQty($userId, $inputCode, $inputEscrow, false);
        }

        if ($coinEscrow > 0) {
            $this->walletService->credit(
                $userId,
                GameEconomyConfig::CURRENCY_PROGNOBAKS,
                $coinEscrow,
                'labor_order_refund',
                'labor_order',
                $orderId
            );
        }

        $this->orderRepository->updateOrder($orderId, [
            'UF_STATUS' => LaborExchangeConfig::STATUS_CANCELLED,
            'UF_INPUT_ESCROW_QTY' => 0,
            'UF_COIN_ESCROW' => 0,
            'UF_UPDATED_AT' => new DateTime(),
        ]);

        return $this->formatOrder($this->orderRepository->getOrderById($orderId) ?? [], $userId);
    }

    /**
     * Исполнитель берёт заказ (до 5 циклов).
     *
     * @return array{order: array<string, mixed>, farm: array<string, mixed>}
     */
    public function claimOrder(int $userId, int $orderId, int $iterations = 0): array
    {
        return $this->startLaborSession($userId, $orderId, false, $iterations);
    }

    /**
     * Заказчик работает в мастерской по своему заказу.
     *
     * @return array{order: array<string, mixed>, farm: array<string, mixed>}
     */
    public function startPosterWorkshop(int $userId, int $orderId, int $iterations = 0): array
    {
        return $this->startLaborSession($userId, $orderId, true, $iterations);
    }

    /**
     * Завершение смены по заказу (вызывается из ProfessionFarmService).
     *
     * @param array<string, mixed> $session
     * @return array{message:string,pay_coins:float,fee_coins:float}
     */
    public function applySessionCompletion(
        int $workerUserId,
        array $session,
        int $iterations,
        int $totalComboYield,
        int $totalPremiumQty,
        string $outputCode,
        string $premiumCode,
        string $inputCode,
        bool $isProcessing
    ): array {
        $orderId = (int)($session['UF_LABOR_ORDER_ID'] ?? 0);
        if ($orderId <= 0) {
            throw new \RuntimeException('Смена не привязана к заказу');
        }

        $order = $this->orderRepository->getOrderById($orderId);
        if (!$order) {
            throw new \RuntimeException('Заказ не найден');
        }

        $workMode = (string)($session['UF_WORK_MODE'] ?? '');
        $posterUserId = (int)($order['UF_POSTER_USER_ID'] ?? 0);
        $isTreasury = $this->isTreasuryOrder($order);
        $payPerCycle = (float)($order['UF_PAY_PER_CYCLE'] ?? 0);
        $definition = ProfessionMaterialConfig::getProfession((string)($order['UF_PROFESSION_CODE'] ?? ''));
        $outputLabel = (string)($definition['output_label'] ?? $outputCode);
        $inputLabel = $isProcessing ? (string)($definition['input_label'] ?? '') : '';
        $premiumLabel = (string)($definition['premium_label'] ?? '');

        $inputConsumed = $isProcessing && $inputCode !== '' ? $iterations : 0;
        $baseOutput = $iterations;
        $bonusOutput = max(0, $totalComboYield - $iterations);
        $payCoins = 0.0;
        $feeCoins = 0.0;
        $message = '';

        if ($workMode === ProfessionMaterialConfig::WORK_MODE_LABOR_POSTER) {
            if ($workerUserId !== $posterUserId) {
                throw new \RuntimeException('Мастерская доступна только заказчику');
            }

            $feeCoins = $iterations * ProfessionEconomyConfig::FEE_SELF_PER_ITERATION;
            $wallet = $this->walletService->ensureWallet($workerUserId);
            if ((float)($wallet['prognobaks'] ?? 0) < $feeCoins) {
                throw new \RuntimeException('Недостаточно 🪙 для оплаты мастерской после смены');
            }

            if ($inputConsumed > 0) {
                $inputEscrow = (int)($order['UF_INPUT_ESCROW_QTY'] ?? 0);
                if ($inputEscrow < $inputConsumed) {
                    throw new \RuntimeException('В заказе недостаточно сырья');
                }
            }

            $posterOutput = $totalComboYield;
            if ($posterOutput > 0) {
                if ($isTreasury) {
                    $this->professionRepository->addGovWarehouseQty($outputCode, $posterOutput);
                } else {
                    $this->professionRepository->addUserMaterialQty($posterUserId, $outputCode, $posterOutput, false);
                }
            }
            if ($totalPremiumQty > 0 && $premiumCode !== '') {
                if ($isTreasury) {
                    $this->professionRepository->addGovWarehouseQty($premiumCode, $totalPremiumQty);
                } else {
                    $this->professionRepository->addUserMaterialQty($posterUserId, $premiumCode, $totalPremiumQty, true);
                }
            }

            $this->walletService->debit(
                $workerUserId,
                GameEconomyConfig::CURRENCY_PROGNOBAKS,
                $feeCoins,
                'labor_poster_workshop_fee',
                'labor_order',
                $orderId
            );
            $this->treasuryService->credit(
                GameEconomyConfig::CURRENCY_PROGNOBAKS,
                $feeCoins,
                'labor_poster_workshop_fee',
                $orderId,
                $workerUserId,
                'labor_order'
            );

            $message = $posterOutput . ' ' . $outputLabel . ($isTreasury ? ' на госсклад' : ' вам');
            if ($inputConsumed > 0) {
                $message .= ', −' . $inputConsumed . ' ' . $inputLabel . ' (заказ)';
            }
            if ($feeCoins > 0) {
                $message .= ', −' . $feeCoins . ' 🪙 (мастерская)';
            }
            if ($totalPremiumQty > 0) {
                $message .= ', +' . $totalPremiumQty . ' ' . $premiumLabel;
            }

            $this->orderRepository->applyOrderChunkCompletion($orderId, $iterations, $inputConsumed, 0.0);
        } else {
            if (!$isTreasury && $workerUserId === $posterUserId) {
                throw new \RuntimeException('Заказчик не может взять свой заказ как исполнитель');
            }

            $payCoins = round($iterations * $payPerCycle, 1);
            $coinEscrow = (float)($order['UF_COIN_ESCROW'] ?? 0);
            if ($coinEscrow < $payCoins) {
                throw new \RuntimeException('В заказе недостаточно оплаты труда');
            }

            if ($inputConsumed > 0) {
                $inputEscrow = (int)($order['UF_INPUT_ESCROW_QTY'] ?? 0);
                if ($inputEscrow < $inputConsumed) {
                    throw new \RuntimeException('В заказе недостаточно сырья');
                }
            }

            if ($baseOutput > 0) {
                if ($isTreasury) {
                    $this->professionRepository->addGovWarehouseQty($outputCode, $baseOutput);
                } else {
                    $this->professionRepository->addUserMaterialQty($posterUserId, $outputCode, $baseOutput, false);
                }
            }
            if ($bonusOutput > 0) {
                $this->professionRepository->addUserMaterialQty($workerUserId, $outputCode, $bonusOutput, false);
            }
            if ($totalPremiumQty > 0 && $premiumCode !== '') {
                $this->professionRepository->addUserMaterialQty($workerUserId, $premiumCode, $totalPremiumQty, true);
            }

            $this->walletService->credit(
                $workerUserId,
                GameEconomyConfig::CURRENCY_PROGNOBAKS,
                $payCoins,
                'labor_order_pay',
                'labor_order',
                $orderId
            );

            $message = '+' . $payCoins . ' 🪙';
            $message .= ', ' . $baseOutput . ' ' . $outputLabel . ($isTreasury ? ' на госсклад' : ' заказчику');
            if ($bonusOutput > 0) {
                $message .= ', +' . $bonusOutput . ' ' . $outputLabel . ' вам (комбо)';
            }
            if ($inputConsumed > 0) {
                $message .= ', −' . $inputConsumed . ' ' . $inputLabel . ' (заказ)';
            }
            if ($totalPremiumQty > 0) {
                $message .= ', +' . $totalPremiumQty . ' ' . $premiumLabel;
            }

            $this->orderRepository->applyOrderChunkCompletion($orderId, $iterations, $inputConsumed, $payCoins);
        }

        return [
            'message' => $message,
            'pay_coins' => $payCoins,
            'fee_coins' => $feeCoins,
        ];
    }

    /**
     * @return array{order: array<string, mixed>, farm: array<string, mixed>}
     */
    private function startLaborSession(int $userId, int $orderId, bool $asPoster, int $requestedIterations = 0): array
    {
        $order = $this->orderRepository->getOrderById($orderId);
        if (!$order) {
            throw new \RuntimeException('Заказ не найден');
        }

        if ((string)($order['UF_STATUS'] ?? '') !== LaborExchangeConfig::STATUS_OPEN) {
            throw new \RuntimeException('Заказ недоступен');
        }

        $posterUserId = (int)($order['UF_POSTER_USER_ID'] ?? 0);
        $professionCode = (string)($order['UF_PROFESSION_CODE'] ?? '');
        $isTreasury = $this->isTreasuryOrder($order);

        if ($asPoster) {
            if ($isTreasury) {
                throw new \RuntimeException('Заказ казны нельзя выполнять в мастерской');
            }
            if ($userId !== $posterUserId) {
                throw new \RuntimeException('Мастерская доступна только заказчику');
            }
        } elseif (!$isTreasury && $userId === $posterUserId) {
            throw new \RuntimeException('Возьмите заказ в мастерской или дождитесь другого исполнителя');
        }

        if (!$this->professionRepository->getProfessionByUserAndCode($userId, $professionCode)) {
            throw new \RuntimeException('Нужна профессия: ' . $professionCode);
        }

        if ($this->professionRepository->getActiveSessionByUserId($userId)) {
            throw new \RuntimeException('Уже есть активная смена');
        }

        if ($requestedIterations > LaborExchangeConfig::MAX_CYCLES_PER_CLAIM) {
            throw new \InvalidArgumentException(
                'За раз можно взять не более ' . LaborExchangeConfig::MAX_CYCLES_PER_CLAIM . ' циклов'
            );
        }

        if ($requestedIterations < 0) {
            throw new \InvalidArgumentException('Некорректное число циклов');
        }

        $reserved = $this->orderRepository->reserveOrderChunk(
            $orderId,
            LaborExchangeConfig::MAX_CYCLES_PER_CLAIM,
            $requestedIterations
        );
        if (!$reserved) {
            throw new \RuntimeException('Не удалось зарезервировать циклы по заказу');
        }

        $chunk = (int)$reserved['chunk'];
        $payPerCycle = (float)($order['UF_PAY_PER_CYCLE'] ?? 0);
        if (!$asPoster) {
            $needCoins = round($chunk * $payPerCycle, 1);
            if ((float)($order['UF_COIN_ESCROW'] ?? 0) < $needCoins) {
                throw new \RuntimeException('В заказе недостаточно оплаты');
            }
        }

        $workMode = $asPoster
            ? ProfessionMaterialConfig::WORK_MODE_LABOR_POSTER
            : ProfessionMaterialConfig::WORK_MODE_LABOR;

        $now = new DateTime();
        $shiftMinutes = $chunk * ProfessionEconomyConfig::ITERATION_MINUTES;
        $nextTick = (clone $now)->add('+' . $shiftMinutes . ' minutes');

        $this->professionRepository->addProfessionSession([
            'UF_USER_ID' => $userId,
            'UF_PROFESSION_CODE' => $professionCode,
            'UF_WORK_MODE' => $workMode,
            'UF_STATUS' => ProfessionMaterialConfig::SESSION_STATUS_ACTIVE,
            'UF_ITERATIONS_DONE' => 0,
            'UF_ITERATIONS_TOTAL' => $chunk,
            'UF_LABOR_ORDER_ID' => $orderId,
            'UF_NEXT_TICK_AT' => $nextTick,
            'UF_STARTED_AT' => $now,
            'UF_UPDATED_AT' => $now,
            'UF_LAST_RESULT_JSON' => '',
        ]);

        $farm = (new ProfessionFarmService(
            $this->professionRepository,
            $this->walletService
        ))->getState($userId);

        return [
            'order' => $this->formatOrder($this->orderRepository->getOrderById($orderId) ?? $order, $userId),
            'farm' => $farm,
        ];
    }

    /**
     * @param array<string, mixed> $order
     * @param string[]|null $userProfessionCodes
     * @return array<string, mixed>
     */
    private function formatOrder(
        array $order,
        int $viewerUserId,
        ?array $userProfessionCodes = null,
        bool $viewerCanManageTreasury = false
    ): array {
        if ($order === []) {
            return [];
        }

        $orderId = (int)($order['ID'] ?? 0);
        $professionCode = (string)($order['UF_PROFESSION_CODE'] ?? '');
        $definition = ProfessionMaterialConfig::getProfession($professionCode);
        $total = (int)($order['UF_ITERATIONS_TOTAL'] ?? 0);
        $done = (int)($order['UF_ITERATIONS_DONE'] ?? 0);
        $remaining = max(0, $total - $done - $this->orderRepository->getActiveSessionIterationsSum($orderId));
        $posterUserId = (int)($order['UF_POSTER_USER_ID'] ?? 0);
        $posterKind = (string)($order['UF_POSTER_KIND'] ?? LaborExchangeConfig::POSTER_KIND_USER);
        $isTreasury = $this->isTreasuryOrder($order);
        $status = (string)($order['UF_STATUS'] ?? '');
        $professionCodes = $userProfessionCodes ?? $this->getUserProfessionCodes($viewerUserId);

        $canClaim = $status === LaborExchangeConfig::STATUS_OPEN
            && $remaining > 0
            && ($isTreasury || $viewerUserId !== $posterUserId)
            && in_array($professionCode, $professionCodes, true)
            && !$this->professionRepository->getActiveSessionByUserId($viewerUserId);

        $canWorkshop = !$isTreasury
            && $status === LaborExchangeConfig::STATUS_OPEN
            && $remaining > 0
            && $viewerUserId === $posterUserId
            && in_array($professionCode, $professionCodes, true)
            && !$this->professionRepository->getActiveSessionByUserId($viewerUserId);

        $canCancel = $status === LaborExchangeConfig::STATUS_OPEN
            && !$this->orderRepository->orderHasActiveSession($orderId)
            && (
                ($isTreasury && $viewerCanManageTreasury)
                || (!$isTreasury && $viewerUserId === $posterUserId)
            );

        return [
            'id' => $orderId,
            'poster_user_id' => $posterUserId,
            'poster_kind' => $posterKind,
            'poster_name' => $isTreasury ? 'Казна' : $this->resolveUserDisplayName($posterUserId),
            'profession_code' => $professionCode,
            'profession_label' => (string)($definition['label'] ?? $professionCode),
            'type' => (string)($definition['type'] ?? 'gather'),
            'output_code' => (string)($order['UF_OUTPUT_CODE'] ?? ''),
            'output_label' => (string)($definition['output_label'] ?? ''),
            'input_code' => (string)($order['UF_INPUT_CODE'] ?? ''),
            'input_label' => (string)($definition['input_label'] ?? ''),
            'iterations_total' => $total,
            'iterations_done' => $done,
            'iterations_remaining' => $remaining,
            'pay_per_cycle' => (float)($order['UF_PAY_PER_CYCLE'] ?? 0),
            'pay_total_remaining' => round($remaining * (float)($order['UF_PAY_PER_CYCLE'] ?? 0), 1),
            'input_escrow' => (int)($order['UF_INPUT_ESCROW_QTY'] ?? 0),
            'coin_escrow' => (float)($order['UF_COIN_ESCROW'] ?? 0),
            'status' => $status,
            'is_mine' => $isTreasury ? false : $viewerUserId === $posterUserId,
            'is_treasury' => $isTreasury,
            'can_claim' => $canClaim,
            'can_workshop' => $canWorkshop,
            'can_cancel' => $canCancel,
            'has_active_worker' => $this->orderRepository->orderHasActiveSession($orderId),
            'max_claim_cycles' => min(LaborExchangeConfig::MAX_CYCLES_PER_CLAIM, $remaining),
            'created_at' => $this->formatDateTime($order['UF_CREATED_AT'] ?? null),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getPostableProfessions(): array
    {
        return array_values(array_map(static function (array $definition): array {
            return [
                'code' => (string)($definition['code'] ?? ''),
                'label' => (string)($definition['label'] ?? ''),
                'type' => (string)($definition['type'] ?? 'gather'),
                'input_label' => (string)($definition['input_label'] ?? ''),
                'output_label' => (string)($definition['output_label'] ?? ''),
            ];
        }, ProfessionMaterialConfig::allProfessions()));
    }

    /**
     * @return string[]
     */
    private function getUserProfessionCodes(int $userId): array
    {
        $codes = [];
        foreach ($this->professionRepository->getProfessionsByUserId($userId) as $row) {
            $code = (string)($row['UF_PROFESSION_CODE'] ?? '');
            if ($code !== '') {
                $codes[] = $code;
            }
        }

        return $codes;
    }

    /**
     * @param array<string, mixed> $order
     */
    private function isTreasuryOrder(array $order): bool
    {
        return (string)($order['UF_POSTER_KIND'] ?? '') === LaborExchangeConfig::POSTER_KIND_TREASURY;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveProfessionDefinition(string $professionCode): array
    {
        $definition = ProfessionMaterialConfig::getProfession($professionCode);
        if (!$definition) {
            throw new \InvalidArgumentException('Неизвестная профессия: ' . $professionCode);
        }

        return $definition;
    }

    /**
     * @return array<string, mixed>
     */
    private function requirePosterOrder(int $userId, int $orderId): array
    {
        $order = $this->orderRepository->getOrderById($orderId);
        if (!$order) {
            throw new \RuntimeException('Заказ не найден');
        }

        if ((int)($order['UF_POSTER_USER_ID'] ?? 0) !== $userId) {
            throw new \RuntimeException('Это не ваш заказ');
        }

        return $order;
    }

    private function resolveUserDisplayName(int $userId): string
    {
        if ($userId <= 0) {
            return '';
        }

        $userRow = \Bitrix\Main\UserTable::getList([
            'filter' => ['=ID' => $userId],
            'select' => ['ID', 'LOGIN', 'NAME', 'LAST_NAME', 'SECOND_NAME'],
            'limit' => 1,
        ])->fetch();

        if (!$userRow) {
            return 'игрок #' . $userId;
        }

        $parts = array_filter([
            (string)($userRow['NAME'] ?? ''),
            (string)($userRow['LAST_NAME'] ?? ''),
        ]);

        if ($parts) {
            return implode(' ', $parts);
        }

        return (string)($userRow['LOGIN'] ?? ('#' . $userId));
    }

    /**
     * @param mixed $value
     */
    private function formatDateTime($value): string
    {
        if ($value instanceof DateTime) {
            return $value->format('d.m.Y H:i');
        }

        return '';
    }
}
