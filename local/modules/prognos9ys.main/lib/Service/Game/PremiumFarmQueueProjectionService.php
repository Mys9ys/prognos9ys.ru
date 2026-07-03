<?php

namespace Prognos9ys\Main\Service\Game;

use Prognos9ys\Main\Model\Repository\GameEconomyRepository;
use Prognos9ys\Main\Model\Repository\ProfessionRepository;

/**
 * Виртуальный баланс материалов и резерв 🪙 с учётом очереди Premium (farm).
 */
class PremiumFarmQueueProjectionService
{
    private ProfessionRepository $professionRepository;
    private GameEconomyRepository $economyRepository;
    private WalletService $walletService;

    public function __construct(
        ?ProfessionRepository $professionRepository = null,
        ?GameEconomyRepository $economyRepository = null,
        ?WalletService $walletService = null
    ) {
        $this->professionRepository = $professionRepository ?? new ProfessionRepository();
        $this->economyRepository = $economyRepository ?? new GameEconomyRepository();
        $this->walletService = $walletService ?? new WalletService($this->economyRepository);
    }

    /**
     * @return array{
     *   materials_self: array<string, int>,
     *   materials_gov: array<string, int>,
     *   reserved_prognobaks: float,
     *   wallet_prognobaks: float,
     *   wallet_available_self_farm: float
     * }
     */
    public function buildPreview(int $userId): array
    {
        if ($userId <= 0) {
            return $this->emptyPreview();
        }

        $state = $this->createVirtualState($userId);
        $wallet = $this->walletService->getWalletSummary($userId);
        $walletPrognobaks = round((float)($wallet['prognobaks'] ?? 0), 1);
        $reserved = round((float)($state['reserved_prognobaks'] ?? 0), 1);

        return [
            'materials_self' => $state['materials_self'],
            'materials_gov' => $state['materials_gov'],
            'albums' => (int)($state['albums'] ?? 0),
            'reserved_prognobaks' => $reserved,
            'wallet_prognobaks' => $walletPrognobaks,
            'wallet_available_self_farm' => round(max(0.0, $walletPrognobaks - $reserved), 1),
        ];
    }

    /**
     * Виртуальное состояние после симуляции текущей очереди (для макросов и превью).
     *
     * @return array{
     *   materials_self: array<string, int>,
     *   materials_gov: array<string, int>,
     *   albums: int,
     *   reserved_prognobaks: float
     * }
     */
    public function createVirtualState(int $userId): array
    {
        $selfMaterials = $this->loadSelfMaterialMap($userId);
        $govMaterials = $this->loadGovMaterialMap();
        $albums = $this->loadSellableAlbumQty($userId);
        $queueRows = $this->loadQueueRows($userId);
        $activeSession = $this->professionRepository->getActiveSessionByUserId($userId);

        $state = [
            'materials_self' => $selfMaterials,
            'materials_gov' => $govMaterials,
            'albums' => $albums,
            'reserved_prognobaks' => 0.0,
        ];

        foreach ($queueRows as $row) {
            $this->simulateQueueRow($state, $row, $activeSession);
        }

        return $state;
    }

    /**
     * @param array{
     *   materials_self: array<string, int>,
     *   materials_gov: array<string, int>,
     *   albums: int,
     *   reserved_prognobaks: float
     * } $state
     * @param array<string, mixed> $payload
     */
    public function simulateFarmTask(array &$state, array $payload, int $iterations): void
    {
        if ($iterations <= 0) {
            return;
        }

        $this->applyFarmTaskToVirtualBalances(
            $state['materials_self'],
            $state['materials_gov'],
            $payload,
            $iterations
        );

        if (($payload['work_mode'] ?? '') === ProfessionMaterialConfig::WORK_MODE_SELF) {
            $state['reserved_prognobaks'] = round(
                (float)$state['reserved_prognobaks'] + $iterations * ProfessionEconomyConfig::FEE_SELF_PER_ITERATION,
                1
            );
        }
    }

    /**
     * @param array{
     *   materials_self: array<string, int>,
     *   materials_gov: array<string, int>,
     *   albums: int,
     *   reserved_prognobaks: float
     * } $state
     */
    public function simulateAlbumCraft(array &$state): void
    {
        $plank = AlbumConfig::RECIPE_PLANK;
        $cloth = AlbumConfig::RECIPE_CLOTH;
        $state['materials_self']['plank'] = max(0, (int)($state['materials_self']['plank'] ?? 0) - $plank);
        $state['materials_self']['cloth'] = max(0, (int)($state['materials_self']['cloth'] ?? 0) - $cloth);
        $state['albums'] = (int)($state['albums'] ?? 0) + AlbumConfig::CRAFT_OUTPUT_COUNT;
    }

