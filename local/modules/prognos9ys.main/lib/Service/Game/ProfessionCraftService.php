<?php

namespace Prognos9ys\Main\Service\Game;

use Prognos9ys\Main\Model\Repository\GameEconomyRepository;
use Prognos9ys\Main\Model\Repository\ProfessionRepository;

class ProfessionCraftService
{
    private ProfessionRepository $professionRepository;
    private GameEconomyRepository $economyRepository;
    private WalletService $walletService;
    private LevelService $levelService;
    private ProfessionLevelRewardService $levelRewardService;

    public function __construct(
        ?ProfessionRepository $professionRepository = null,
        ?GameEconomyRepository $economyRepository = null,
        ?WalletService $walletService = null,
        ?LevelService $levelService = null,
        ?ProfessionLevelRewardService $levelRewardService = null
    ) {
        $this->professionRepository = $professionRepository ?? new ProfessionRepository();
        $this->economyRepository = $economyRepository ?? new GameEconomyRepository();
        $this->walletService = $walletService ?? new WalletService($this->economyRepository);
        $this->levelService = $levelService ?? new LevelService();
        $this->levelRewardService = $levelRewardService ?? new ProfessionLevelRewardService();
    }

    /**
     * @return array{
     *   recipes:array<int, array<string, mixed>>,
     *   wallet_prognobaks:float,
     *   has_active_session:bool
     * }
     */
    public function getCraftCatalog(int $userId): array
    {
        if ($userId <= 0) {
            return [
                'recipes' => [],
                'wallet_prognobaks' => 0.0,
                'has_active_session' => false,
            ];
        }

        $this->economyRepository->ensureLearnedRecipesSchema();
        $learned = $this->economyRepository->getLearnedRecipes($userId);
        $wallet = $this->walletService->getWalletSummary($userId);
        $hasActiveSession = $this->professionRepository->getActiveSessionByUserId($userId) !== null;
        $recipes = [];

        foreach ($learned as $recipeCode) {
            if (!ProfessionRecipeConfig::isCraftableViaService($recipeCode)) {
                continue;
            }

            $definition = ProfessionRecipeConfig::getCraftDefinition($recipeCode);
            if (!$definition) {
                continue;
            }

            $professionCode = (string)($definition['profession'] ?? '');
            if (!$this->professionRepository->getProfessionByUserAndCode($userId, $professionCode)) {
                continue;
            }

            $preview = $this->buildCraftPreview($userId, $definition, $hasActiveSession, (float)$wallet['prognobaks']);
            $recipes[] = $preview;
        }

        usort($recipes, static function (array $a, array $b): int {
            return strcmp((string)($a['label'] ?? ''), (string)($b['label'] ?? ''));
        });

        return [
            'recipes' => $recipes,
            'wallet_prognobaks' => round((float)$wallet['prognobaks'], 1),
            'has_active_session' => $hasActiveSession,
        ];
    }

    /**
     * @return array{
     *   recipe_code:string,
     *   crafted_qty:int,
     *   xp_gain:int,
     *   profession_code:string,
     *   profession_level_rewards:array<int, array<string, mixed>>,
     *   lines:array<int, array{text:string,status:string}>
     * }
     */
    public function craft(int $userId, string $recipeCode, string $professionCode): array
    {
        $definition = $this->validateCraftRequest($userId, $recipeCode, $professionCode);
        $preview = $this->buildCraftPreview(
            $userId,
            $definition,
            false,
            (float)$this->walletService->getWalletSummary($userId)['prognobaks']
        );

        if (empty($preview['can_craft'])) {
            throw new \RuntimeException((string)($preview['missing_reason'] ?? 'Нельзя выполнить крафт'));
        }

        $workCost = (int)($definition['work_cost'] ?? ProfessionRecipeConfig::WORK_COST);
        $this->consumeInputs($userId, $definition);
        $this->walletService->debit(
            $userId,
            GameEconomyConfig::CURRENCY_PROGNOBAKS,
            (float)$workCost,
            'profession_craft',
            'recipe',
            0
        );

        $craftedQty = $this->grantOutputs($userId, $definition);
        $xpGain = (int)($definition['craft_xp'] ?? ProfessionRecipeConfig::CRAFT_XP);
        $xpResult = $this->grantProfessionXp($userId, $professionCode, $xpGain);

        $lines = $this->buildCraftLines($definition, $craftedQty, $xpGain, $professionCode, $xpResult['level_rewards']);

        $this->economyRepository->incrementRecipeCraftRunCount($userId, $professionCode, $recipeCode);

        return [
            'recipe_code' => $recipeCode,
            'crafted_qty' => $craftedQty,
            'xp_gain' => $xpGain,
            'profession_code' => $professionCode,
            'profession_level_rewards' => $xpResult['level_rewards'],
            'lines' => $lines,
        ];
    }

