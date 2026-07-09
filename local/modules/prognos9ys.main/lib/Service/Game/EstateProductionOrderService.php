<?php

namespace Prognos9ys\Main\Service\Game;

use Bitrix\Main\Type\DateTime;
use Prognos9ys\Main\Model\Repository\LaborOrderRepository;
use Prognos9ys\Main\Model\Repository\ProfessionRepository;

class EstateProductionOrderService
{
    private LaborOrderRepository $orderRepository;
    private ProfessionRepository $professionRepository;
    private WalletService $walletService;
    private ProfessionCraftService $craftService;
    private EstatePlotService $plotService;

    public function __construct(
        ?LaborOrderRepository $orderRepository = null,
        ?ProfessionRepository $professionRepository = null,
        ?WalletService $walletService = null,
        ?ProfessionCraftService $craftService = null,
        ?EstatePlotService $plotService = null
    ) {
        $this->orderRepository = $orderRepository ?? new LaborOrderRepository();
        $this->professionRepository = $professionRepository ?? new ProfessionRepository();
        $this->walletService = $walletService ?? new WalletService();
        $this->craftService = $craftService ?? new ProfessionCraftService($this->professionRepository);
        $this->plotService = $plotService ?? new EstatePlotService(null, $this->professionRepository);
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function createOrder(
        int $userId,
        string $componentCode,
        int $qty,
        array $context = []
    ): array {
        $componentCode = trim($componentCode);
        if ($componentCode === '') {
            throw new \InvalidArgumentException('Не указан компонент');
        }

        $craft = EstateBuildingRecipeBridge::resolveComponentCraft($componentCode);
        $recipeCode = (string)($craft['recipe_code'] ?? '');
        $professionCode = (string)($craft['profession'] ?? '');
        if ($recipeCode === '' || $professionCode === '') {
            throw new \RuntimeException('Для «' . ProfessionCraftedItemConfig::getLabel($componentCode) . '» нет рецепта крафта');
        }

        $qty = max(1, min(LaborExchangeConfig::MAX_ITERATIONS, $qty));
        $payPerCycle = $this->resolvePayPerCycle($componentCode);
        $coinEscrow = 0.0;

        $now = new DateTime();
        $orderId = $this->orderRepository->addOrder([
            'UF_POSTER_USER_ID' => $userId,
            'UF_POSTER_KIND' => LaborExchangeConfig::POSTER_KIND_USER,
            'UF_ORDER_PURPOSE' => LaborExchangeConfig::ORDER_PURPOSE_ESTATE,
            'UF_PROFESSION_CODE' => $professionCode,
            'UF_RECIPE_CODE' => $recipeCode,
            'UF_OUTPUT_CODE' => $componentCode,
            'UF_INPUT_CODE' => '',
            'UF_ITERATIONS_TOTAL' => $qty,
            'UF_ITERATIONS_DONE' => 0,
            'UF_INPUT_ESCROW_QTY' => 0,
            'UF_PAY_PER_CYCLE' => $payPerCycle,
            'UF_COIN_ESCROW' => $coinEscrow,
            'UF_STATUS' => LaborExchangeConfig::STATUS_OPEN,
            'UF_CONTEXT_JSON' => $context !== [] ? json_encode($context, JSON_UNESCAPED_UNICODE) : '',
            'UF_CREATED_AT' => $now,
            'UF_UPDATED_AT' => $now,
        ]);

        return $this->formatOrder($this->orderRepository->getOrderById($orderId) ?? [], $userId);
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, pagination: array<string, int|bool>}
     */
    public function getOpenOrders(int $userId, int $offset = 0, int $limit = 25): array
    {
        $limit = max(1, min(50, $limit));
        $offset = max(0, $offset);
        $rows = $this->orderRepository->getOpenOrdersByPurpose(
            LaborExchangeConfig::ORDER_PURPOSE_ESTATE,
            $limit + 1,
            $offset
        );
        $hasMore = count($rows) > $limit;
        if ($hasMore) {
            array_pop($rows);
        }

        $rows = array_values(array_filter($rows, static function (array $row) use ($userId): bool {
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

        foreach (
            $this->orderRepository->getOrdersByPosterUserIdAndPurpose(
                $userId,
                LaborExchangeConfig::ORDER_PURPOSE_ESTATE,
                50
            ) as $row
        ) {
            $items[] = $this->formatOrder($row, $userId, $professionCodes);
        }

        return $items;
    }

    /**
     * @return array{order: array<string, mixed>, craft: array<string, mixed>, message: string}
     */
    public function claimOrder(int $userId, int $orderId, int $qty = 0): array
    {
        $order = $this->orderRepository->getOrderById($orderId);
        if (!$order || !LaborOrderRepository::isEstateOrder($order)) {
            throw new \RuntimeException('Заказ не найден');
        }

        if ((string)($order['UF_STATUS'] ?? '') !== LaborExchangeConfig::STATUS_OPEN) {
            throw new \RuntimeException('Заказ уже закрыт');
        }

        $posterUserId = (int)($order['UF_POSTER_USER_ID'] ?? 0);
        if ($posterUserId === $userId) {
            throw new \RuntimeException('Свой заказ нельзя выполнить — дождитесь исполнителя');
        }

        $total = (int)($order['UF_ITERATIONS_TOTAL'] ?? 0);
        $done = (int)($order['UF_ITERATIONS_DONE'] ?? 0);
        $remaining = max(0, $total - $done);
        if ($remaining <= 0) {
            throw new \RuntimeException('Заказ уже выполнен');
        }

        $recipeCode = (string)($order['UF_RECIPE_CODE'] ?? '');
        $professionCode = (string)($order['UF_PROFESSION_CODE'] ?? '');
        $outputCode = (string)($order['UF_OUTPUT_CODE'] ?? '');
        $payPerCycle = (float)($order['UF_PAY_PER_CYCLE'] ?? 0);

        $maxByMaterials = $this->craftService->maxCraftableQty($userId, $recipeCode, $professionCode);
        if ($maxByMaterials <= 0) {
            throw new \RuntimeException('Недостаточно материалов или 🪙 для крафта');
        }

        $chunk = min(
            LaborExchangeConfig::MAX_ITERATIONS,
            $remaining,
            $maxByMaterials
        );
        if ($qty > 0) {
            $chunk = min($chunk, $qty);
        }
        if ($chunk <= 0) {
            throw new \RuntimeException('Нельзя взять заказ');
        }

        $payCoins = round($chunk * $payPerCycle, 1);

        $craft = $this->craftService->craftForRecipient(
            $userId,
            $posterUserId,
            $recipeCode,
            $professionCode,
            $chunk
        );

        $this->walletService->credit(
            $userId,
            GameEconomyConfig::CURRENCY_PROGNOBAKS,
            $payCoins,
            'estate_order_pay',
            'estate_order',
            $orderId
        );

        $updated = $this->orderRepository->applyOrderChunkCompletion($orderId, $chunk, 0, $payCoins);
        $outputLabel = ProfessionCraftedItemConfig::getLabel($outputCode);
        $deliveryMessage = $this->deliverFulfilledComponent($posterUserId, $outputCode, $chunk, $order, true);

        return [
            'order' => $this->formatOrder($updated, $userId),
            'craft' => $craft,
            'message' => '+' . $payCoins . ' 🪙, ' . $chunk . ' ' . $outputLabel . ' — ' . $deliveryMessage,
        ];
    }

    /**
     * Сдать готовый компонент из инвентаря (как в госстройке).
     *
     * @return array{order: array<string, mixed>, message: string}
     */
    public function submitFromInventory(int $userId, int $orderId, int $qty = 0): array
    {
        $order = $this->orderRepository->getOrderById($orderId);
        if (!$order || !LaborOrderRepository::isEstateOrder($order)) {
            throw new \RuntimeException('Заказ не найден');
        }

        if ((string)($order['UF_STATUS'] ?? '') !== LaborExchangeConfig::STATUS_OPEN) {
            throw new \RuntimeException('Заказ уже закрыт');
        }

        $posterUserId = (int)($order['UF_POSTER_USER_ID'] ?? 0);
        if ($posterUserId === $userId) {
            throw new \RuntimeException('Свой заказ нельзя выполнить — дождитесь исполнителя');
        }

        $total = (int)($order['UF_ITERATIONS_TOTAL'] ?? 0);
        $done = (int)($order['UF_ITERATIONS_DONE'] ?? 0);
        $remaining = max(0, $total - $done);
        if ($remaining <= 0) {
            throw new \RuntimeException('Заказ уже выполнен');
        }

        $outputCode = (string)($order['UF_OUTPUT_CODE'] ?? '');
        if ($outputCode === '') {
            throw new \RuntimeException('Некорректный заказ');
        }

        $payPerCycle = (float)($order['UF_PAY_PER_CYCLE'] ?? 0);
        $userHave = $this->professionRepository->getUserMaterialQty($userId, $outputCode, false);
        if ($userHave <= 0) {
            throw new \RuntimeException('Нет компонента в инвентаре');
        }

        $chunk = $this->resolveSubmitChunk($remaining, $userHave, $payPerCycle);
        if ($qty > 0) {
            $chunk = min($chunk, $qty);
        }
        if ($chunk <= 0) {
            throw new \RuntimeException('Нельзя сдать компонент по этому заказу');
        }

        $payCoins = round($chunk * $payPerCycle, 1);

        $this->professionRepository->consumeUserMaterialQty($userId, $outputCode, $chunk, false);

        $this->walletService->credit(
            $userId,
            GameEconomyConfig::CURRENCY_PROGNOBAKS,
            $payCoins,
            'estate_order_submit',
            'estate_order',
            $orderId
        );

        $updated = $this->orderRepository->applyOrderChunkCompletion($orderId, $chunk, 0, $payCoins);
        $outputLabel = ProfessionCraftedItemConfig::getLabel($outputCode);
        $deliveryMessage = $this->deliverFulfilledComponent($posterUserId, $outputCode, $chunk, $order);

        return [
            'order' => $this->formatOrder($updated, $userId),
            'message' => '+' . $payCoins . ' 🪙, сдано ' . $chunk . ' ' . $outputLabel . ' — ' . $deliveryMessage,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function cancelOrder(int $userId, int $orderId): array
    {
        $order = $this->orderRepository->getOrderById($orderId);
        if (!$order || !LaborOrderRepository::isEstateOrder($order)) {
            throw new \RuntimeException('Заказ не найден');
        }

        if ((int)($order['UF_POSTER_USER_ID'] ?? 0) !== $userId) {
            throw new \RuntimeException('Это не ваш заказ');
        }

        if ((string)($order['UF_STATUS'] ?? '') !== LaborExchangeConfig::STATUS_OPEN) {
            throw new \RuntimeException('Заказ уже закрыт');
        }

        $this->orderRepository->updateOrder($orderId, [
            'UF_STATUS' => LaborExchangeConfig::STATUS_CANCELLED,
            'UF_COIN_ESCROW' => 0,
            'UF_UPDATED_AT' => new DateTime(),
        ]);

        return $this->formatOrder($this->orderRepository->getOrderById($orderId) ?? [], $userId);
    }

    public function getMeta(): array
    {
        return [
            'max_claim_qty' => LaborExchangeConfig::MAX_ITERATIONS,
            'default_pay_per_cycle' => LaborExchangeConfig::DEFAULT_PAY_PER_CYCLE,
        ];
    }

    private function resolvePayPerCycle(string $componentCode): float
    {
        $payout = EstateRecipesConfig::calcComponentDonationUnitPayout($componentCode);

        return max(LaborExchangeConfig::MIN_PAY_PER_CYCLE, $payout);
    }

    /**
     * @param array<string, mixed> $order
     * @param string[]|null $userProfessionCodes
     * @return array<string, mixed>
     */
    private function formatOrder(array $order, int $viewerUserId, ?array $userProfessionCodes = null): array
    {
        if ($order === []) {
            return [];
        }

        $orderId = (int)($order['ID'] ?? 0);
        $professionCode = (string)($order['UF_PROFESSION_CODE'] ?? '');
        $profession = ProfessionMaterialConfig::getProfession($professionCode);
        $outputCode = (string)($order['UF_OUTPUT_CODE'] ?? '');
        $recipeCode = (string)($order['UF_RECIPE_CODE'] ?? '');
        $total = (int)($order['UF_ITERATIONS_TOTAL'] ?? 0);
        $done = (int)($order['UF_ITERATIONS_DONE'] ?? 0);
        $remaining = max(0, $total - $done);
        $posterUserId = (int)($order['UF_POSTER_USER_ID'] ?? 0);
        $status = (string)($order['UF_STATUS'] ?? '');
        $payPerCycle = (float)($order['UF_PAY_PER_CYCLE'] ?? 0);
        $isMine = $viewerUserId > 0 && $viewerUserId === $posterUserId;
        $userHave = $viewerUserId > 0 && $outputCode !== ''
            ? $this->professionRepository->getUserMaterialQty($viewerUserId, $outputCode, false)
            : 0;

        if ($userProfessionCodes === null) {
            $userProfessionCodes = $this->getUserProfessionCodes($viewerUserId);
        }

        $canSubmit = false;
        $maxSubmit = 0;
        if ($status === LaborExchangeConfig::STATUS_OPEN && !$isMine && $remaining > 0 && $outputCode !== '') {
            $maxSubmit = $this->resolveSubmitChunk($remaining, $userHave, $payPerCycle);
            $canSubmit = $maxSubmit > 0;
        }

        $canClaim = false;
        $maxClaim = 0;
        $claimBlockReason = '';
        if ($status === LaborExchangeConfig::STATUS_OPEN && !$isMine && $remaining > 0 && $recipeCode !== '') {
            if (!in_array($professionCode, $userProfessionCodes, true)) {
                $claimBlockReason = 'Нужна профессия: ' . (string)($profession['label'] ?? $professionCode);
            } else {
                $eligibility = $this->craftService->resolveEstateOrderCraftEligibility(
                    $viewerUserId,
                    $recipeCode,
                    $professionCode
                );
                $maxClaim = min($remaining, (int)($eligibility['max_qty'] ?? 0));
                if ($maxClaim <= 0) {
                    $claimBlockReason = (string)($eligibility['block_reason'] ?? '');
                }
                $canClaim = $maxClaim > 0;
            }
        }

        if (!$canSubmit && !$canClaim && !$isMine && $status === LaborExchangeConfig::STATUS_OPEN && $remaining > 0) {
            if ($claimBlockReason === '' && $userHave <= 0) {
                $claimBlockReason = 'Нет компонента в инвентаре и нельзя скрафтить';
            }
        } elseif ($canSubmit || $canClaim) {
            $claimBlockReason = '';
        }

        $context = [];
        $contextRaw = trim((string)($order['UF_CONTEXT_JSON'] ?? ''));
        if ($contextRaw !== '') {
            $decoded = json_decode($contextRaw, true);
            if (is_array($decoded)) {
                $context = $decoded;
            }
        }

        return [
            'id' => $orderId,
            'poster_user_id' => $posterUserId,
            'poster_name' => $this->resolveUserDisplayName($posterUserId),
            'profession_code' => $professionCode,
            'profession_label' => (string)($profession['label'] ?? $professionCode),
            'recipe_code' => $recipeCode,
            'recipe_label' => $recipeCode !== '' ? ProfessionRecipeConfig::getRecipeLabel($recipeCode) : '',
            'output_code' => $outputCode,
            'output_label' => ProfessionCraftedItemConfig::getLabel($outputCode),
            'iterations_total' => $total,
            'iterations_done' => $done,
            'iterations_remaining' => $remaining,
            'pay_per_cycle' => $payPerCycle,
            'pay_total_reserved' => round($total * $payPerCycle, 1),
            'pay_total_remaining' => round($remaining * $payPerCycle, 1),
            'coin_escrow' => $coinEscrow,
            'status' => $status,
            'is_mine' => $isMine,
            'user_have' => $userHave,
            'can_submit' => $canSubmit,
            'max_submit_qty' => $maxSubmit,
            'can_claim' => $canClaim,
            'can_cancel' => $isMine && $status === LaborExchangeConfig::STATUS_OPEN,
            'max_claim_qty' => $maxClaim,
            'claim_block_reason' => $claimBlockReason,
            'context' => $context,
            'created_at' => $this->formatDateTime($order['UF_CREATED_AT'] ?? null),
        ];
    }

    private function resolveSubmitChunk(int $remaining, int $userHave, float $payPerCycle): int
    {
        if ($remaining <= 0 || $userHave <= 0 || $payPerCycle <= 0) {
            return 0;
        }

        return min(
            $remaining,
            $userHave,
            LaborExchangeConfig::MAX_ITERATIONS
        );
    }

    /**
     * @param array<string, mixed> $order
     */
    private function deliverFulfilledComponent(
        int $posterUserId,
        string $componentCode,
        int $qty,
        array $order,
        bool $fromPosterInventory = false
    ): string {
        $context = $this->parseOrderContext($order);
        $citySlug = (string)($context['city_slug'] ?? '');
        $plotNumber = (int)($context['plot_number'] ?? 0);
        $projectCode = (string)($context['project_code'] ?? '');

        if ($citySlug !== '' && $plotNumber > 0 && $projectCode !== '') {
            try {
                if ($fromPosterInventory) {
                    $result = $this->plotService->donateComponent(
                        $posterUserId,
                        $citySlug,
                        $plotNumber,
                        $projectCode,
                        $componentCode,
                        $qty
                    );
                    $toStash = (int)($result['donated_qty'] ?? 0);
                    $leftInInventory = $qty - $toStash;
                    if ($toStash > 0 && $leftInInventory > 0) {
                        return 'на стройку ×' . $toStash . ', осталось в инвентаре ×' . $leftInInventory;
                    }
                    if ($toStash > 0) {
                        return 'зарезервировано на стройку';
                    }
                } else {
                    $result = $this->plotService->depositOrderFulfillment(
                        $posterUserId,
                        $citySlug,
                        $plotNumber,
                        $projectCode,
                        $componentCode,
                        $qty
                    );
                    $toStash = (int)($result['deposited_qty'] ?? 0);
                    $toInventory = (int)($result['inventory_qty'] ?? 0);
                    if ($toStash > 0 && $toInventory > 0) {
                        return 'на стройку ×' . $toStash . ', в инвентарь ×' . $toInventory;
                    }
                    if ($toStash > 0) {
                        return 'зарезервировано на стройку';
                    }
                }
            } catch (\Throwable $e) {
                // fallback ниже
            }
        }

        if (!$fromPosterInventory) {
            $this->professionRepository->addUserMaterialQty($posterUserId, $componentCode, $qty, false);
        }

        return $fromPosterInventory ? 'осталось в инвентаре' : 'в инвентарь заказчика';
    }

    /**
     * @param array<string, mixed> $order
     * @return array<string, mixed>
     */
    private function parseOrderContext(array $order): array
    {
        $contextRaw = trim((string)($order['UF_CONTEXT_JSON'] ?? ''));
        if ($contextRaw === '') {
            return [];
        }

        $decoded = json_decode($contextRaw, true);

        return is_array($decoded) ? $decoded : [];
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
