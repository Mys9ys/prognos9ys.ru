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

    public function takeLoan(int $userId, int $bankId, float $amount, ?int $eventId = null): array
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

        $eventId = $this->scopeService->resolveContractEventId($eventId);
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

    /**
     * Сумма автоматического погашения при расчёте матча (с учётом уже списанных % при продлении).
     *
     * @return array{
     *   principal:float,
     *   interest_amount:float,
     *   interest_paid:float,
     *   interest_remaining:float,
     *   total_due:float
     * }
     */
    public static function getLoanRepaySummary(array $row, ?GameEconomyRepository $repository = null): array
    {
        $repository = $repository ?? new GameEconomyRepository();
        $loanId = (int)($row['ID'] ?? 0);
        $principal = round((float)($row['UF_PRINCIPAL'] ?? 0), 1);
        $interestAmount = GameEconomyConfig::calculateLoanInterest($principal);

        $interestPaid = 0.0;
        if ($loanId > 0) {
            foreach ($repository->getWalletTxByRefs('loan', [$loanId], ['bank_loan_interest']) as $tx) {
                $interestPaid = round($interestPaid + abs((float)($tx['UF_AMOUNT'] ?? 0)), 1);
            }
        }

        $interestRemaining = max(0.0, round($interestAmount - $interestPaid, 1));

        return [
            'principal' => $principal,
            'interest_amount' => $interestAmount,
            'interest_paid' => $interestPaid,
            'interest_remaining' => $interestRemaining,
            'total_due' => round($principal + $interestRemaining, 1),
        ];
    }

    /**
     * Досрочный возврат: всегда тело + полные проценты по этому займу.
     * Не уменьшаем сумму из-за %, списанных при продлении других/этого контракта.
     *
     * @return array{principal:float,interest_amount:float,total_due:float}
     */
    public static function getEarlyRepayDue(array $row): array
    {
        $principal = round((float)($row['UF_PRINCIPAL'] ?? 0), 1);
        $interestAmount = GameEconomyConfig::calculateLoanInterest($principal);

        return [
            'principal' => $principal,
            'interest_amount' => $interestAmount,
            'total_due' => round($principal + $interestAmount, 1),
        ];
    }

    /**
     * @return array{
     *   can_repay:bool,
     *   reason?:string,
     *   principal?:float,
     *   interest_amount?:float,
     *   interest_paid?:float,
     *   interest_remaining?:float,
     *   total_due?:float
     * }
     */
    public function evaluateEarlyRepayEligibility(array $row): array
    {
        $status = (string)($row['UF_STATUS'] ?? '');
        if ($status === GameEconomyConfig::CONTRACT_STATUS_CLOSED) {
            return ['can_repay' => false, 'reason' => 'closed'];
        }

        if ($status !== GameEconomyConfig::CONTRACT_STATUS_ACTIVE
            && $status !== GameEconomyConfig::CONTRACT_STATUS_EXTENDED) {
            return ['can_repay' => false, 'reason' => 'not_active'];
        }

        $loanId = (int)($row['ID'] ?? 0);
        $userId = (int)($row['UF_USER_ID'] ?? 0);
        if ($this->repository->hasWalletTx($userId, 'bank_loan_repay', 'loan', $loanId)
            || $this->repository->hasWalletTx($userId, 'bank_loan_cancel', 'loan', $loanId)) {
            return ['can_repay' => false, 'reason' => 'already_settled'];
        }

        $cancel = (new BankContractLifecycleService($this->repository, $this->walletService, $this->scopeService))
            ->evaluateCancelEligibility($row, 'loan');
        if ($cancel['can_cancel'] ?? false) {
            return ['can_repay' => false, 'reason' => 'use_cancel'];
        }

        $summary = self::getEarlyRepayDue($row);
        $wallet = $this->walletService->getWalletSummary($userId);
        if ((float)$wallet['prognobaks'] < (float)$summary['total_due']) {
            return array_merge($summary, ['can_repay' => false, 'reason' => 'insufficient_funds']);
        }

        return array_merge($summary, ['can_repay' => true]);
    }

    public function repayLoanEarly(int $userId, int $loanId): array
    {
        $loan = $this->repository->getBankLoanById($loanId);
        if (!$loan) {
            throw new \RuntimeException('Займ не найден');
        }

        if ((int)($loan['UF_USER_ID'] ?? 0) !== $userId) {
            throw new \RuntimeException('Нет доступа к этому займу');
        }

        $check = $this->evaluateEarlyRepayEligibility($loan);
        if (!($check['can_repay'] ?? false)) {
            throw new \RuntimeException($this->earlyRepayBlockMessage($check['reason'] ?? '', $check));
        }

        $bankId = (int)($loan['UF_BANK_ID'] ?? 0);
        $due = self::getEarlyRepayDue($loan);
        $principal = (float)$due['principal'];
        $interest = (float)$due['interest_amount'];
        $total = (float)$due['total_due'];
        $now = new DateTime();

        $this->walletService->debit(
            $userId,
            GameEconomyConfig::CURRENCY_PROGNOBAKS,
            $principal,
            'bank_loan_repay',
            'loan',
            $loanId
        );
        if ($interest > 0) {
            $this->walletService->debit(
                $userId,
                GameEconomyConfig::CURRENCY_PROGNOBAKS,
                $interest,
                'bank_loan_interest',
                'loan',
                $loanId
            );
        }
        $this->repository->creditUserBankLoanRepayment($bankId, $total);
        $this->repository->updateBankLoan($loanId, [
            'UF_STATUS' => GameEconomyConfig::CONTRACT_STATUS_CLOSED,
            'UF_UPDATED_AT' => $now,
            'UF_CLOSED_AT' => $now,
        ]);

        return self::formatContract($this->repository->getBankLoanById($loanId));
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
        $maturitySummary = self::getLoanRepaySummary($row);
        $earlyDue = self::getEarlyRepayDue($row);
        $scope = new GameEventScopeService();
        $opening = $scope->resolveOpeningMatchMeta($row);
        $lastTickMatchId = (int)($row['UF_LAST_TICK_MATCH_ID'] ?? 0);
        $eventId = (int)($row['UF_EVENT_ID'] ?? 0);
        $maturityMatchNumber = $opening['opening_match_number'] > 0
            ? $opening['opening_match_number'] + $term
            : 0;
        $lifecycle = new BankContractLifecycleService();
        $cancel = $lifecycle->evaluateCancelEligibility($row, 'loan');
        $repay = (new self())->evaluateEarlyRepayEligibility($row);
        $status = (string)($row['UF_STATUS'] ?? '');
        $showEarlyRepay = !($cancel['can_cancel'] ?? false)
            && in_array($status, [
                GameEconomyConfig::CONTRACT_STATUS_ACTIVE,
                GameEconomyConfig::CONTRACT_STATUS_EXTENDED,
            ], true)
            && ($repay['reason'] ?? '') !== 'already_settled';
        $displayDue = ($cancel['can_cancel'] ?? false)
            ? $principal
            : (float)$earlyDue['total_due'];

        return [
            'id' => (int)$row['ID'],
            'bank_id' => (int)($row['UF_BANK_ID'] ?? 0),
            'user_id' => (int)($row['UF_USER_ID'] ?? 0),
            'principal' => $principal,
            'interest_rate' => round((float)($row['UF_INTEREST_RATE'] ?? 0), 1),
            'interest_amount' => (float)$earlyDue['interest_amount'],
            'interest_paid' => (float)$maturitySummary['interest_paid'],
            'interest_remaining' => (float)$maturitySummary['interest_remaining'],
            'total_due' => $displayDue,
            'early_repay_due' => (float)$earlyDue['total_due'],
            'status' => (string)($row['UF_STATUS'] ?? ''),
            'matches_since_start' => $since,
            'term_matches' => $term,
            'matches_left' => max(0, $term - $since),
            'event_id' => $eventId,
            'event_name' => $scope->getEventName($eventId),
            'opening_match_id' => $opening['opening_match_id'],
            'opening_match_number' => $opening['opening_match_number'],
            'opening_match_label' => $opening['opening_match_label'],
            'created_match_label' => $opening['created_match_label'],
            'maturity_match_number' => $maturityMatchNumber,
            'maturity_match_label' => $maturityMatchNumber > 0
                ? 'расчёт после ' . $scope->formatMatchLabelByNumber($maturityMatchNumber)
                : '',
            'last_tick_match_id' => $lastTickMatchId,
            'last_tick_match_label' => $scope->formatMatchLabel($lastTickMatchId),
            'can_cancel' => (bool)($cancel['can_cancel'] ?? false),
            'show_early_repay' => $showEarlyRepay,
            'can_early_repay' => (bool)($repay['can_repay'] ?? false),
            'early_repay_hint' => self::buildEarlyRepayHint($repay),
        ];
    }

    /**
     * @param array<string, mixed> $repay
     */
    private static function buildEarlyRepayHint(array $repay): string
    {
        if ($repay['can_repay'] ?? false) {
            return '';
        }

        $reason = (string)($repay['reason'] ?? '');
        $totalDue = round((float)($repay['total_due'] ?? 0), 1);

        if ($reason === 'insufficient_funds' && $totalDue > 0) {
            return 'Нужно ' . $totalDue . ' 🪙 на кошельке';
        }

        return '';
    }

    /**
     * @param array<string, mixed> $check
     */
    private function earlyRepayBlockMessage(string $reason, array $check = []): string
    {
        switch ($reason) {
            case 'insufficient_funds':
                $total = round((float)($check['total_due'] ?? 0), 1);
                return $total > 0
                    ? 'Недостаточно средств: нужно ' . $total . ' 🪙'
                    : 'Недостаточно средств на кошельке';
            case 'already_settled':
                return 'Займ уже закрыт или изменён';
            case 'closed':
                return 'Займ уже закрыт';
            case 'use_cancel':
                return 'До первого матча займ можно отменить без процентов';
            default:
                return 'Досрочный возврат сейчас недоступен';
        }
    }
}
