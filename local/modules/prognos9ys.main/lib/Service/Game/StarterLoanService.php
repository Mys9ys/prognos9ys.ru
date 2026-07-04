<?php

namespace Prognos9ys\Main\Service\Game;

use Prognos9ys\Main\Model\Repository\GameEconomyRepository;

/**
 * Стартовый займ 500 🪙 — кнопка в профиле для игроков с низкой ликвидностью.
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
     *   wallet_max:float,
     *   reason?:string,
     *   hint?:string
     * }
     */
    public function buildEligibility(int $userId): array
    {
        $amount = GameEconomyConfig::STARTER_LOAN_AMOUNT_PROGNOBAKS;
        $walletMax = GameEconomyConfig::STARTER_LOAN_WALLET_MAX;
        $base = [
            'can_take' => false,
            'amount' => $amount,
            'wallet_max' => $walletMax,
        ];

        if ($userId <= 0) {
            return $base + ['reason' => 'not_authorized'];
        }

        $wallet = $this->walletService->getWalletSummary($userId);
        $prognobaks = round((float)($wallet['prognobaks'] ?? 0), 1);
        if ($prognobaks > $walletMax) {
            return $base + [
                'reason' => 'wallet_too_high',
                'hint' => 'Доступно при балансе ≤ ' . (int)$walletMax . ' 🪙',
            ];
        }

        if ($this->countActiveLoans($userId) > 0) {
            return $base + [
                'reason' => 'active_loan',
                'hint' => 'Сначала верните текущий займ',
            ];
        }

        $bank = $this->findBestBankForLoan($userId, $amount);
        if (!$bank) {
            return $base + [
                'reason' => 'no_lender_bank',
                'hint' => 'Пока нет банков для займа — попробуйте позже',
            ];
        }

        return [
            'can_take' => true,
            'amount' => $amount,
            'wallet_max' => $walletMax,
            'hint' => '+' . (int)GameEconomyConfig::LOAN_INTEREST_PERCENT . '% за '
                . GameEconomyConfig::BANK_TERM_MATCHES . ' матчей',
        ];
    }

    public function takeStarterLoan(int $userId, ?int $eventId = null): array
    {
        $eligibility = $this->buildEligibility($userId);
        if (empty($eligibility['can_take'])) {
            $hint = trim((string)($eligibility['hint'] ?? ''));
            throw new \RuntimeException($hint !== '' ? $hint : 'Стартовый займ сейчас недоступен');
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
