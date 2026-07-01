<?php

namespace Prognos9ys\Main\Service\Game;

use Prognos9ys\Main\Model\Repository\GameEconomyRepository;
use Prognos9ys\Main\Model\Repository\ProfessionRepository;

/**
 * Планировщик макросов Premium: цепочки добыча → обработка → крафт → продажа.
 */
class PremiumFarmMacroPlannerService
{
    /** @var array<string, array{gather:string, process?:string, input?:string}> */
    private const OUTPUT_CHAINS = [
        'log' => ['gather' => 'woodcutter'],
        'stone' => ['gather' => 'quarryman'],
        'ore' => ['gather' => 'miner'],
        'sand' => ['gather' => 'sandgatherer'],
        'cotton' => ['gather' => 'cottongatherer'],
        'plank' => ['gather' => 'woodcutter', 'process' => 'carpenter', 'input' => 'log'],
        'block' => ['gather' => 'quarryman', 'process' => 'stonemason', 'input' => 'stone'],
        'ingot' => ['gather' => 'miner', 'process' => 'smelter', 'input' => 'ore'],
        'glass' => ['gather' => 'sandgatherer', 'process' => 'glassblower', 'input' => 'sand'],
        'cloth' => ['gather' => 'cottongatherer', 'process' => 'weaver', 'input' => 'cotton'],
    ];

    private ProfessionRepository $professionRepository;
    private GameEconomyRepository $economyRepository;
    private PremiumFarmQueueProjectionService $projection;
    private PremiumWorkQueueService $queueService;

    public function __construct(
        ?ProfessionRepository $professionRepository = null,
        ?GameEconomyRepository $economyRepository = null,
        ?PremiumFarmQueueProjectionService $projection = null,
        ?PremiumWorkQueueService $queueService = null
    ) {
        $this->professionRepository = $professionRepository ?? new ProfessionRepository();
        $this->economyRepository = $economyRepository ?? new GameEconomyRepository();
        $this->projection = $projection ?? new PremiumFarmQueueProjectionService(
            $this->professionRepository,
            $this->economyRepository
        );
        $this->queueService = $queueService ?? new PremiumWorkQueueService(
            $this->economyRepository,
            $this->professionRepository
        );
    }

    /**
     * @param array<string, mixed> $options
     * @return array{queued:int, tasks:array<int, array<string, mixed>>, state:array<string, mixed>}
     */
    public function planAndEnqueue(int $userId, string $macroType, array $options): array
    {
        if ($userId <= 0) {
            throw new \InvalidArgumentException('Некорректный пользователь');
        }

        $macroType = trim($macroType);
        $tasks = match ($macroType) {
            'album' => $this->buildAlbumMacro($userId, $options),
            'profession' => $this->buildProfessionMacro($userId, $options),
            default => throw new \InvalidArgumentException('Неизвестный макрос: ' . $macroType),
        };

        if (!$tasks) {
            throw new \RuntimeException('Не удалось составить план работ');
        }

        return $this->queueService->enqueueMany($userId, $tasks);
    }

    /**
     * @param array<string, mixed> $options
     * @return array<int, array{task_type:string, payload:array<string, mixed>}>
     */
    private function buildAlbumMacro(int $userId, array $options): array
    {
        if (!$this->economyRepository->hasLearnedRecipe($userId, AlbumConfig::RECIPE_ITEM_CODE)) {
            throw new \RuntimeException('Сначала изучите рецепт альбома');
        }

        $batches = max(1, min(5, (int)($options['batches'] ?? 1)));
        $sell = !array_key_exists('sell', $options) || (bool)$options['sell'];
        $sellMode = (string)($options['sell_mode'] ?? 'listing');
        if (!in_array($sellMode, ['listing', 'consign'], true)) {
            $sellMode = 'listing';
        }

        $planksNeed = AlbumConfig::RECIPE_PLANK * $batches;
        $clothNeed = AlbumConfig::RECIPE_CLOTH * $batches;
        $craftProfession = $this->resolveAlbumCraftProfession($userId);

        $state = $this->projection->createVirtualState($userId);
        $tasks = [];

        $this->planMaterialOutput($userId, $state, $tasks, 'plank', $planksNeed);
        $this->planMaterialOutput($userId, $state, $tasks, 'cloth', $clothNeed);

        for ($i = 0; $i < $batches; $i++) {
            $tasks[] = [
                'task_type' => PremiumWorkQueueConfig::TASK_ALBUM_CRAFT,
                'payload' => ['profession_code' => $craftProfession],
            ];
            $this->projection->simulateAlbumCraft($state);
        }

        if ($sell) {
            $albumQty = AlbumConfig::CRAFT_OUTPUT_COUNT * $batches;
            $nominal = ExchangeNominalConfig::getLootNominal(
                AlbumConfig::ITEM_CODE,
                ChestLootConfig::CATEGORY_ALBUM
            );
            $tasks[] = [
                'task_type' => PremiumWorkQueueConfig::TASK_EXCHANGE_LIST,
                'payload' => [
                    'kind' => ExchangeConfig::KIND_LOOT,
                    'code' => AlbumConfig::ITEM_CODE,
                    'category' => ChestLootConfig::CATEGORY_ALBUM,
                    'qty' => $albumQty,
                    'price_per_unit' => $nominal,
                    'event_id' => 0,
                    'team_code' => '',
                    'sell_mode' => $sellMode,
                ],
            ];
        }

        $this->assertMacroCoinReserve($userId, $state);

        return $tasks;
    }

