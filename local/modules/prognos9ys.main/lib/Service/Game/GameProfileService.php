<?php

namespace Prognos9ys\Main\Service\Game;

class GameProfileService
{
    private WalletService $walletService;
    private UserProgressService $progressService;
    private TreasureService $treasureService;
    private UserBankService $bankService;
    private BankDepositService $depositService;
    private BankLoanService $loanService;

    public function __construct(
        ?WalletService $walletService = null,
        ?UserProgressService $progressService = null,
        ?TreasureService $treasureService = null,
        ?UserBankService $bankService = null,
        ?BankDepositService $depositService = null,
        ?BankLoanService $loanService = null
    ) {
        $this->walletService = $walletService ?? new WalletService();
        $this->progressService = $progressService ?? new UserProgressService();
        $this->treasureService = $treasureService ?? new TreasureService();
        $this->bankService = $bankService ?? new UserBankService();
        $this->depositService = $depositService ?? new BankDepositService();
        $this->loanService = $loanService ?? new BankLoanService();
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
            ];

            if ($includeBankDetails) {
                $bankBlock['my_bank'] = $myBank;
                $bankBlock['active_deposits'] = count($deposits);
                $bankBlock['active_loans'] = count($loans);
                $bankBlock['can_open'] = $myBank === null
                    && $this->walletService->getWalletSummary($userId)['prognobaks']
                    >= GameEconomyConfig::BANK_OPEN_MIN_WALLET_PROGNOBAKS;
            }

            return [
                'wallet' => $this->walletService->getWalletSummary($userId),
                'progress' => $this->progressService->getSummary($userId),
                'treasure' => $this->treasureService->getTreasureSummary($userId),
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
                'treasure' => [
                    'closed_chests' => 0,
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
    }
}
