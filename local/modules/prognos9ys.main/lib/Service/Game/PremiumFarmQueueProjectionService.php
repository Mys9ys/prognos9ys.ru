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

        $selfMaterials = $this->loadSelfMaterialMap($userId);
        $govMaterials = $this->loadGovMaterialMap();
        $queueRows = $this->loadQueueRows($userId);
        $activeSession = $this->professionRepository->getActiveSessionByUserId($userId);

        $reserved = 0.0;
        foreach ($queueRows as $row) {
            if ((string)($row['UF_TASK_TYPE'] ?? '') !== PremiumWorkQueueConfig::TASK_FARM) {
                continue;
            }

            $payload = $this->decodePayload((string)($row['UF_PAYLOAD_JSON'] ?? ''));
            $iterations = $this->resolveFarmTaskIterations($row, $activeSession, $payload);
            $this->applyFarmTaskToVirtualBalances($selfMaterials, $govMaterials, $payload, $iterations);

            if (($payload['work_mode'] ?? '') === ProfessionMaterialConfig::WORK_MODE_SELF) {
                $reserved = round(
                    $reserved + $iterations * ProfessionEconomyConfig::FEE_SELF_PER_ITERATION,
                    1
                );
            }
        }

        $wallet = $this->walletService->getWalletSummary($userId);
        $walletPrognobaks = round((float)($wallet['prognobaks'] ?? 0), 1);

        return [
            'materials_self' => $selfMaterials,
            'materials_gov' => $govMaterials,
            'reserved_prognobaks' => $reserved,
            'wallet_prognobaks' => $walletPrognobaks,
            'wallet_available_self_farm' => round(max(0.0, $walletPrognobaks - $reserved), 1),
        ];
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

        foreach ($queueRows as $row) {
            if ((string)($row['UF_TASK_TYPE'] ?? '') !== PremiumWorkQueueConfig::TASK_FARM) {
                continue;
            }

            $payload = $this->decodePayload((string)($row['UF_PAYLOAD_JSON'] ?? ''));
            $iterations = $this->resolveFarmTaskIterations($row, $activeSession, $payload);
            $this->applyFarmTaskToVirtualBalances($selfMaterials, $govMaterials, $payload, $iterations);
        }

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
            'reserved_prognobaks' => 0.0,
            'wallet_prognobaks' => 0.0,
            'wallet_available_self_farm' => 0.0,
        ];
    }
}
