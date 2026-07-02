<?php

namespace Prognos9ys\Main\Service\Game;

use Bitrix\Main\UserTable;
use Prognos9ys\Main\Model\Repository\GameEconomyRepository;
use Prognos9ys\Main\Model\Repository\ProfessionRepository;

class BotFarmService
{
    /** Макс. циклов «для себя» в модераторском массовом крафте (п.5). */
    public const SELF_PROCESS_MAX_ITERATIONS = 5;

    private ProfessionRepository $professionRepository;
    private ProfessionFarmService $farmService;

    public function __construct(
        ?ProfessionRepository $professionRepository = null,
        ?ProfessionFarmService $farmService = null
    ) {
        $this->professionRepository = $professionRepository ?? new ProfessionRepository();
        $this->farmService = $farmService ?? new ProfessionFarmService($this->professionRepository);
    }

    /**
     * @return int[]
     */
    public function listSeedUserIds(): array
    {
        $ids = [];
        $response = UserTable::getList([
            'filter' => [
                'LOGIC' => 'OR',
                ['%EMAIL' => '@prognos9ys.ru'],
                ['%LOGIN' => 'gk'],
                ['%LOGIN' => 'coach'],
                ['%LOGIN' => 'fanm'],
                ['%LOGIN' => 'fanf'],
                ['%LOGIN' => 'ruler'],
                ['%LOGIN' => 'cs2p_'],
                ['%LOGIN' => 'cs2c_'],
            ],
            'select' => ['ID', 'LOGIN', 'EMAIL'],
            'order' => ['ID' => 'ASC'],
        ]);

        while ($row = $response->fetch()) {
            if (!SeedUserGroupService::isSeedAccount($row)) {
                continue;
            }

            $id = (int)($row['ID'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Все пользователи с игровым кошельком (для массовых действий).
     *
     * @return int[]
     */
    public function listWalletUserIds(): array
    {
        $ids = [];
        foreach ((new GameEconomyRepository())->getAllWallets() as $wallet) {
            $userId = (int)($wallet['user_id'] ?? 0);
            if ($userId > 0) {
                $ids[] = $userId;
            }
        }

        return array_values(array_unique($ids));
    }

    public function isSeedUser(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $row = UserTable::getList([
            'filter' => ['=ID' => $userId],
            'select' => ['ID', 'LOGIN', 'EMAIL'],
            'limit' => 1,
        ])->fetch();

        return $row && SeedUserGroupService::isSeedAccount($row);
    }

    public function userHasGatherProfession(int $userId): bool
    {
        return count($this->professionRepository->getProfessionsByUserId($userId)) > 0;
    }

    /**
     * @return array{status:string,profession_code?:string,label?:string,message:string}
     */
    public function pickGatherProfessionIfMissing(int $userId, string $profile = BotProfessionPickConfig::DEFAULT_PROFILE): array
    {
        if ($userId <= 0) {
            return ['status' => 'skipped', 'message' => 'Некорректный пользователь'];
        }

        if ($this->userHasGatherProfession($userId)) {
            $existing = $this->professionRepository->getProfessionsByUserId($userId);
            $code = (string)($existing[0]['UF_PROFESSION_CODE'] ?? '');

            return [
                'status' => 'skipped',
                'profession_code' => $code,
                'message' => 'Профессия уже есть',
            ];
        }

        $code = BotProfessionPickConfig::pickGatheringCodeForUser($userId, $profile);
        $definition = ProfessionMaterialConfig::getProfession($code);
        $this->farmService->pickProfessions($userId, [$code]);

        return [
            'status' => 'success',
            'profession_code' => $code,
            'label' => $definition['label'] ?? $code,
            'message' => ($definition['label'] ?? $code) . ' (' . $code . ')',
        ];
    }

    /**
     * Вторая профессия (обработка) — только если ровно одна добывающая.
     *
     * @return array{status:string,profession_code?:string,label?:string,message:string}
     */
    public function pickProcessingProfessionIfSingleGather(int $userId): array
    {
        if ($userId <= 0) {
            return ['status' => 'skipped', 'message' => 'Некорректный пользователь'];
        }

        $professions = $this->professionRepository->getProfessionsByUserId($userId);
        if (count($professions) !== 1) {
            return ['status' => 'skipped', 'message' => 'Нужна ровно одна профессия'];
        }

        $existingCode = (string)($professions[0]['UF_PROFESSION_CODE'] ?? '');
        $existingDef = ProfessionMaterialConfig::getProfession($existingCode);
        if (!$existingDef || ($existingDef['type'] ?? '') !== 'gather') {
            return ['status' => 'skipped', 'message' => 'Единственная профессия не добыча'];
        }

        $code = BotProfessionPickConfig::pickProcessingCodeForUser($userId);
        $definition = ProfessionMaterialConfig::getProfession($code);
        try {
            $this->farmService->pickProfessions($userId, [$code]);
        } catch (\Throwable $exception) {
            return ['status' => 'failed', 'message' => $exception->getMessage()];
        }

        return [
            'status' => 'success',
            'profession_code' => $code,
            'label' => $definition['label'] ?? $code,
            'message' => ($definition['label'] ?? $code) . ' (' . $code . ')',
        ];
    }

    /**
     * Мгновенная смена на казну (все циклы сразу) — для модераторского массового действия.
     *
     * @return array{status:string,message:string,profession_code?:string,ticks?:int}
     */
    public function runInstantTreasuryGather(
        int $userId,
        int $iterations = 0,
        string $profile = BotProfessionPickConfig::DEFAULT_PROFILE
    ): array {
        if ($userId <= 0) {
            return ['status' => 'skipped', 'message' => 'Некорректный пользователь'];
        }

        if ($this->professionRepository->getActiveSessionByUserId($userId)) {
            return ['status' => 'skipped', 'message' => 'Уже идёт смена'];
        }

        if (!$this->userHasGatherProfession($userId)) {
            $picked = $this->pickGatherProfessionIfMissing($userId, $profile);
            if ($picked['status'] !== 'success') {
                return $picked;
            }
        }

        $professions = $this->professionRepository->getProfessionsByUserId($userId);
        $professionCode = (string)($professions[0]['UF_PROFESSION_CODE'] ?? '');
        if ($professionCode === '') {
            return ['status' => 'failed', 'message' => 'Нет профессии'];
        }

        $iterations = $iterations > 0
            ? min(ProfessionEconomyConfig::FREE_ITERATIONS_PER_SESSION, $iterations)
            : ProfessionEconomyConfig::FREE_ITERATIONS_PER_SESSION;

        return $this->runInstantTreasuryWork(
            $userId,
            $professionCode,
            $iterations,
            static fn(LaborExchangeService $laborService): bool => $laborService->hasOpenTreasuryGatherOrders()
        );
    }

    /**
     * Мгновенный крафт на казну по профессии обработки игрока.
     *
     * @return array{status:string,message:string,profession_code?:string,ticks?:int}
     */
    public function runInstantTreasuryCraft(int $userId, int $iterations = 0): array
    {
        if ($userId <= 0) {
            return ['status' => 'skipped', 'message' => 'Некорректный пользователь'];
        }

        if ($this->professionRepository->getActiveSessionByUserId($userId)) {
            return ['status' => 'skipped', 'message' => 'Уже идёт смена'];
        }

        $professionCode = $this->resolveProcessingProfessionCode($userId);
        if ($professionCode === '') {
            return ['status' => 'skipped', 'message' => 'Нет профессии обработки'];
        }

        $iterations = $iterations > 0
            ? min(ProfessionEconomyConfig::FREE_ITERATIONS_PER_SESSION, $iterations)
            : ProfessionEconomyConfig::FREE_ITERATIONS_PER_SESSION;

        return $this->runInstantTreasuryWork(
            $userId,
            $professionCode,
            $iterations,
            static fn(LaborExchangeService $laborService): bool => $laborService->hasOpenTreasuryProcessingOrders()
        );
    }

    public function getProcessingProfessionCode(int $userId): string
    {
        return $this->resolveProcessingProfessionCode($userId);
    }

    /**
     * @param callable(LaborExchangeService):bool $hasOtherCategoryOrders
     * @return array{status:string,message:string,profession_code?:string,ticks?:int,labor_order_id?:int}
     */
    private function runInstantTreasuryWork(
        int $userId,
        string $professionCode,
        int $iterations,
        callable $hasOtherCategoryOrders
    ): array {
        $definition = ProfessionMaterialConfig::getProfession($professionCode);
        $laborService = new LaborExchangeService(null, $this->professionRepository);
        $order = $laborService->findOpenTreasuryOrderForProfession($professionCode);

        if ($order !== null) {
            $claimIterations = min(
                $iterations,
                LaborExchangeConfig::MAX_CYCLES_PER_CLAIM,
                $laborService->getTreasuryOrderRemainingIterations($order)
            );
            if ($claimIterations <= 0) {
                return ['status' => 'skipped', 'message' => 'В заказе казны не осталось циклов'];
            }

            try {
                $laborService->claimOrder($userId, (int)$order['ID'], $claimIterations);
                $ticks = $this->farmService->forceRunAllSessionTicks($userId);

                return [
                    'status' => 'success',
                    'profession_code' => $professionCode,
                    'ticks' => $ticks,
                    'labor_order_id' => (int)$order['ID'],
                    'message' => ($definition['label'] ?? $professionCode)
                        . ': ' . $ticks . ' цикл. по заказу казны на бирже',
                ];
            } catch (\Throwable $e) {
                return ['status' => 'skipped', 'message' => $e->getMessage()];
            }
        }

        if ($hasOtherCategoryOrders($laborService)) {
            return [
                'status' => 'skipped',
                'message' => 'Нет заказа казны на бирже для ' . ($definition['label'] ?? $professionCode),
            ];
        }

        $payTotal = $iterations * ProfessionEconomyConfig::PAY_TREASURY_PER_ITERATION;
        $treasury = (new TreasuryService())->getSummary();
        if ((float)($treasury['prognobaks'] ?? 0) < $payTotal) {
            return [
                'status' => 'skipped',
                'message' => 'В казне мало 🪙 (нужно ' . $payTotal . ')',
            ];
        }

        try {
            $this->farmService->startWork(
                $userId,
                $professionCode,
                ProfessionMaterialConfig::WORK_MODE_TREASURY,
                $iterations
            );
        } catch (\Throwable $e) {
            return ['status' => 'skipped', 'message' => $e->getMessage()];
        }

        $ticks = $this->farmService->forceRunAllSessionTicks($userId);

        return [
            'status' => 'success',
            'profession_code' => $professionCode,
            'ticks' => $ticks,
            'message' => ($definition['label'] ?? $professionCode)
                . ': ' . $ticks . ' циклов на казну',
        ];
    }

    /**
     * План мгновенной обработки «для себя» (модератор п.5): сырьё с инвентаря + биржа по лучшей цене.
     *
     * @return array{
     *   eligible:bool,
     *   profession_code?:string,
     *   label?:string,
     *   input_code?:string,
     *   iterations?:int,
     *   inventory_input?:int,
     *   exchange_input?:int,
     *   buy_qty?:int,
     *   fee_coins?:float,
     *   buy_cost_estimate?:float,
     *   message:string,
     *   skip_reason?:string
     * }
     */
    public function previewSelfProcess(int $userId): array
    {
        if ($userId <= 0) {
            return ['eligible' => false, 'message' => '', 'skip_reason' => 'Некорректный пользователь'];
        }

        if ($this->professionRepository->getActiveSessionByUserId($userId)) {
            return ['eligible' => false, 'message' => '', 'skip_reason' => 'Уже идёт смена'];
        }

        $professionCode = $this->resolveProcessingProfessionCode($userId);
        if ($professionCode === '') {
            return ['eligible' => false, 'message' => '', 'skip_reason' => 'Нет профессии обработки'];
        }

        $definition = ProfessionMaterialConfig::getProfession($professionCode);
        $inputCode = ProfessionMaterialConfig::getProfessionInput($professionCode);
        if (!$definition || !$inputCode) {
            return ['eligible' => false, 'message' => '', 'skip_reason' => 'Нет цепочки обработки'];
        }

        $inventoryInput = $this->professionRepository->getUserMaterialQty($userId, $inputCode, false);
        $exchangeInput = $this->countExchangeMaterialQty($inputCode);
        $iterations = min(
            self::SELF_PROCESS_MAX_ITERATIONS,
            $inventoryInput + $exchangeInput
        );

        if ($iterations <= 0) {
            return [
                'eligible' => false,
                'message' => '',
                'skip_reason' => 'Нет сырья (' . ($definition['input_label'] ?? $inputCode) . ')',
            ];
        }

        $buyQty = max(0, $iterations - $inventoryInput);
        $buyCost = $this->estimateMaterialBuyCost($inputCode, $buyQty);
        $feeCoins = round($iterations * ProfessionEconomyConfig::FEE_SELF_PER_ITERATION, 1);
        $wallet = (new GameEconomyRepository())->getWalletByUserId($userId);
        $balance = round((float)($wallet['UF_PROGNOBAKS'] ?? 0), 1);
        $requiredCoins = round($buyCost + $feeCoins, 1);

        if ($balance < $requiredCoins) {
            return [
                'eligible' => false,
                'message' => '',
                'skip_reason' => 'Мало 🪙 (нужно ' . $requiredCoins . ')',
            ];
        }

        $label = (string)($definition['label'] ?? $professionCode);
        $hint = $label . ', ×' . $iterations;
        if ($buyQty > 0) {
            $hint .= ', купить ' . $buyQty . ' ' . ($definition['input_label'] ?? $inputCode);
        }

        return [
            'eligible' => true,
            'profession_code' => $professionCode,
            'label' => $label,
            'input_code' => $inputCode,
            'iterations' => $iterations,
            'inventory_input' => $inventoryInput,
            'exchange_input' => $exchangeInput,
            'buy_qty' => $buyQty,
            'fee_coins' => $feeCoins,
            'buy_cost_estimate' => $buyCost,
            'message' => $hint,
        ];
    }

    /**
     * Мгновенная обработка «для себя» с докупкой сырья на бирже (модератор п.5).
     *
     * @return array{
     *   status:string,
     *   message:string,
     *   profession_code?:string,
     *   iterations?:int,
     *   buy_qty?:int,
     *   ticks?:int
     * }
     */
    public function runInstantSelfProcess(int $userId): array
    {
        $preview = $this->previewSelfProcess($userId);
        if (!($preview['eligible'] ?? false)) {
            return [
                'status' => 'skipped',
                'message' => (string)($preview['skip_reason'] ?? 'Не подходит'),
            ];
        }

        $professionCode = (string)($preview['profession_code'] ?? '');
        $inputCode = (string)($preview['input_code'] ?? '');
        $iterations = (int)($preview['iterations'] ?? 0);
        $buyQty = (int)($preview['buy_qty'] ?? 0);
        $definition = ProfessionMaterialConfig::getProfession($professionCode);

        if ($buyQty > 0) {
            try {
                (new ExchangeService())->buy(
                    $userId,
                    ExchangeConfig::KIND_MATERIAL,
                    $inputCode,
                    $buyQty,
                    ExchangeConfig::MATERIAL_CATEGORY_NORMAL,
                    0,
                    '',
                    0,
                    'price'
                );
            } catch (\Throwable $exception) {
                return [
                    'status' => 'failed',
                    'message' => $exception->getMessage(),
                ];
            }
        }

        try {
            $this->farmService->startWork(
                $userId,
                $professionCode,
                ProfessionMaterialConfig::WORK_MODE_SELF,
                $iterations
            );
        } catch (\Throwable $exception) {
            return [
                'status' => 'failed',
                'message' => $exception->getMessage(),
            ];
        }

        $ticks = $this->farmService->forceRunAllSessionTicks($userId);
        $parts = [
            ($definition['label'] ?? $professionCode) . ': ' . $ticks . ' циклов для себя',
        ];
        if ($buyQty > 0) {
            $parts[] = 'куплено ' . $buyQty . ' ' . ($definition['input_label'] ?? $inputCode);
        }

        return [
            'status' => 'success',
            'profession_code' => $professionCode,
            'iterations' => $iterations,
            'buy_qty' => $buyQty,
            'ticks' => $ticks,
            'message' => implode(', ', $parts),
        ];
    }

    private function resolveProcessingProfessionCode(int $userId): string
    {
        $rows = $this->professionRepository->getProfessionsByUserId($userId);
        $processing = [];
        $gatherOutputs = [];

        foreach ($rows as $row) {
            $code = (string)($row['UF_PROFESSION_CODE'] ?? '');
            $definition = ProfessionMaterialConfig::getProfession($code);
            if (!$definition) {
                continue;
            }

            if (($definition['type'] ?? '') === 'process') {
                $processing[$code] = (int)($row['UF_SLOT_INDEX'] ?? 0);
                continue;
            }

            if (($definition['type'] ?? '') === 'gather') {
                $gatherOutputs[] = (string)($definition['output'] ?? '');
            }
        }

        if (!$processing) {
            return '';
        }

        $outputToProcessor = [
            'log' => 'carpenter',
            'stone' => 'stonemason',
            'ore' => 'smelter',
            'sand' => 'glassblower',
            'cotton' => 'weaver',
        ];

        foreach ($gatherOutputs as $output) {
            $processorCode = (string)($outputToProcessor[$output] ?? '');
            if ($processorCode !== '' && isset($processing[$processorCode])) {
                return $processorCode;
            }
        }

        asort($processing);

        return (string)array_key_first($processing);
    }

    private function countExchangeMaterialQty(string $materialCode): int
    {
        $materialCode = trim($materialCode);
        if ($materialCode === '') {
            return 0;
        }

        $total = 0;
        foreach ((new GameEconomyRepository())->findActiveExchangeListingsForSku(
            ExchangeConfig::KIND_MATERIAL,
            $materialCode,
            ExchangeConfig::MATERIAL_CATEGORY_NORMAL,
            0,
            ''
        ) as $listing) {
            $total += (int)($listing['UF_QTY_REMAINING'] ?? 0);
        }

        return $total;
    }

    private function estimateMaterialBuyCost(string $materialCode, int $qty): float
    {
        if ($qty <= 0) {
            return 0.0;
        }

        $listings = (new GameEconomyRepository())->findActiveExchangeListingsForSku(
            ExchangeConfig::KIND_MATERIAL,
            $materialCode,
            ExchangeConfig::MATERIAL_CATEGORY_NORMAL,
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

        return $cost;
    }
}