    /**
     * @param array{
     *   materials_self: array<string, int>,
     *   materials_gov: array<string, int>,
     *   albums: int,
     *   reserved_prognobaks: float
     * } $state
     * @param array<string, mixed> $payload
     */
    public function simulateExchangeList(array &$state, array $payload): void
    {
        $kind = (string)($payload['kind'] ?? '');
        $code = (string)($payload['code'] ?? '');
        $category = (string)($payload['category'] ?? '');
        $qty = max(0, (int)($payload['qty'] ?? 0));
        if ($qty <= 0) {
            return;
        }

        if ($kind === ExchangeConfig::KIND_LOOT
            && $code === AlbumConfig::ITEM_CODE
            && $category === ChestLootConfig::CATEGORY_ALBUM
        ) {
            $state['albums'] = max(0, (int)($state['albums'] ?? 0) - $qty);

            return;
        }

        if ($kind === ExchangeConfig::KIND_MATERIAL) {
            if ($category === ExchangeConfig::MATERIAL_CATEGORY_PREMIUM) {
                return;
            }

            $state['materials_self'][$code] = max(0, (int)($state['materials_self'][$code] ?? 0) - $qty);
        }
    }

    /**
     * Максимум циклов для новой farm-задачи после симуляции текущей очереди.
     */
    public function resolveMaxFarmIterationsAfterQueue(
        int $userId,
        string $professionCode,
        string $workMode,
        int $requestedIterations
    ): int {
        if ($userId <= 0 || $professionCode === '') {
            return 0;
        }

        $requestedIterations = $requestedIterations > 0
            ? min(ProfessionEconomyConfig::FREE_ITERATIONS_PER_SESSION, $requestedIterations)
            : ProfessionEconomyConfig::FREE_ITERATIONS_PER_SESSION;

        $selfMaterials = $this->loadSelfMaterialMap($userId);
        $govMaterials = $this->loadGovMaterialMap();
        $queueRows = $this->loadQueueRows($userId);
        $activeSession = $this->professionRepository->getActiveSessionByUserId($userId);

        $state = [
            'materials_self' => $selfMaterials,
            'materials_gov' => $govMaterials,
            'albums' => 0,
            'reserved_prognobaks' => 0.0,
        ];
        foreach ($queueRows as $row) {
            $this->simulateQueueRow($state, $row, $activeSession);
        }
        $selfMaterials = $state['materials_self'];
        $govMaterials = $state['materials_gov'];

        return $this->resolveMaxForSingleTask(
            $selfMaterials,
            $govMaterials,
            $professionCode,
            $workMode,
            $requestedIterations
        );
    }

    public function assertSelfFarmAffordable(int $userId, int $iterations, float $reservedBeforeNewTask): void
    {
        if ($iterations <= 0) {
            return;
        }

        $fee = round($iterations * ProfessionEconomyConfig::FEE_SELF_PER_ITERATION, 1);
        $wallet = round((float)($this->walletService->getWalletSummary($userId)['prognobaks'] ?? 0), 1);
        $available = round(max(0.0, $wallet - $reservedBeforeNewTask), 1);

        if ($available < $fee) {
            throw new \RuntimeException(
                'Недостаточно 🪙 для очереди: нужно ' . $fee
                . ', доступно ' . $available
                . ($reservedBeforeNewTask > 0 ? ' (зарезервировано ' . $reservedBeforeNewTask . ')' : '')
            );
        }
    }

    /**
     * @return array<string, int>
     */
    private function loadSelfMaterialMap(int $userId): array
    {
        $map = [];
        foreach ($this->professionRepository->getMaterialsByUserId($userId) as $row) {
            if (($row['UF_IS_PREMIUM'] ?? '') === 'Y') {
                continue;
            }

            $code = (string)($row['UF_MATERIAL_CODE'] ?? '');
            $qty = (int)($row['UF_QTY'] ?? 0);
            if ($code !== '' && $qty > 0) {
                $map[$code] = $qty;
            }
        }

        return $map;
    }

