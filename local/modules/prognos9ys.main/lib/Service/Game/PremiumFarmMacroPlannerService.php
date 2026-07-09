<?php

namespace Prognos9ys\Main\Service\Game;

use Prognos9ys\Main\Model\Repository\GameEconomyRepository;
use Prognos9ys\Main\Model\Repository\ProfessionRepository;

/**
 * Планировщик макросов Premium: цепочки добыча/покупка → обработка → крафт → продажа.
 */
class PremiumFarmMacroPlannerService
{
    private const MAX_MACRO_BATCHES = 10;

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

    /** @var array<string, string> */
    private const RAW_GATHER = [
        'stone' => 'quarryman',
        'log' => 'woodcutter',
        'ore' => 'miner',
        'sand' => 'sandgatherer',
        'cotton' => 'cottongatherer',
    ];

    private const SOURCE_GATHER = 'gather';
    private const SOURCE_BUY = 'buy';

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
        if ($macroType === 'album') {
            $tasks = $this->buildAlbumMacro($userId, $options);
        } elseif ($macroType === 'profession') {
            $tasks = $this->buildProfessionMacro($userId, $options);
        } elseif ($macroType === 'recipe') {
            $tasks = $this->buildRecipeMacro($userId, $options);
        } else {
            throw new \InvalidArgumentException('Неизвестный макрос: ' . $macroType);
        }

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

        $batches = max(1, min(self::MAX_MACRO_BATCHES, (int)($options['batches'] ?? 1)));
        $sell = (bool)($options['sell'] ?? false);
        $sellMode = (string)($options['sell_mode'] ?? 'listing');
        if (!in_array($sellMode, ['listing', 'consign'], true)) {
            $sellMode = 'listing';
        }
        $source = $this->resolveSource($options);

        $planksNeed = AlbumConfig::RECIPE_PLANK * $batches;
        $clothNeed = AlbumConfig::RECIPE_CLOTH * $batches;
        $craftProfession = $this->resolveAlbumCraftProfession($userId);

        $state = $this->projection->createVirtualState($userId);
        $tasks = [];

        $this->planMaterialNeed($userId, $state, $tasks, 'plank', $planksNeed, $source);
        $this->planMaterialNeed($userId, $state, $tasks, 'cloth', $clothNeed, $source);

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

        $outputQty = max(1, min(self::MAX_MACRO_BATCHES, (int)($options['output_qty'] ?? 0)));
        if ($outputQty <= 0) {
            $outputQty = max(1, min(self::MAX_MACRO_BATCHES, (int)($options['iterations'] ?? 1)));
        }

        $outputCode = (string)($definition['output'] ?? '');
        if ($outputCode === '') {
            throw new \RuntimeException('У профессии нет продукта');
        }

        $source = $this->resolveSource($options);
        $state = $this->projection->createVirtualState($userId);
        $tasks = [];

        if (($definition['type'] ?? '') === 'gather') {
            if ($source === self::SOURCE_BUY) {
                $this->planMaterialBuy($userId, $state, $tasks, $outputCode, $outputQty);
            } else {
                $this->appendFarmChunks($userId, $tasks, $state, $professionCode, $outputQty);
            }
        } else {
            $this->planMaterialNeed($userId, $state, $tasks, $outputCode, $outputQty, $source);
        }

        $this->assertMacroCoinReserve($userId, $state);