    /**
     * Крафт исполнителем с выдачей продукта заказчику (заказ усадьбы на бирже).
     *
     * @return array{
     *   recipe_code:string,
     *   crafted_qty:int,
     *   xp_gain:int,
     *   profession_code:string,
     *   profession_level_rewards:array<int, array<string, mixed>>,
     *   work_cost_total:float
     * }
     */
    public function craftForRecipient(
        int $workerUserId,
        int $recipientUserId,
        string $recipeCode,
        string $professionCode,
        int $qty = 1
    ): array {
        if ($workerUserId <= 0 || $recipientUserId <= 0) {
            throw new \InvalidArgumentException('Некорректные участники заказа');
        }

        $qty = max(1, min(LaborExchangeConfig::MAX_ESTATE_CLAIM_QTY, $qty));
        $definition = $this->validateEstateOrderCraftDefinition($recipeCode, $professionCode);
        $professionRow = $this->professionRepository->getProfessionByUserAndCode($workerUserId, $professionCode);
        if (!$professionRow) {
            $label = (string)(ProfessionMaterialConfig::getProfession($professionCode)['label'] ?? $professionCode);
            throw new \RuntimeException('Нужна профессия: ' . $label);
        }

        $preview = $this->buildCraftPreview(
            $workerUserId,
            $definition,
            false,
            (float)$this->walletService->getWalletSummary($workerUserId)['prognobaks']
        );

        if (empty($preview['can_craft'])) {
            throw new \RuntimeException((string)($preview['missing_reason'] ?? 'Нельзя выполнить крафт'));
        }

        $workCost = (float)((int)($definition['work_cost'] ?? ProfessionRecipeConfig::WORK_COST) * $qty);
        $wallet = $this->walletService->getWalletSummary($workerUserId);
        if ((float)$wallet['prognobaks'] < $workCost) {
            throw new \RuntimeException('Нужно ' . $workCost . ' 🪙 за работу');
        }

        if (!$this->canCraftQty($workerUserId, $definition, $qty)) {
            throw new \RuntimeException('Недостаточно материалов для крафта ×' . $qty);
        }

        $craftedQty = 0;
        $xpGain = 0;
        $levelRewards = [];

        for ($i = 0; $i < $qty; $i++) {
            $this->consumeInputs($workerUserId, $definition);
            $craftedQty += $this->grantOutputs($recipientUserId, $definition);
            $iterationXp = (int)($definition['craft_xp'] ?? ProfessionRecipeConfig::CRAFT_XP);
            $xpGain += $iterationXp;
            $xpResult = $this->grantProfessionXp($workerUserId, $professionCode, $iterationXp);
            $levelRewards = array_merge($levelRewards, $xpResult['level_rewards']);
            $this->economyRepository->incrementRecipeCraftRunCount($workerUserId, $professionCode, $recipeCode);
        }

        if ($workCost > 0) {
            $this->walletService->debit(
                $workerUserId,
                GameEconomyConfig::CURRENCY_PROGNOBAKS,
                $workCost,
                'estate_order_craft_fee',
                'recipe',
                0
            );
        }

        return [
            'recipe_code' => $recipeCode,
            'crafted_qty' => $craftedQty,
            'xp_gain' => $xpGain,
            'profession_code' => $professionCode,
            'profession_level_rewards' => $levelRewards,
            'work_cost_total' => $workCost,
        ];
    }

