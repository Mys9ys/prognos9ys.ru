<?php

namespace Prognos9ys\Main\Service\Game;

use Prognos9ys\Main\Model\Repository\GameEconomyRepository;

/**
 * Стартовый займ 500 🪙 — кнопка в профиле и в банке для всех игроков.
 */
class StarterLoanService
{
    private GameEconomyRepository $repository;
    private WalletService $walletService;
    private BankLoanService $loanService;

    public function __construct(
        ?GameEconomyRepository $repository = null,
        ?WalletService $walletService = null,
        ?BankLoanService $loanService = null
    ) {
        $this->repository = $repository ?? new GameEconomyRepository();
        $this->walletService = $walletService ?? new WalletService($this->repository);
        $this->loanService = $loanService ?? new BankLoanService($this->repository, $this->walletService);
    }

    /**
     * @return array{
     *   can_take:bool,
     *   amount:float,
     *   hint?:string
     * }
     */
    public function buildEligibility(int $userId): array
    {
        $amount = GameEconomyConfig::STARTER_LOAN_AMOUNT_PROGNOBAKS;

        if ($userId <= 0) {
            return [
                'can_take' => false,
                'amount' => $amount,
            ];
        }

        if ($this->countActiveLoans($userId) > 0) {
            return [
                'can_take' => false,
                'amount' => $amount,
                'hint' => 'Сначала верните текущий займ во вкладке «Операции»',
            ];
        }

        return [
            'can_take' => true,
            'amount' => $amount,
            'hint' => '+' . (int)GameEconomyConfig::LOAN_INTEREST_PERCENT . '% за '
                . GameEconomyConfig::BANK_TERM_MATCHES . ' матчей',
        ];
    }

    public function takeStarterLoan(int $userId, ?int $eventId = null): array
    {
        if ($userId <= 0) {
            throw new \RuntimeException('Пользователь не авторизован');
        }

        if ($this->countActiveLoans($userId) > 0) {
            throw new \RuntimeException('Сначала верните текущий займ');
        }

        $amount = GameEconomyConfig::STARTER_LOAN_AMOUNT_PROGNOBAKS;
        $bank = $this->findBestBankForLoan($userId, $amount);
        if (!$bank) {
            throw new \RuntimeException('Нет банка для стартового займа');
        }

        $this->ensureLenderBankLiquidity($bank, $amount, $userId);

        return $this->loanService->takeLoan(
            $userId,
            (int)$bank['ID'],
            $amount,
            $eventId
        );
    }

    private function countActiveLoans(int $userId): int
    {
        return count($this->loanService->getMyContracts($userId));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findBestBankForLoan(int $borrowerId, float $minAmount): ?array
    {
        $best = null;
        $bestLoanable = 0.0;

        foreach ($this->repository->getActiveUserBanks(500) as $bank) {
            if ((int)($bank['UF_OWNER_ID'] ?? 0) === $borrowerId) {
                continue;
            }

            $loanable = $this->repository->getUserBankLoanableAmount($bank);
            if ($loanable >= $minAmount && $loanable > $bestLoanable) {
                $best = $bank;
                $bestLoanable = $loanable;
            }
        }

        if ($best !== null) {
            return $best;
        }

        foreach ($this->repository->getActiveUserBanks(500) as $bank) {
            if ((int)($bank['UF_OWNER_ID'] ?? 0) === $borrowerId) {
                continue;
            }

            if ($best === null) {
                $best = $bank;
            }
        }

        return $best;
    }

    /**
     * @param array<string, mixed> $bank
     */
    private function ensureLenderBankLiquidity(array $bank, float $amount, int $borrowerId): void
    {
        $bankId = (int)($bank['ID'] ?? 0);
        if ($bankId <= 0) {
            throw new \RuntimeException('Банк для займа не найден');
        }

        $loanable = $this->repository->getUserBankLoanableAmount($bank);
        $shortfall = round($amount - $loanable, 1);
        if ($shortfall <= 0) {
            return;
        }

        $treasury = new TreasuryService($this->repository);
        $summary = $treasury->getSummary();
        if ((float)($summary['prognobaks'] ?? 0) < $shortfall) {
            throw new \RuntimeException('В казне недостаточно средств для стартового займа');
        }

        $treasury->debit(
            GameEconomyConfig::CURRENCY_PROGNOBAKS,
            $shortfall,
            'starter_loan_liquidity',
            $bankId,
            $borrowerId,
            'bank'
        );
        $this->repository->adjustUserBankLiquid($bankId, $shortfall);
    }
}