    /**
     * @return array<string, int>
     */
    private function loadGovMaterialMap(): array
    {
        $map = [];
        foreach ($this->professionRepository->getGovWarehouseQtyMap() as $code => $qty) {
            $qty = (int)$qty;
            if ($code !== '' && $qty > 0) {
                $map[(string)$code] = $qty;
            }
        }

        return $map;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadQueueRows(int $userId): array
    {
        return $this->economyRepository->getPremiumWorkQueueItemsForUser($userId, [
            PremiumWorkQueueConfig::STATUS_ACTIVE,
            PremiumWorkQueueConfig::STATUS_PENDING,
        ]);
    }

    /**
     * @param array<string, int> $selfMaterials
     * @param array<string, int> $govMaterials
     * @param array<string, mixed> $payload
     */
    private function applyFarmTaskToVirtualBalances(
        array &$selfMaterials,
        array &$govMaterials,
        array $payload,
        int $iterations
    ): void {
        if ($iterations <= 0) {
            return;
        }

        $professionCode = (string)($payload['profession_code'] ?? '');
        $workMode = (string)($payload['work_mode'] ?? ProfessionMaterialConfig::WORK_MODE_TREASURY);
        $definition = ProfessionMaterialConfig::getProfession($professionCode);
        if (!$definition) {
            return;
        }

        $outputCode = (string)($definition['output'] ?? '');
        $inputCode = ProfessionMaterialConfig::getProfessionInput($professionCode);
        $isProcessing = ProfessionMaterialConfig::isProcessingProfession($definition);

        if ($isProcessing && $inputCode) {
            if ($workMode === ProfessionMaterialConfig::WORK_MODE_TREASURY) {
                $govMaterials[$inputCode] = max(0, (int)($govMaterials[$inputCode] ?? 0) - $iterations);
            } else {
                $selfMaterials[$inputCode] = max(0, (int)($selfMaterials[$inputCode] ?? 0) - $iterations);
            }
        }

        if ($outputCode === '') {
            return;
        }

        if ($workMode === ProfessionMaterialConfig::WORK_MODE_TREASURY) {
            $govMaterials[$outputCode] = (int)($govMaterials[$outputCode] ?? 0) + $iterations;
        } else {
            $selfMaterials[$outputCode] = (int)($selfMaterials[$outputCode] ?? 0) + $iterations;
        }
    }

    /**
     * @param array<string, int> $selfMaterials
     * @param array<string, int> $govMaterials
     */
    private function resolveMaxForSingleTask(
        array $selfMaterials,
        array $govMaterials,
        string $professionCode,
        string $workMode,
        int $requestedIterations
    ): int {
        $inputCode = ProfessionMaterialConfig::getProfessionInput($professionCode);
        if (!$inputCode) {
            return $requestedIterations;
        }

        $available = $workMode === ProfessionMaterialConfig::WORK_MODE_TREASURY
            ? (int)($govMaterials[$inputCode] ?? 0)
            : (int)($selfMaterials[$inputCode] ?? 0);

        if ($available <= 0) {
            return 0;
        }

        return min($requestedIterations, $available);
    }

    /**
     * @param array{
     *   materials_self: array<string, int>,
     *   materials_gov: array<string, int>,
     *   albums: int,
     *   reserved_prognobaks: float
     * } $state
     * @param array<string, mixed> $payload
     */
    public function simulateExchangeBuy(array &$state, array $payload): void
    {
        $kind = (string)($payload['kind'] ?? '');
        $code = (string)($payload['code'] ?? '');
        $category = (string)($payload['category'] ?? '');
        $qty = max(0, (int)($payload['qty'] ?? 0));
        if ($qty <= 0 || $code === '') {
            return;
        }

        if ($kind === ExchangeConfig::KIND_MATERIAL) {
            $state['materials_self'][$code] = (int)($state['materials_self'][$code] ?? 0) + $qty;
        }

        $cost = $this->estimateBuyCost($kind, $code, $category, $qty);
        $state['reserved_prognobaks'] = round((float)$state['reserved_prognobaks'] + $cost, 1);
    }

    /**
     * @param array{
     *   materials_self: array<string, int>,
     *   materials_gov: array<string, int>,
     *   albums: int,
     *   reserved_prognobaks: float
     * } $state
     */
    public function simulateProfessionCraft(array &$state, string $recipeCode): void
    {
        $definition = ProfessionRecipeConfig::getCraftDefinition($recipeCode);
        if (!$definition) {
            return;
        }

        foreach ($definition['inputs'] ?? [] as $input) {
            $code = (string)($input['code'] ?? '');
            $qty = max(1, (int)($input['qty'] ?? 1));
            if ($code === '' || (string)($input['source'] ?? 'material') !== 'material') {
                continue;
            }

            $state['materials_self'][$code] = max(0, (int)($state['materials_self'][$code] ?? 0) - $qty);
        }

        foreach ($definition['outputs'] ?? [] as $output) {
            $code = (string)($output['code'] ?? '');
            $qty = max(1, (int)($output['qty'] ?? 1));
            if ($code === '' || (string)($output['source'] ?? 'material') !== 'material') {
                continue;
            }

            $state['materials_self'][$code] = (int)($state['materials_self'][$code] ?? 0) + $qty;
        }

        $state['reserved_prognobaks'] = round(
            (float)$state['reserved_prognobaks'] + (int)($definition['work_cost'] ?? ProfessionRecipeConfig::WORK_COST),
            1
        );
    }

    private function estimateBuyCost(string $kind, string $code, string $category, int $qty): float
    {
        if ($qty <= 0) {
            return 0.0;
        }

        $listings = $this->economyRepository->findActiveExchangeListingsForSku(
            $kind,
            $code,
            $category,
            0,
            ''
        );

        usort($listings, static function (array $a, array $b): int {
            $priceCmp = ((float)($a['UF_PRICE_PER_UNIT'] ?? 0)) <=> ((float)($b['UF_PRICE_PER_UNIT'] ?? 0));
            if ($priceCmp !== 0) {
                return $priceCmp;
            }

            return ((int)($a['ID'] ?? 0)) <=> ((int)($b['ID'] ?? 0));
        });

        $remaining = $qty;
        $cost = 0.0;
        foreach ($listings as $listing) {
            if ($remaining <= 0) {
                break;
            }

            $available = (int)($listing['UF_QTY_REMAINING'] ?? 0);
            if ($available <= 0) {
                continue;
            }

            $take = min($available, $remaining);
            $cost = round($cost + $take * (float)($listing['UF_PRICE_PER_UNIT'] ?? 0), 1);
            $remaining -= $take;
        }

        if ($remaining > 0) {
            $nominal = $kind === ExchangeConfig::KIND_MATERIAL
                ? ExchangeNominalConfig::getMaterialNominal($code)
                : ExchangeNominalConfig::getLootNominal($code, $category);
            $cost = round($cost + $remaining * $nominal, 1);
        }

        return $cost;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed>|null $activeSession
     * @param array<string, mixed> $payload
     */
    private function resolveFarmTaskIterations(array $row, ?array $activeSession, array $payload): int
    {
        $iterations = (int)($payload['iterations'] ?? 0);
        if ($iterations <= 0) {
            $iterations = ProfessionEconomyConfig::FREE_ITERATIONS_PER_SESSION;
        }

        $status = (string)($row['UF_STATUS'] ?? '');
        $sessionId = (int)($row['UF_SESSION_ID'] ?? 0);
        if ($status === PremiumWorkQueueConfig::STATUS_ACTIVE
            && $activeSession
            && $sessionId === (int)($activeSession['ID'] ?? 0)
        ) {
            $total = (int)($activeSession['UF_ITERATIONS_TOTAL'] ?? 0);
            $done = (int)($activeSession['UF_ITERATIONS_DONE'] ?? 0);

            return max(0, $total - $done);
        }

        return $iterations;
    }

    /**
     * @param array{
     *   materials_self: array<string, int>,
     *   materials_gov: array<string, int>,
     *   albums: int,
     *   reserved_prognobaks: float
     * } $state
     * @param array<string, mixed> $row
     * @param array<string, mixed>|null $activeSession
     */
    private function simulateQueueRow(array &$state, array $row, ?array $activeSession): void
    {
        $taskType = (string)($row['UF_TASK_TYPE'] ?? '');
        $payload = $this->decodePayload((string)($row['UF_PAYLOAD_JSON'] ?? ''));

        if ($taskType === PremiumWorkQueueConfig::TASK_FARM) {
            $iterations = $this->resolveFarmTaskIterations($row, $activeSession, $payload);
            $this->simulateFarmTask($state, $payload, $iterations);

            return;
        }

        if ($taskType === PremiumWorkQueueConfig::TASK_ALBUM_CRAFT) {
            $this->simulateAlbumCraft($state);

            return;
        }

        if ($taskType === PremiumWorkQueueConfig::TASK_PROFESSION_CRAFT) {
            $this->simulateProfessionCraft($state, (string)($payload['recipe_code'] ?? ''));

            return;
        }

        if ($taskType === PremiumWorkQueueConfig::TASK_EXCHANGE_BUY) {
            $this->simulateExchangeBuy($state, $payload);

            return;
        }

        if ($taskType === PremiumWorkQueueConfig::TASK_EXCHANGE_LIST) {
            $this->simulateExchangeList($state, $payload);
        }
    }

    private function loadSellableAlbumQty(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        return (new ExchangeInventoryService($this->economyRepository))->getAvailableQty(
            $userId,
            ExchangeConfig::KIND_LOOT,
            AlbumConfig::ITEM_CODE,
            ChestLootConfig::CATEGORY_ALBUM
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function decodePayload(string $json): array
    {
        if ($json === '') {
            return [];
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array{
     *   materials_self: array<string, int>,
     *   materials_gov: array<string, int>,
     *   reserved_prognobaks: float,
     *   wallet_prognobaks: float,
     *   wallet_available_self_farm: float
     * }
     */
    private function emptyPreview(): array
    {
        return [
            'materials_self' => [],
            'materials_gov' => [],
            'albums' => 0,
            'reserved_prognobaks' => 0.0,
            'wallet_prognobaks' => 0.0,
            'wallet_available_self_farm' => 0.0,
        ];
    }
}
