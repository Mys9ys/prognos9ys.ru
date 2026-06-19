<?php

namespace Prognos9ys\Main\Service\Game;

use Bitrix\Main\Type\DateTime;
use Prognos9ys\Main\Model\Repository\GameEconomyRepository;

class BankLoanService
{
    private GameEconomyRepository $repository;
    private WalletService $walletService;
    private GameEventScopeService $scopeService;

    public function __construct(
        ?GameEconomyRepository $repository = null,
        ?WalletService $walletService = null,
        ?GameEventScopeService $scopeService = null
    ) {
        $this->repository = $repository ?? new GameEconomyRepository();
        $this->walletService = $walletService ?? new WalletService($this->repository);
        $this->scopeService = $scopeService ?? new GameEventScopeService();
    }

    public function takeLoan(int $userId, int $bankId, float $amount): array
    {
        if ($userId <= 0 || $bankId <= 0) {
            throw new \InvalidArgumentException('Некорректные параметры займа');
        }

        $amount = round($amount, 1);
        if ($amount !== GameEconomyConfig::LOAN_MIN_AMOUNT_PROGNOBAKS) {
            throw new \RuntimeException(
                'Сумма займа фиксирована: ' . GameEconomyConfig::LOAN_MIN_AMOUNT_PROGNOBAKS
            );
        }

        $bank = $this->repository->getUserBankById($bankId);
        if (!$bank || ($bank['UF_ACTIVE'] ?? '') !== GameEconomyConfig::USER_BANK_STATUS_ACTIVE) {
            throw new \RuntimeException('Банк не найден или закрыт');
        }

        $ownerId = (int)($bank['UF_OWNER_ID'] ?? 0);
        if ($ownerId === $userId) {
            throw new \RuntimeException('Нельзя взять займ у собственного банка');
        }

        if ($this->repository->getUserBankLoanableAmount($bank) < $amount) {
            throw new \RuntimeException('В банке недостаточно средств для займа');
        }

        $this->repository->allocateUserBankFundsForLoan($bankId, $amount);

        $eventId = $this->scopeService->getAnchorEventId();
        $lastSettledMatch = $this->scopeService->getLastSettledMatchForEvent($eventId);

        $loanId = $this->repository->addBankLoan([
            'UF_BANK_ID' => $bankId,
            'UF_USER_ID' => $userId,
            'UF_PRINCIPAL' => $amount,
            'UF_INTEREST_RATE' => GameEconomyConfig::LOAN_INTEREST_PERCENT,
            'UF_STATUS' => GameEconomyConfig::CONTRACT_STATUS_ACTIVE,
            'UF_MATCHES_SINCE_START' => 0,
            'UF_TERM_MATCHES' => GameEconomyConfig::BANK_TERM_MATCHES,
            'UF_EVENT_ID' => $eventId,
            'UF_OPENING_MATCH_ID' => $lastSettledMatch['id'],
            'UF_OPENING_MATCH_NUMBER' => $lastSettledMatch['number'],
            'UF_LAST_TICK_MATCH_ID' => 0,
            'UF_CREATED_AT' => new DateTime(),
            'UF_UPDATED_AT' => new DateTime(),
        ]);

        $this->walletService->credit(
            $userId,
            GameEconomyConfig::CURRENCY_PROGNOBAKS,
            $amount,
            'bank_loan',
            'loan',
            $loanId
        );

        return self::formatContract($this->repository->getBankLoanById($loanId));
    }

    public function getMyContracts(int $userId): array
    {
        $items = [];
        foreach ($this->repository->getLoansByUserId($userId) as $row) {
            if (($row['UF_STATUS'] ?? '') === GameEconomyConfig::CONTRACT_STATUS_CLOSED) {
                continue;
            }
            $items[] = self::formatContract($row);
        }

        return $items;
    }

    public function processMaturity(array $loan): void
    {
        $loanId = (int)$loan['ID'];
        $bankId = (int)($loan['UF_BANK_ID'] ?? 0);
        $userId = (int)($loan['UF_USER_ID'] ?? 0);
        $principal = round((float)($loan['UF_PRINCIPAL'] ?? 0), 1);
        $interest = GameEconomyConfig::calculateLoanInterest($principal);
        $total = round($principal + $interest, 1);
        $now = new DateTime();

        $wallet = $this->walletService->getWalletSummary($userId);
        if ($wallet['prognobaks'] >= $total) {
            $this->walletService->debit(
                $userId,
                GameEconomyConfig::CURRENCY_PROGNOBAKS,
                $total,
                'bank_loan_repay',
                'loan',
                $loanId
            );
            $this->repository->creditUserBankLoanRepayment($bankId, $total);
            $this->repository->updateBankLoan($loanId, [
                'UF_STATUS' => GameEconomyConfig::CONTRACT_STATUS_CLOSED,
                'UF_UPDATED_AT' => $now,
                'UF_CLOSED_AT' => $now,
            ]);

            return;
        }

        $this->repository->updateBankLoan($loanId, [
            'UF_STATUS' => GameEconomyConfig::CONTRACT_STATUS_EXTENDED,
            'UF_MATCHES_SINCE_START' => 0,
            'UF_UPDATED_AT' => $now,
        ]);

        if ($interest > 0) {
            $paid = $this->walletService->tryDebitPrognobaks(
                $userId,
                $interest,
                'bank_loan_interest',
                'loan',
                $loanId
            );
            if ($paid > 0) {
                $this->repository->creditUserBankLoanRepayment($bankId, $paid);
            }
        }
    }

    public static function formatContract(array $row): array
    {
        $term = (int)($row['UF_TERM_MATCHES'] ?? GameEconomyConfig::BANK_TERM_MATCHES);
        $since = (int)($row['UF_MATCHES_SINCE_START'] ?? 0);
        $principal = round((float)($row['UF_PRINCIPAL'] ?? 0), 1);
        $scope = new GameEventScopeService();
        $opening = $scope->resolveOpeningMatchMeta($row);
        $lastTickMatchId = (int)($row['UF_LAST_TICK_MATCH_ID'] ?? 0);

        return [
            'id' => (int)$row['ID'],
            'bank_id' => (int)($row['UF_BANK_ID'] ?? 0),
            'user_id' => (int)($row['UF_USER_ID'] ?? 0),
            'principal' => $principal,
            'interest_rate' => round((float)($row['UF_INTEREST_RATE'] ?? 0), 1),
            'interest_amount' => GameEconomyConfig::calculateLoanInterest($principal),
            'total_due' => round($principal + GameEconomyConfig::calculateLoanInterest($principal), 1),
            'status' => (string)($row['UF_STATUS'] ?? ''),
            'matches_since_start' => $since,
            'term_matches' => $term,
            'matches_left' => max(0, $term - $since),
            'event_id' => (int)($row['UF_EVENT_ID'] ?? 0),
            'opening_match_id' => $opening['opening_match_id'],
            'opening_match_number' => $opening['opening_match_number'],
            'opening_match_label' => $opening['opening_match_label'],
            'created_match_label' => $opening['created_match_label'],
            'last_tick_match_id' => $lastTickMatchId,
            'last_tick_match_label' => $scope->formatMatchLabel($lastTickMatchId),
        ];
    }
}