        return $tasks;
    }

    /**
     * @param array<string, mixed> $options
     * @return array<int, array{task_type:string, payload:array<string, mixed>}>
     */
    private function buildRecipeMacro(int $userId, array $options): array
    {
        $recipeCode = trim((string)($options['recipe_code'] ?? ''));
        if (!ProfessionRecipeConfig::isCraftableViaService($recipeCode)) {
            throw new \InvalidArgumentException('Некорректный рецепт');
        }

        $definition = ProfessionRecipeConfig::getCraftDefinition($recipeCode);
        if (!$definition) {
            throw new \RuntimeException('Крафт для рецепта не настроен');
        }

        if (!$this->economyRepository->hasLearnedRecipe($userId, $recipeCode)) {
            throw new \RuntimeException('Сначала изучите рецепт');
        }

        $professionCode = (string)($definition['profession'] ?? '');
        if (!$this->professionRepository->getProfessionByUserAndCode($userId, $professionCode)) {
            throw new \RuntimeException('Профессия не изучена');
        }

        $batches = max(1, min(self::MAX_MACRO_BATCHES, (int)($options['batches'] ?? 1)));
        $source = $this->resolveSource($options);
        $sell = (bool)($options['sell'] ?? false);
        $sellMode = (string)($options['sell_mode'] ?? 'listing');
        if (!in_array($sellMode, ['listing', 'consign'], true)) {
            $sellMode = 'listing';
        }

        $state = $this->projection->createVirtualState($userId);
        $tasks = [];

        foreach ($definition['inputs'] ?? [] as $input) {
            $code = (string)($input['code'] ?? '');
            $need = max(1, (int)($input['qty'] ?? 1)) * $batches;
            $inputSource = (string)($input['source'] ?? 'material');
            $premium = !empty($input['premium']);
            if ($code === '') {
                continue;
            }

            $this->planInputNeed($userId, $state, $tasks, $code, $need, $source, $inputSource, $premium);
        }

        for ($i = 0; $i < $batches; $i++) {
            $tasks[] = [
                'task_type' => PremiumWorkQueueConfig::TASK_PROFESSION_CRAFT,
                'payload' => [
                    'recipe_code' => $recipeCode,
                    'profession_code' => $professionCode,
                ],
            ];
            $this->projection->simulateProfessionCraft($state, $recipeCode);
        }

        if ($sell) {
            foreach ($definition['outputs'] ?? [] as $output) {
                $code = (string)($output['code'] ?? '');
                $qty = max(1, (int)($output['qty'] ?? 1)) * $batches;
                $outputSource = (string)($output['source'] ?? 'material');
                if ($code === '') {
                    continue;
                }

                $listing = $this->buildSellListingPayload($code, $qty, $outputSource);
                if ($listing === null) {
                    continue;
                }

                $listing['sell_mode'] = $sellMode;
                $tasks[] = [
                    'task_type' => PremiumWorkQueueConfig::TASK_EXCHANGE_LIST,
                    'payload' => $listing,
                ];
            }
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
    private function planMaterialNeed(
        int $userId,
        array &$state,
        array &$tasks,
        string $code,
        int $qtyNeeded,
        string $source
    ): void {
        $have = (int)($state['materials_self'][$code] ?? 0);
        $deficit = max(0, $qtyNeeded - $have);
        if ($deficit <= 0) {
            return;
        }

        if ($source === self::SOURCE_BUY) {
            $this->planMaterialBuy($userId, $state, $tasks, $code, $deficit);

            return;
        }

        $this->planMaterialGather($userId, $state, $tasks, $code, $deficit);
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
    private function planInputNeed(
        int $userId,
        array &$state,
        array &$tasks,
        string $code,
        int $qtyNeeded,
        string $source,
        string $inputSource,
        bool $premium = false
    ): void {
        if ($inputSource !== 'material') {
            if ($source === self::SOURCE_BUY) {
                $have = $this->countLootInVirtualState($state, $code, $inputSource);
                $deficit = max(0, $qtyNeeded - $have);
                if ($deficit > 0) {
                    $this->planLootBuy($userId, $state, $tasks, $code, $deficit, $inputSource);
                }
            }

            return;
        }

        if ($premium) {
            if ($source === self::SOURCE_BUY) {
                $have = (int)($state['materials_self'][$code] ?? 0);
                $deficit = max(0, $qtyNeeded - $have);
                if ($deficit > 0) {
                    $this->planMaterialBuy($userId, $state, $tasks, $code, $deficit, true);
                }
            }

            return;
        }

        $this->planMaterialNeed($userId, $state, $tasks, $code, $qtyNeeded, $source);
    }

    /**
     * @param array{
     *   materials_self: array<string, int>,
     *   materials_gov: array<string, int>,
     *   albums: int,
     *   reserved_prognobaks: float
     * } $state
     */
    private function countLootInVirtualState(array $state, string $code, string $source): int
    {
        if ($source === 'equipment') {
            return 0;
        }

        return 0;
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
    private function planMaterialGather(
        int $userId,
        array &$state,
        array &$tasks,
        string $code,
        int $deficit
    ): void {
        if ($deficit <= 0) {
            return;
        }

        if (isset(self::OUTPUT_CHAINS[$code])) {
            $this->planMaterialOutputChain($userId, $state, $tasks, $code, $deficit);

            return;
        }

        if (isset(self::RAW_GATHER[$code])) {
            $this->appendFarmChunks($userId, $tasks, $state, self::RAW_GATHER[$code], $deficit);

            return;
        }

        $subRecipe = ProfessionRecipeConfig::findRecipeCodeByOutputCode($code);
        if ($subRecipe !== null) {
            $this->planSubRecipeProduction($userId, $state, $tasks, $subRecipe, $deficit);

            return;
        }

        throw new \RuntimeException('Нет цепочки для материала: ' . $code);
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
    private function planSubRecipeProduction(
        int $userId,
        array &$state,
        array &$tasks,
        string $recipeCode,
        int $outputQty
    ): void {
        $definition = ProfessionRecipeConfig::getCraftDefinition($recipeCode);
        if (!$definition) {
            throw new \RuntimeException('Подрецепт не найден');
        }

        $professionCode = (string)($definition['profession'] ?? '');

        foreach ($definition['inputs'] ?? [] as $input) {
            $code = (string)($input['code'] ?? '');
            $need = max(1, (int)($input['qty'] ?? 1)) * $outputQty;
            if ($code === '' || (string)($input['source'] ?? 'material') !== 'material') {
                continue;
            }

            $this->planMaterialGather($userId, $state, $tasks, $code, $need);
        }

        for ($i = 0; $i < $outputQty; $i++) {
            $tasks[] = [
                'task_type' => PremiumWorkQueueConfig::TASK_PROFESSION_CRAFT,
                'payload' => [
                    'recipe_code' => $recipeCode,
                    'profession_code' => $professionCode,
                ],
            ];
            $this->projection->simulateProfessionCraft($state, $recipeCode);
        }
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
    private function planMaterialOutputChain(
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
                $this->planMaterialGather($userId, $state, $tasks, $inputCode, $inputDeficit);
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
    private function planMaterialBuy(
        int $userId,
        array &$state,
        array &$tasks,
        string $code,
        int $qty,
        bool $premium = false
    ): void {
        if ($qty <= 0) {
            return;
        }

        $payload = [
            'kind' => ExchangeConfig::KIND_MATERIAL,
            'code' => $code,
            'category' => $premium
                ? ExchangeConfig::MATERIAL_CATEGORY_PREMIUM
                : ExchangeConfig::MATERIAL_CATEGORY_NORMAL,
            'qty' => $qty,
            'event_id' => 0,
            'team_code' => '',
            'listing_sort' => 'price',
        ];

        $tasks[] = [
            'task_type' => PremiumWorkQueueConfig::TASK_EXCHANGE_BUY,
            'payload' => $payload,
        ];
        $this->projection->simulateExchangeBuy($state, $payload);
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
    private function planLootBuy(
        int $userId,
        array &$state,
        array &$tasks,
        string $code,
        int $qty,
        string $source
    ): void {
        if ($qty <= 0) {
            return;
        }

        $category = $source === 'equipment'
            ? ChestLootConfig::CATEGORY_EQUIPMENT
            : ChestLootConfig::CATEGORY_ALBUM;

        $payload = [
            'kind' => ExchangeConfig::KIND_LOOT,
            'code' => $code,
            'category' => $category,
            'qty' => $qty,
            'event_id' => 0,
            'team_code' => '',
            'listing_sort' => 'price',
        ];

        $tasks[] = [
            'task_type' => PremiumWorkQueueConfig::TASK_EXCHANGE_BUY,
            'payload' => $payload,
        ];
        $this->projection->simulateExchangeBuy($state, $payload);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildSellListingPayload(string $code, int $qty, string $source): ?array
    {
        if ($qty <= 0) {
            return null;
        }

        if ($source === 'material' || ProfessionCraftedItemConfig::getStorage($code) === ProfessionCraftedItemConfig::STORAGE_MATERIAL) {
            $nominal = ExchangeNominalConfig::getMaterialNominal($code);

            return [
                'kind' => ExchangeConfig::KIND_MATERIAL,
                'code' => $code,
                'category' => ExchangeConfig::MATERIAL_CATEGORY_NORMAL,
                'qty' => $qty,
                'price_per_unit' => $nominal,
                'event_id' => 0,
                'team_code' => '',
            ];
        }

        if ($source === 'equipment') {
            $nominal = ProfessionCraftedItemConfig::getNominal($code);

            return [
                'kind' => ExchangeConfig::KIND_LOOT,
                'code' => $code,
                'category' => ChestLootConfig::CATEGORY_EQUIPMENT,
                'qty' => $qty,
                'price_per_unit' => $nominal,
                'event_id' => 0,
                'team_code' => '',
            ];
        }

        return null;
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

    /**
     * @param array<string, mixed> $options
     */
    private function resolveSource(array $options): string
    {
        $source = trim((string)($options['source'] ?? self::SOURCE_GATHER));

        return $source === self::SOURCE_BUY ? self::SOURCE_BUY : self::SOURCE_GATHER;
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
