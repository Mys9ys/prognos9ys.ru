<?php

namespace Prognos9ys\Main\Service\Game;

use Bitrix\Main\Data\Cache;
use Prognos9ys\Main\Model\Repository\GameEconomyRepository;
use Prognos9ys\Main\Model\Repository\ProfessionRepository;

class GameProfileService
{
    private const SUMMARY_CACHE_DIR = '/prognos9ys/game_profile/';
    private const SUMMARY_CACHE_TTL = 45;

    private WalletService $walletService;
    private UserProgressService $progressService;
    private TreasureService $treasureService;
    private UserBankService $bankService;
    private BankDepositService $depositService;
    private BankLoanService $loanService;
    private GameEconomyRepository $repository;

    public function __construct(
        ?WalletService $walletService = null,
        ?UserProgressService $progressService = null,
        ?TreasureService $treasureService = null,
        ?UserBankService $bankService = null,
        ?BankDepositService $depositService = null,
        ?BankLoanService $loanService = null,
        ?GameEconomyRepository $repository = null
    ) {
        $this->walletService = $walletService ?? new WalletService();
        $this->progressService = $progressService ?? new UserProgressService();
        $this->treasureService = $treasureService ?? new TreasureService();
        $this->bankService = $bankService ?? new UserBankService();
        $this->depositService = $depositService ?? new BankDepositService();
        $this->loanService = $loanService ?? new BankLoanService();
        $this->repository = $repository ?? new GameEconomyRepository();
    }