    public function maxCraftableQty(int $userId, string $recipeCode, string $professionCode): int
    {
        return $this->resolveEstateOrderCraftEligibility($userId, $recipeCode, $professionCode)['max_qty'];
    }

    /**
     * @return array{max_qty:int, block_reason:string}
     */
    public function resolveEstateOrderCraftEligibility(int $userId, string $recipeCode, string $professionCode): array
    {
        if ($userId <= 0 || trim($recipeCode) === '' || trim($professionCode) === '') {
            return ['max_qty' => 0, 'block_reason' => 'Некорректный заказ'];
        }

        try {
            $definition = $this->validateEstateOrderCraftDefinition($recipeCode, $professionCode);
        } catch (\Throwable $e) {
            return ['max_qty' => 0, 'block_reason' => $e->getMessage()];
        }

        $professionRow = $this->professionRepository->getProfessionByUserAndCode($userId, $professionCode);
        if (!$professionRow) {
            $label = (string)(ProfessionMaterialConfig::getProfession($professionCode)['label'] ?? $professionCode);

            return ['max_qty' => 0, 'block_reason' => 'Нужна профессия: ' . $label];
        }

        $wallet = (float)$this->walletService->getWalletSummary($userId)['prognobaks'];
        $preview = $this->buildCraftPreview($userId, $definition, false, $wallet);

        $max = LaborExchangeConfig::MAX_ESTATE_CLAIM_QTY;
        for ($qty = $max; $qty >= 1; $qty--) {
            if ($this->canCraftQty($userId, $definition, $qty)) {
                return ['max_qty' => $qty, 'block_reason' => ''];
            }
        }

        return [
            'max_qty' => 0,
            'block_reason' => (string)($preview['missing_reason'] ?? 'Недостаточно ресурсов для крафта'),
        ];
    }

    /**
     * @return array{
     *   recipe_code:string,
     *   profession:string,
     *   tier:string,
     *   work_cost:int,
     *   craft_xp:int,
     *   inputs:array<int, array{code:string,qty:int,source:string,premium?:bool}>,
     *   outputs:array<int, array{code:string,qty:int,source:string}>
     * }
     */
    private function validateEstateOrderCraftDefinition(string $recipeCode, string $professionCode): array
    {
        $recipeCode = trim($recipeCode);
        $professionCode = trim($professionCode);
        $definition = ProfessionRecipeConfig::getCraftDefinition($recipeCode);
        if (!$definition) {
            throw new \RuntimeException('Неизвестный рецепт');
        }

        if ($professionCode !== (string)($definition['profession'] ?? '')) {
            throw new \RuntimeException('Рецепт не соответствует профессии');
        }

        return $definition;
    }

    /**
     * @param array{
     *   recipe_code:string,
     *   profession:string,
     *   tier:string,
     *   work_cost:int,
     *   craft_xp:int,
     *   inputs:array<int, array{code:string,qty:int,source:string,premium?:bool}>,
     *   outputs:array<int, array{code:string,qty:int,source:string}>
     * } $definition
     */
    private function canCraftQty(int $userId, array $definition, int $qty): bool
    {
        if ($qty <= 0) {
            return false;
        }

        foreach ($definition['inputs'] ?? [] as $input) {
            $code = (string)($input['code'] ?? '');
            $need = max(1, (int)($input['qty'] ?? 1)) * $qty;
            $source = (string)($input['source'] ?? 'material');
            $premium = !empty($input['premium']);

            if ($source === 'material') {
                if ($this->professionRepository->getUserMaterialQty($userId, $code, $premium) < $need) {
                    return false;
                }

                continue;
            }

            $category = $source === 'equipment'
                ? ChestLootConfig::CATEGORY_EQUIPMENT
                : ChestLootConfig::CATEGORY_ALBUM;
            if ($this->economyRepository->getEventAgnosticLootItemCount($userId, $code, $category) < $need) {
                return false;
            }
        }

        $workCost = (float)((int)($definition['work_cost'] ?? ProfessionRecipeConfig::WORK_COST) * $qty);
        $wallet = $this->walletService->getWalletSummary($userId);

        return (float)$wallet['prognobaks'] >= $workCost;
    }

