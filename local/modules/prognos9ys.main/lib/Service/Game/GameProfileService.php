<?php

namespace Prognos9ys\Main\Service\Game;

use Prognos9ys\Main\Model\Repository\GameEconomyRepository;
use Prognos9ys\Main\Model\Repository\ProfessionRepository;

class GameProfileService
{
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

    public function getSummary(int $userId, bool $includeBankDetails = true): array
    {
        if ($userId <= 0) {
            return [];
        }

        try {
            (new WalletService())->grantStarterPackIfMissing($userId);
        } catch (\Throwable $exception) {
            // не блокируем профиль
        }

        try {
            (new LevelUpRewardService())->grantMissedRewards($userId);
        } catch (\Throwable $exception) {
            // не блокируем профиль
        }

        try {
            (new LevelUpRewardService())->grantMissedLevelChests($userId);
        } catch (\Throwable $exception) {
            // не блокируем профиль
        }

        try {
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
                'progress' => $this->progressService->getSummary($userId),
                'pending_xp' => (new ExperienceService())->getPendingSummaryForUser($userId),
                'treasure' => $this->treasureService->getTreasureSummary($userId),
                'inventory_items' => $inventoryItems,
                'bank' => $bankBlock,
            ];
        } catch (\Throwable $exception) {
            return [
                'wallet' => [
                    'prognobaks' => 0,
                    'rublius' => 0,
                    'rublius_rate' => GameEconomyConfig::RUBLIUS_TO_PROGNOBAKS,
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
                'bank' => [
                    'has_bank' => false,
                    'my_bank' => null,
                    'active_deposits' => 0,
                    'active_loans' => 0,
                    'can_open' => false,
                ],
            ];
        }
    }
}