    public function getSummary(
        int $userId,
        bool $includeBankDetails = true,
        bool $withGrants = false,
        bool $forceRefresh = false
    ): array {
        if ($userId <= 0) {
            return [];
        }

        $cacheKey = 'summary_' . $userId . '_' . ($includeBankDetails ? '1' : '0');
        if (!$withGrants && !$forceRefresh) {
            $cached = $this->readSummaryCache($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        try {
            if ($withGrants) {
                try {
                    (new WalletService())->grantStarterPackIfMissing($userId);
                } catch (\Throwable $exception) {
                }

                try {
                    (new LevelUpRewardService())->grantMissedRewards($userId);
                } catch (\Throwable $exception) {
                }

                try {
                    (new LevelUpRewardService())->grantMissedLevelChests($userId);
                } catch (\Throwable $exception) {
                }
            }

            $data = $this->buildSummaryPayload($userId, $includeBankDetails, $withGrants);
            if (!$withGrants) {
                $this->writeSummaryCache($cacheKey, $data);
            }

            return $data;
        } catch (\Throwable $exception) {
            return $this->emptySummaryFallback();
        }
    }

    public static function invalidateSummaryCache(int $userId): void
    {
        if ($userId <= 0 || !class_exists(Cache::class)) {
            return;
        }

        $cache = Cache::createInstance();
        $cache->clean('summary_' . $userId . '_1', self::SUMMARY_CACHE_DIR);
        $cache->clean('summary_' . $userId . '_0', self::SUMMARY_CACHE_DIR);
    }

    private function buildSummaryPayload(int $userId, bool $includeBankDetails, bool $runChestMigrations = false): array
    {
        $myBank = $includeBankDetails ? $this->bankService->getMyBank($userId) : null;
        $hasBank = $includeBankDetails
            ? $myBank !== null
            : $this->bankService->hasActiveBank($userId);

        $deposits = $includeBankDetails ? $this->depositService->getMyContracts($userId) : [];
        $loans = $includeBankDetails ? $this->loanService->getMyContracts($userId) : [];

        $bankBlock = [
            'has_bank' => $hasBank,
            'deposit_amount' => GameEconomyConfig::DEPOSIT_MIN_AMOUNT_PROGNOBAKS,
            'loan_amount' => GameEconomyConfig::LOAN_MIN_AMOUNT_PROGNOBAKS,
            'contract_events' => (new GameEventScopeService())->listEligibleEventsForBank(),
        ];

        if ($includeBankDetails) {
            $bankBlock['my_bank'] = $myBank;
            $bankBlock['active_deposits'] = count($deposits);
            $bankBlock['active_loans'] = count($loans);
            $bankBlock['can_open'] = $myBank === null
                && $this->walletService->getWalletSummary($userId)['prognobaks']
                >= GameEconomyConfig::BANK_OPEN_MIN_WALLET_PROGNOBAKS;
        }

        $anchorEventId = (new GameEventScopeService())->getAnchorEventId();
        $lootStacks = ChestLootConfig::mergeInventoryLootStacks(array_merge(
            $this->repository->getLootItemStacksForUser($userId, ChestLootConfig::LOOT_EVENT_GLOBAL),
            $anchorEventId > 0
                ? $this->repository->getLootItemStacksForUser($userId, $anchorEventId)
                : []
        ));
        $inventoryItems = array_merge(
            $lootStacks,
            ProfessionMaterialConfig::buildInventoryStacksFromRows(
                (new ProfessionRepository())->getMaterialsByUserId($userId)
            )
        );

        return [
            'wallet' => $this->walletService->getWalletSummary($userId),
            'premium' => $this->buildPremiumSummarySafe($userId),
            'progress' => $this->progressService->getSummary($userId),
            'pending_xp' => (new ExperienceService($this->repository))->getPendingSummaryForUser($userId),
            'treasure' => $this->treasureService->getTreasureSummary($userId, $runChestMigrations),
            'inventory_items' => $inventoryItems,
            'learned_recipes' => $this->repository->getLearnedRecipes($userId),
            'album_meta' => (new AlbumService())->getProfileMeta($userId),
            'equipment' => (new EquipmentService($this->repository))->getSummary($userId),
            'bank' => $bankBlock,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readSummaryCache(string $cacheKey): ?array
    {
        if (!class_exists(Cache::class)) {
            return null;
        }

        $cache = Cache::createInstance();
        if ($cache->initCache(self::SUMMARY_CACHE_TTL, $cacheKey, self::SUMMARY_CACHE_DIR)) {
            $vars = $cache->getVars();

            return is_array($vars) ? $vars : null;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function writeSummaryCache(string $cacheKey, array $data): void
    {
        if (!class_exists(Cache::class)) {
            return;
        }

        $cache = Cache::createInstance();
        if ($cache->startDataCache(self::SUMMARY_CACHE_TTL, $cacheKey, self::SUMMARY_CACHE_DIR)) {
            $cache->endDataCache($data);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function emptySummaryFallback(): array
    {
        return [
                'wallet' => [
                    'prognobaks' => 0,
                    'rublius' => 0,
                    'rublius_rate' => GameEconomyConfig::RUBLIUS_TO_PROGNOBAKS,
                ],
                'premium' => [
                    'active' => false,
                    'until' => null,
                    'remaining_seconds' => 0,
                    'scrolls' => [1 => 0, 3 => 0, 5 => 0],
                    'scrolls_total' => 0,
                ],
                'progress' => (new LevelService())->getProgressSummary(0),
                'pending_xp' => ['count' => 0, 'points' => 0.0],
                'treasure' => [
                    'closed_chests' => 0,
                    'match_chests' => 0,
                    'level_chests' => 0,
                    'achievement_chests' => 0,
                    'wc26_achievement_chests' => 0,
                    'shop_chests' => 0,
                    'wc26_openable_chests' => 0,
                    'premium_scrolls' => 0,
                    'premium_scrolls_1d' => 0,
                    'premium_scrolls_3d' => 0,
                    'premium_scrolls_5d' => 0,
                    'pennant_site' => 0,
                    'pennant_chm2026' => 0,
                ],
                'inventory_items' => [],
                'learned_recipes' => [],
                'album_meta' => [
                    'glued_teams' => [
                        AlbumConfig::COLLECTION_PENNANT_WC26 => [],
                        AlbumConfig::COLLECTION_SCARF_WC26 => [],
                    ],
                    'activate' => ['allowed' => true, 'reason' => '', 'has_pennant' => false, 'has_scarf' => false, 'has_pending' => false],
                    'albums' => [],
                ],
                'bank' => [
                    'has_bank' => false,
                    'my_bank' => null,
                    'active_deposits' => 0,
                    'active_loans' => 0,
                    'can_open' => false,
                ],
            ];
    }

    /**
     * Лёгкое обновление game_info после мутаций инвентаря/альбома (без банка и автоначислений).
     */
    public function getMutationSummary(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        self::invalidateSummaryCache($userId);

        return $this->buildMutationPayload($userId);
    }

    /**
     * Лёгкое обновление game_info после открытия сундуков/паков/банок (без банка и автоначислений).
     */
    public function getInventoryOpenSummary(int $userId, bool $withProgress = false): array
    {
        if ($userId <= 0) {
            return [];
        }

        self::invalidateSummaryCache($userId);

        $payload = $this->buildMutationPayload($userId);
        $payload['treasure'] = $this->treasureService->getTreasureSummary($userId);

        if ($withProgress) {
            $payload['progress'] = $this->progressService->getSummary($userId);
        }

        return $payload;
    }

    /**
     * Кошелёк + инвентарь/альбом после биржевой покупки (без банка, прогресса, сундуков).
     */
    public function getWalletMutationSummary(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        self::invalidateSummaryCache($userId);

        return array_merge(
            ['wallet' => $this->walletService->getWalletSummary($userId)],
            $this->buildMutationPayload($userId)
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildMutationPayload(int $userId): array
    {
        $anchorEventId = (new GameEventScopeService())->getAnchorEventId();
        $lootStacks = ChestLootConfig::mergeInventoryLootStacks(array_merge(
            $this->repository->getLootItemStacksForUser($userId, ChestLootConfig::LOOT_EVENT_GLOBAL),
            $anchorEventId > 0
                ? $this->repository->getLootItemStacksForUser($userId, $anchorEventId)
                : []
        ));
        $inventoryItems = array_merge(
            $lootStacks,
            ProfessionMaterialConfig::buildInventoryStacksFromRows(
                (new ProfessionRepository())->getMaterialsByUserId($userId)
            )
        );

        return [
            'inventory_items' => $inventoryItems,
            'learned_recipes' => $this->repository->getLearnedRecipes($userId),
            'album_meta' => (new AlbumService())->getProfileMeta($userId),
        ];
    }

    /**
     * @return array{
     *   active:bool,
     *   until:?string,
     *   remaining_seconds:int,
     *   scrolls:array{1:int,3:int,5:int},
     *   scrolls_total:int
     * }
     */
    private function buildPremiumSummarySafe(int $userId): array
    {
        try {
            return (new PremiumService($this->repository))->getSummary($userId);
        } catch (\Throwable $exception) {
            return [
                'active' => false,
                'until' => null,
                'remaining_seconds' => 0,
                'scrolls' => [1 => 0, 3 => 0, 5 => 0],
                'scrolls_total' => 0,
            ];
        }
    }
}