    /**
     * @return array{
     *   recipe_code:string,
     *   copied_qty:int,
     *   xp_gain:int,
     *   profession_code:string,
     *   profession_level_rewards:array<int, array<string, mixed>>,
     *   lines:array<int, array{text:string,status:string}>
     * }
     */
    public function copyRecipe(int $userId, string $recipeCode, string $professionCode): array
    {
        if ($userId <= 0) {
            throw new \InvalidArgumentException('Некорректный пользователь');
        }

        $recipeCode = trim($recipeCode);
        $professionCode = trim($professionCode);
        if (!ProfessionRecipeConfig::isKnownRecipe($recipeCode)) {
            throw new \InvalidArgumentException('Неизвестный рецепт');
        }

        $expectedProfession = ProfessionRecipeConfig::getRecipeProfession($recipeCode);
        if ($professionCode !== $expectedProfession) {
            throw new \RuntimeException('Копировать рецепт может только его профессия');
        }

        if ($this->professionRepository->getActiveSessionByUserId($userId)) {
            throw new \RuntimeException('Сначала завершите или остановите текущую смену');
        }

        $professionRow = $this->professionRepository->getProfessionByUserAndCode($userId, $professionCode);
        if (!$professionRow) {
            throw new \RuntimeException('Профессия не изучена');
        }

        $this->economyRepository->ensureLearnedRecipesSchema();
        if (!$this->economyRepository->hasLearnedRecipe($userId, $recipeCode)) {
            throw new \RuntimeException('Сначала изучите рецепт');
        }

        $cleanScrollHave = $this->professionRepository->getUserMaterialQty($userId, 'clean_scroll', false);
        if ($cleanScrollHave < 1) {
            throw new \RuntimeException('Нужен чистый свиток ×1 (есть ' . $cleanScrollHave . ')');
        }

        $workCost = (float)ProfessionRecipeConfig::COPY_WORK_COST;
        $wallet = $this->walletService->getWalletSummary($userId);
        if ((float)$wallet['prognobaks'] < $workCost) {
            throw new \RuntimeException('Нужно ' . $workCost . ' 🪙 за работу');
        }

        $this->professionRepository->consumeUserMaterialQty($userId, 'clean_scroll', 1, false);
        $this->walletService->debit(
            $userId,
            GameEconomyConfig::CURRENCY_PROGNOBAKS,
            $workCost,
            'profession_recipe_copy',
            'recipe',
            0
        );

        $this->economyRepository->incrementLootItem(
            $userId,
            ChestLootConfig::LOOT_EVENT_GLOBAL,
            $recipeCode,
            ChestLootConfig::CATEGORY_RECIPE,
            1,
            'N'
        );

        $xpGain = ProfessionRecipeConfig::COPY_XP;
        $xpResult = $this->grantProfessionXp($userId, $professionCode, $xpGain);
        $professionLabel = ProfessionMaterialConfig::getProfession($professionCode)['label'] ?? $professionCode;

        $lines = [
            ['text' => 'Списано: чистый свиток ×1, работа ' . $workCost . ' 🪙', 'status' => 'ok'],
            ['text' => ProfessionRecipeConfig::getRecipeLabel($recipeCode) . ' ×1', 'status' => 'ok'],
            ['text' => $professionLabel . ': +' . $xpGain . ' опыта', 'status' => 'ok'],
        ];

        foreach ($xpResult['level_rewards'] as $reward) {
            $lines[] = $this->formatLevelRewardLine($reward);
        }

        $this->economyRepository->incrementRecipeCopyRunCount($userId);

        return [
            'recipe_code' => $recipeCode,
            'copied_qty' => 1,
            'xp_gain' => $xpGain,
            'profession_code' => $professionCode,
            'profession_level_rewards' => $xpResult['level_rewards'],
            'lines' => $lines,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCraftPreview(
        int $userId,
        array $definition,
        bool $hasActiveSession,
        float $walletPrognobaks
    ): array {
        $recipeCode = (string)($definition['recipe_code'] ?? '');
        $workCost = (int)($definition['work_cost'] ?? ProfessionRecipeConfig::WORK_COST);
        $inputs = [];
        $missingReason = '';

        foreach ($definition['inputs'] ?? [] as $input) {
            $code = (string)($input['code'] ?? '');
            $need = max(1, (int)($input['qty'] ?? 1));
            $source = (string)($input['source'] ?? 'material');
            $premium = !empty($input['premium']);
            $have = $this->resolveInputQty($userId, $code, $source, $premium);

            $inputs[] = [
                'code' => $code,
                'label' => $this->resolveInputLabel($code, $source),
                'need' => $need,
                'have' => $have,
                'source' => $source,
                'premium' => $premium,
            ];

            if ($have < $need && $missingReason === '') {
                $missingReason = 'Не хватает: ' . $this->resolveInputLabel($code, $source)
                    . ' ×' . $need . ' (есть ' . $have . ')';
            }
        }

        $outputs = [];
        foreach ($definition['outputs'] ?? [] as $output) {
            $code = (string)($output['code'] ?? '');
            $qty = max(1, (int)($output['qty'] ?? 1));
            $source = (string)($output['source'] ?? 'material');
            $outputs[] = [
                'code' => $code,
                'label' => $this->resolveOutputLabel($code, $source),
                'qty' => $qty,
                'source' => $source,
            ];
        }

        if ($hasActiveSession && $missingReason === '') {
            $missingReason = 'Сначала завершите или остановите текущую смену';
        }
        if ($walletPrognobaks < $workCost && $missingReason === '') {
            $missingReason = 'Нужно ' . $workCost . ' 🪙 за работу (есть ' . round($walletPrognobaks, 1) . ')';
        }

        return [
            'code' => $recipeCode,
            'label' => ProfessionRecipeConfig::getRecipeLabel($recipeCode),
            'profession' => (string)($definition['profession'] ?? ''),
            'profession_label' => ProfessionMaterialConfig::getProfession((string)($definition['profession'] ?? ''))['label']
                ?? (string)($definition['profession'] ?? ''),
            'tier' => (string)($definition['tier'] ?? ''),
            'work_cost' => $workCost,
            'craft_xp' => (int)($definition['craft_xp'] ?? ProfessionRecipeConfig::CRAFT_XP),
            'inputs' => $inputs,
            'outputs' => $outputs,
            'can_craft' => $missingReason === '',
            'can_copy' => $this->canCopyRecipe($userId, $recipeCode, $walletPrognobaks),
            'missing_reason' => $missingReason,
        ];
    }

    private function canCopyRecipe(int $userId, string $recipeCode, float $walletPrognobaks): bool
    {
        if ($walletPrognobaks < ProfessionRecipeConfig::COPY_WORK_COST) {
            return false;
        }

        return $this->professionRepository->getUserMaterialQty($userId, 'clean_scroll', false) >= 1
            && $this->economyRepository->hasLearnedRecipe($userId, $recipeCode);
    }

    /**
     * @return array{
     *   recipe_code:string,
     *   profession:string,
     *   tier:string,
     *   work_cost:int,
     *   craft_xp:int,
     *   inputs:array<int, array{code:string,qty:int,source:string,premium?:bool}>,
     *   outputs:array<int, array{code:string,qty:int,source:string}>
     * }
     */
    private function validateCraftRequest(int $userId, string $recipeCode, string $professionCode): array
    {
        if ($userId <= 0) {
            throw new \InvalidArgumentException('Некорректный пользователь');
        }

        $recipeCode = trim($recipeCode);
        $professionCode = trim($professionCode);
        if (!ProfessionRecipeConfig::isCraftableViaService($recipeCode)) {
            throw new \InvalidArgumentException('Этот рецепт пока нельзя крафтить здесь');
        }

        $definition = ProfessionRecipeConfig::getCraftDefinition($recipeCode);
        if (!$definition) {
            throw new \InvalidArgumentException('Крафт для рецепта не настроен');
        }

        if ($professionCode !== (string)($definition['profession'] ?? '')) {
            throw new \RuntimeException('Этот рецепт относится к другой профессии');
        }

        if ($this->professionRepository->getActiveSessionByUserId($userId)) {
            throw new \RuntimeException('Сначала завершите или остановите текущую смену');
        }

        $professionRow = $this->professionRepository->getProfessionByUserAndCode($userId, $professionCode);
        if (!$professionRow) {
            throw new \RuntimeException('Профессия не изучена');
        }

        $this->economyRepository->ensureLearnedRecipesSchema();
        if (!$this->economyRepository->hasLearnedRecipe($userId, $recipeCode)) {
            throw new \RuntimeException('Сначала изучите рецепт в инвентаре');
        }

        return $definition;
    }

    /**
     * @param array{
     *   recipe_code:string,
     *   profession:string,
     *   tier:string,
     *   work_cost:int,
     *   craft_xp:int,
     *   inputs:array<int, array{code:string,qty:int,source:string,premium?:bool}>,
     *   outputs:array<int, array{code:string,qty:int,source:string}>
     * } $definition
     */
    private function consumeInputs(int $userId, array $definition): void
    {
        foreach ($definition['inputs'] ?? [] as $input) {
            $code = (string)($input['code'] ?? '');
            $qty = max(1, (int)($input['qty'] ?? 1));
            $source = (string)($input['source'] ?? 'material');
            $premium = !empty($input['premium']);

            if ($source === 'material') {
                $this->professionRepository->consumeUserMaterialQty($userId, $code, $qty, $premium);

                continue;
            }

            $category = $source === 'equipment'
                ? ChestLootConfig::CATEGORY_EQUIPMENT
                : ChestLootConfig::CATEGORY_ALBUM;
            $available = $this->economyRepository->getEventAgnosticLootItemCount($userId, $code, $category);
            if ($available < $qty) {
                throw new \RuntimeException('Недостаточно: ' . $this->resolveInputLabel($code, $source));
            }

            $this->economyRepository->decrementEventAgnosticLootItem($userId, $code, $category, $qty);
        }
    }

    /**
     * @param array{
     *   recipe_code:string,
     *   profession:string,
     *   tier:string,
     *   work_cost:int,
     *   craft_xp:int,
     *   inputs:array<int, array{code:string,qty:int,source:string,premium?:bool}>,
     *   outputs:array<int, array{code:string,qty:int,source:string}>
     * } $definition
     */
    private function grantOutputs(int $userId, array $definition): int
    {
        $totalQty = 0;

        foreach ($definition['outputs'] ?? [] as $output) {
            $code = (string)($output['code'] ?? '');
            $qty = max(1, (int)($output['qty'] ?? 1));
            $source = (string)($output['source'] ?? 'material');
            $isPremium = !empty($output['premium']);
            $totalQty += $qty;

            if ($source === 'material') {
                $this->professionRepository->addUserMaterialQty($userId, $code, $qty, $isPremium);

                continue;
            }

            $category = $source === 'equipment'
                ? ChestLootConfig::CATEGORY_EQUIPMENT
                : ChestLootConfig::CATEGORY_ALBUM;
            $this->economyRepository->incrementLootItem(
                $userId,
                ChestLootConfig::LOOT_EVENT_GLOBAL,
                $code,
                $category,
                $qty,
                'N'
            );
        }

        return $totalQty;
    }

    /**
     * @return array{level_rewards:array<int, array<string, mixed>>}
     */
    private function grantProfessionXp(int $userId, string $professionCode, int $xpGain): array
    {
        $professionRow = $this->professionRepository->getProfessionByUserAndCode($userId, $professionCode);
        if (!$professionRow) {
            return ['level_rewards' => []];
        }

        $playerLevel = (int)((new UserProgressService($this->economyRepository, $this->levelService))
            ->getSummary($userId)['level'] ?? 0);
        $xpResult = $this->professionRepository->addProfessionXp(
            (int)$professionRow['ID'],
            $xpGain,
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

        return ['level_rewards' => $levelRewards];
    }

    /**
     * @param array{
     *   recipe_code:string,
     *   profession:string,
     *   tier:string,
     *   work_cost:int,
     *   craft_xp:int,
     *   inputs:array<int, array{code:string,qty:int,source:string,premium?:bool}>,
     *   outputs:array<int, array{code:string,qty:int,source:string}>
     * } $definition
     * @param array<int, array<string, mixed>> $levelRewards
     * @return array<int, array{text:string,status:string}>
     */
    private function buildCraftLines(
        array $definition,
        int $craftedQty,
        int $xpGain,
        string $professionCode,
        array $levelRewards
    ): array {
        $spentParts = [];
        foreach ($definition['inputs'] ?? [] as $input) {
            $spentParts[] = $this->resolveInputLabel((string)$input['code'], (string)($input['source'] ?? 'material'))
                . ' ×' . (int)($input['qty'] ?? 1);
        }

        $lines = [
            ['text' => 'Списано: ' . implode(', ', $spentParts) . ', работа ' . (int)$definition['work_cost'] . ' 🪙', 'status' => 'ok'],
        ];

        foreach ($definition['outputs'] ?? [] as $output) {
            $lines[] = [
                'text' => $this->resolveOutputLabel((string)$output['code'], (string)($output['source'] ?? 'material'))
                    . ' ×' . (int)($output['qty'] ?? 1),
                'status' => 'ok',
            ];
        }

        $professionLabel = ProfessionMaterialConfig::getProfession($professionCode)['label'] ?? $professionCode;
        $lines[] = ['text' => $professionLabel . ': +' . $xpGain . ' опыта', 'status' => 'ok'];

        foreach ($levelRewards as $reward) {
            $lines[] = $this->formatLevelRewardLine($reward);
        }

        return $lines;
    }

    /**
     * @param array<string, mixed> $reward
     * @return array{text:string,status:string}
     */
    private function formatLevelRewardLine(array $reward): array
    {
        $bits = [($reward['profession_label'] ?? 'Профессия') . ' ур. ' . ($reward['level'] ?? '')];
        if ((float)($reward['prognobaks'] ?? 0) > 0) {
            $bits[] = '+' . $reward['prognobaks'] . ' 🪙';
        }
        if ((float)($reward['rublius'] ?? 0) > 0) {
            $bits[] = '+' . $reward['rublius'] . ' 💎';
        }
        if ((int)($reward['material_qty'] ?? 0) > 0) {
            $bits[] = '+' . $reward['material_qty'] . ' ' . ($reward['material_label'] ?? 'рес.');
        }
        if ((int)($reward['chests'] ?? 0) > 0) {
            $bits[] = '+' . $reward['chests'] . ' сунд. проф.';
        }
        if (!empty($reward['title'])) {
            $bits[] = (string)$reward['title'];
        }

        return ['text' => implode(' · ', $bits), 'status' => 'ok'];
    }

    private function resolveInputQty(int $userId, string $code, string $source, bool $premium): int
    {
        if ($source === 'material') {
            return $this->professionRepository->getUserMaterialQty($userId, $code, $premium);
        }

        $category = $source === 'equipment'
            ? ChestLootConfig::CATEGORY_EQUIPMENT
            : ChestLootConfig::CATEGORY_ALBUM;

        return $this->economyRepository->getEventAgnosticLootItemCount($userId, $code, $category);
    }

    private function resolveInputLabel(string $code, string $source): string
    {
        if ($source === 'material') {
            return ProfessionMaterialConfig::getMaterialLabel($code);
        }

        if ($source === 'equipment') {
            return ProfessionCraftedItemConfig::getLabel($code);
        }

        return AlbumConfig::itemLabel();
    }

    private function resolveOutputLabel(string $code, string $source): string
    {
        if ($source === 'material' || $source === 'equipment') {
            return ProfessionCraftedItemConfig::getLabel($code);
        }

        return AlbumConfig::itemLabel();
    }
}