    /**
     * @param array<string, mixed> $options
     * @return array<int, array{task_type:string, payload:array<string, mixed>}>
     */
    private function buildProfessionMacro(int $userId, array $options): array
    {
        $professionCode = trim((string)($options['profession_code'] ?? ''));
        if ($professionCode === '') {
            throw new \InvalidArgumentException('Укажите профессию');
        }

        $definition = ProfessionMaterialConfig::getProfession($professionCode);
        if (!$definition) {
            throw new \InvalidArgumentException('Неизвестная профессия');
        }

        if (!$this->professionRepository->getProfessionByUserAndCode($userId, $professionCode)) {
            throw new \RuntimeException('Профессия не изучена');
        }

        $outputQty = max(1, min(5, (int)($options['output_qty'] ?? 0)));
        if ($outputQty <= 0) {
            $outputQty = max(1, min(5, (int)($options['iterations'] ?? 1)));
        }

        $outputCode = (string)($definition['output'] ?? '');
        if ($outputCode === '') {
            throw new \RuntimeException('У профессии нет продукта');
        }

        $state = $this->projection->createVirtualState($userId);
        $tasks = [];

        if (($definition['type'] ?? '') === 'gather') {
            $this->appendFarmChunks($userId, $tasks, $state, $professionCode, $outputQty);
        } else {
            $this->planMaterialOutput($userId, $state, $tasks, $outputCode, $outputQty);
        }

        $this->assertMacroCoinReserve($userId, $state);

        return $tasks;
    }

    /**
     * @param array{
     *   materials_self: array<string, int>,
     *   materials_gov: array<string, int>,
     *   albums: int,
     *   reserved_prognobaks: float
     * } $state
     * @param array<int, array{task_type:string, payload:array<string, mixed>}> $tasks
     */
    private function planMaterialOutput(
        int $userId,
        array &$state,
        array &$tasks,
        string $outputCode,
        int $qtyNeeded
    ): void {
        $have = (int)($state['materials_self'][$outputCode] ?? 0);
        $deficit = max(0, $qtyNeeded - $have);
        if ($deficit <= 0) {
            return;
        }

        $chain = self::OUTPUT_CHAINS[$outputCode] ?? null;
        if (!$chain) {
            throw new \RuntimeException('Нет цепочки для материала: ' . $outputCode);
        }

        if (!empty($chain['process']) && !empty($chain['input'])) {
            $inputCode = (string)$chain['input'];
            $inputHave = (int)($state['materials_self'][$inputCode] ?? 0);
            $inputDeficit = max(0, $deficit - $inputHave);
            if ($inputDeficit > 0) {
                $this->appendFarmChunks(
                    $userId,
                    $tasks,
                    $state,
                    (string)$chain['gather'],
                    $inputDeficit
                );
            }
            $this->appendFarmChunks(
                $userId,
                $tasks,
                $state,
                (string)$chain['process'],
                $deficit
            );

            return;
        }

        $this->appendFarmChunks($userId, $tasks, $state, (string)$chain['gather'], $deficit);
    }

    /**
     * @param array{
     *   materials_self: array<string, int>,
     *   materials_gov: array<string, int>,
     *   albums: int,
     *   reserved_prognobaks: float
     * } $state
     * @param array<int, array{task_type:string, payload:array<string, mixed>}> $tasks
     */
    private function appendFarmChunks(
        int $userId,
        array &$tasks,
        array &$state,
        string $professionCode,
        int $totalIterations
    ): void {
        if ($totalIterations <= 0) {
            return;
        }

        if (!$this->professionRepository->getProfessionByUserAndCode($userId, $professionCode)) {
            $label = ProfessionMaterialConfig::getProfession($professionCode)['label'] ?? $professionCode;
            throw new \RuntimeException('Нужна профессия: ' . $label);
        }

        $remaining = $totalIterations;
        while ($remaining > 0) {
            $chunk = min(ProfessionEconomyConfig::FREE_ITERATIONS_PER_SESSION, $remaining);
            $payload = [
                'profession_code' => $professionCode,
                'work_mode' => ProfessionMaterialConfig::WORK_MODE_SELF,
                'iterations' => $chunk,
            ];
            $tasks[] = [
                'task_type' => PremiumWorkQueueConfig::TASK_FARM,
                'payload' => $payload,
            ];
            $this->projection->simulateFarmTask($state, $payload, $chunk);
            $remaining -= $chunk;
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
    private function assertMacroCoinReserve(int $userId, array $state): void
    {
        $this->projection->assertSelfFarmAffordable(
            $userId,
            0,
            (float)($state['reserved_prognobaks'] ?? 0)
        );

        $wallet = round(
            (float)(new WalletService($this->economyRepository))->getWalletSummary($userId)['prognobaks'] ?? 0,
            1
        );
        $reserved = round((float)($state['reserved_prognobaks'] ?? 0), 1);
        if ($reserved > $wallet) {
            throw new \RuntimeException(
                'Недостаточно 🪙 для макроса: нужно ' . $reserved
                . ', на кошельке ' . $wallet
            );
        }
    }

    private function resolveAlbumCraftProfession(int $userId): string
    {
        $xp = [];
        foreach (AlbumConfig::CRAFT_PROFESSION_CODES as $code) {
            $row = $this->professionRepository->getProfessionByUserAndCode($userId, $code);
            if ($row) {
                $xp[$code] = (float)($row['UF_XP'] ?? 0);
            }
        }

        if (!isset($xp['weaver']) && !isset($xp['carpenter'])) {
            throw new \RuntimeException('Нужен столяр или ткач для крафта альбомов');
        }
        if (!isset($xp['carpenter'])) {
            return 'weaver';
        }
        if (!isset($xp['weaver'])) {
            return 'carpenter';
        }
        if ($xp['weaver'] > $xp['carpenter']) {
            return 'carpenter';
        }

        return 'weaver';
    }
}
