<?php

namespace Prognos9ys\Main\Service\Game;

use Bitrix\Main\Type\DateTime;
use Prognos9ys\Main\Model\Repository\GameEconomyRepository;

class BankDepositService
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

    public function createDeposit(int $userId, int $bankId, float $amount, ?int $eventId = null): array
    {
        if ($userId <= 0 || $bankId <= 0) {
            throw new \InvalidArgumentException('Некорректные параметры вклада');
        }

        $amount = round($amount, 1);
        if ($amount !== GameEconomyConfig::DEPOSIT_MIN_AMOUNT_PROGNOBAKS) {
            throw new \RuntimeException(
                'Сумма вклада фиксирована: ' . GameEconomyConfig::DEPOSIT_MIN_AMOUNT_PROGNOBAKS
            );
        }

        $bank = $this->repository->getUserBankById($bankId);
        if (!$bank || ($bank['UF_ACTIVE'] ?? '') !== GameEconomyConfig::USER_BANK_STATUS_ACTIVE) {
            throw new \RuntimeException('Банк не найден или закрыт');
        }

        $ownerId = (int)($bank['UF_OWNER_ID'] ?? 0);
        if ($ownerId === $userId) {
            throw new \RuntimeException('Нельзя открыть вклад в собственном банке');
        }

        $this->walletService->debit(
            $userId,
            GameEconomyConfig::CURRENCY_PROGNOBAKS,
            $amount,
            'bank_deposit',
            'bank',
            $bankId
        );

        $eventId = $this->scopeService->resolveContractEventId($eventId);
        $lastSettledMatch = $this->scopeService->getLastSettledMatchForEvent($eventId);

        $depositId = $this->repository->addBankDeposit([
            'UF_BANK_ID' => $bankId,
            'UF_USER_ID' => $userId,
            'UF_PRINCIPAL' => $amount,
            'UF_INTEREST_RATE' => GameEconomyConfig::DEPOSIT_INTEREST_PERCENT,
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

        $this->repository->updateWalletTxRefForLastReason($userId, 'bank_deposit', 'deposit', $depositId);
        $this->repository->adjustUserBankLiquid($bankId, $amount);

        return self::formatContract($this->repository->getBankDepositById($depositId));
    }

    public function getMyContracts(int $userId): array
    {
        $items = [];
        foreach ($this->repository->getDepositsByUserId($userId) as $row) {
            if (($row['UF_STATUS'] ?? '') === GameEconomyConfig::CONTRACT_STATUS_CLOSED) {
                continue;
            }
            $items[] = self::formatContract($row);
        }

        return $items;
    }

    public function processMaturity(array $deposit): void
    {
        $depositId = (int)$deposit['ID'];
        $bankId = (int)($deposit['UF_BANK_ID'] ?? 0);
        $userId = (int)($deposit['UF_USER_ID'] ?? 0);
        $principal = round((float)($deposit['UF_PRINCIPAL'] ?? 0), 1);
        $interest = GameEconomyConfig::calculateDepositInterest($principal);
        $total = round($principal + $interest, 1);

        $bank = $this->repository->getUserBankById($bankId);
        if (!$bank) {
            return;
        }

        $liquid = round((float)($bank['UF_LIQUID'] ?? 0), 1);
        $wallet = $this->walletService->getWalletSummary($userId);
        $now = new DateTime();

        if ($wallet['prognobaks'] < GameEconomyConfig::POOR_WALLET_THRESHOLD_PROGNOBAKS) {
            $this->settlePoorWalletReturn($depositId, $bankId, $userId, $principal, $liquid, $now);

            return;
        }

        if ($liquid >= $total) {
            $this->payoutAndClose($depositId, $bankId, $userId, $total, 'bank_deposit_return', $now);

            return;
        }

        $this->extendContract($depositId, $now);
        if ($interest > 0 && $liquid >= $interest) {
            $this->payoutInterest($depositId, $bankId, $userId, $interest, $now);
        }
    }

    private function settlePoorWalletReturn(
        int $depositId,
        int $bankId,
        int $userId,
        float $principal,
        float $liquid,
        DateTime $now
    ): void {
        if ($liquid >= $principal) {
            $this->payoutAndClose($depositId, $bankId, $userId, $principal, 'bank_deposit_return', $now);

            return;
        }

        $half = round($principal * 0.5, 1);
        if ($half > 0 && $liquid >= $half) {
            $this->payoutAndClose($depositId, $bankId, $userId, $half, 'bank_deposit_return_half', $now);

            return;
        }

        $this->extendContract($depositId, $now);
    }

    /**
     * Разовая выплата пропущенных процентов (без смены срока контракта).
     *
     * @return array{status:string,interest?:float,reason?:string,need?:float,have?:float}
     */
    public function tryPayMissedInterest(int $depositId, bool $dryRun = false): array
    {
        $deposit = $this->repository->getBankDepositById($depositId);
        if (!$deposit) {
            return ['status' => 'error', 'reason' => 'deposit_not_found'];
        }

        if (($deposit['UF_STATUS'] ?? '') === GameEconomyConfig::CONTRACT_STATUS_CLOSED) {
            return ['status' => 'skipped', 'reason' => 'closed'];
        }

        if (($deposit['UF_STATUS'] ?? '') !== GameEconomyConfig::CONTRACT_STATUS_EXTENDED) {
            return ['status' => 'skipped', 'reason' => 'not_due'];
        }

        $userId = (int)($deposit['UF_USER_ID'] ?? 0);
        if ($this->repository->hasWalletTx($userId, 'bank_deposit_interest', 'deposit', $depositId)) {
            return ['status' => 'skipped', 'reason' => 'already_paid'];
        }

        if ($this->repository->hasWalletTx($userId, 'bank_deposit_return', 'deposit', $depositId)) {
            return ['status' => 'skipped', 'reason' => 'closed_via_return'];
        }

        $bankId = (int)($deposit['UF_BANK_ID'] ?? 0);
        $principal = round((float)($deposit['UF_PRINCIPAL'] ?? 0), 1);
        $interest = GameEconomyConfig::calculateDepositInterest($principal);
        if ($interest <= 0) {
            return ['status' => 'skipped', 'reason' => 'zero_interest'];
        }

        $bank = $this->repository->getUserBankById($bankId);
        if (!$bank) {
            return ['status' => 'error', 'reason' => 'bank_not_found'];
        }

        $liquid = round((float)($bank['UF_LIQUID'] ?? 0), 1);
        if ($liquid < $interest) {
            return [
                'status' => 'skipped',
                'reason' => 'insufficient_liquid',
                'need' => $interest,
                'have' => $liquid,
            ];
        }

        if ($dryRun) {
            return ['status' => 'would_pay', 'interest' => $interest];
        }

        $this->payoutInterest($depositId, $bankId, $userId, $interest, new DateTime());

        return ['status' => 'paid', 'interest' => $interest];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findMissedInterestDeposits(?int $bankId = null): array
    {
        $rows = $bankId !== null && $bankId > 0
            ? $this->repository->getActiveDepositsByBankId($bankId)
            : $this->repository->getAllActiveDeposits();

        $missed = [];
        foreach ($rows as $row) {
            $depositId = (int)$row['ID'];
            $check = $this->tryPayMissedInterest($depositId, true);
            if (($check['status'] ?? '') === 'would_pay') {
                $missed[] = array_merge(self::formatContract($row), [
                    'missed_interest' => $check['interest'] ?? 0,
                ]);
            }
        }

        return $missed;
    }

    /**
     * Откат ошибочной выплаты процентов (например, по ещё не созревшему вкладу).
     *
     * @return array{status:string,interest?:float,reason?:string}
     */
    public function rollbackInterestPayment(int $depositId, bool $dryRun = false): array
    {
        $deposit = $this->repository->getBankDepositById($depositId);
        if (!$deposit) {
            return ['status' => 'error', 'reason' => 'deposit_not_found'];
        }

        $userId = (int)($deposit['UF_USER_ID'] ?? 0);
        $bankId = (int)($deposit['UF_BANK_ID'] ?? 0);

        if (!$this->repository->hasWalletTx($userId, 'bank_deposit_interest', 'deposit', $depositId)) {
            return ['status' => 'skipped', 'reason' => 'no_interest_payment'];
        }

        if ($this->repository->hasWalletTx($userId, 'bank_deposit_interest_rollback', 'deposit', $depositId)) {
            return ['status' => 'skipped', 'reason' => 'already_rolled_back'];
        }

        $txRows = $this->repository->getWalletTxByRefs('deposit', [$depositId], ['bank_deposit_interest']);
        $interest = 0.0;
        foreach ($txRows as $tx) {
            if ((int)($tx['UF_USER_ID'] ?? 0) !== $userId) {
                continue;
            }
            $interest = round((float)($tx['UF_AMOUNT'] ?? 0), 1);
            break;
        }

        if ($interest <= 0) {
            $interest = GameEconomyConfig::calculateDepositInterest(
                round((float)($deposit['UF_PRINCIPAL'] ?? 0), 1)
            );
        }

        if ($interest <= 0) {
            return ['status' => 'skipped', 'reason' => 'zero_interest'];
        }

        if ($dryRun) {
            return ['status' => 'would_rollback', 'interest' => $interest];
        }

        $this->walletService->debit(
            $userId,
            GameEconomyConfig::CURRENCY_PROGNOBAKS,
            $interest,
            'bank_deposit_interest_rollback',
            'deposit',
            $depositId
        );
        $this->repository->adjustUserBankLiquid($bankId, $interest);

        return ['status' => 'rolled_back', 'interest' => $interest];
    }

    private function payoutInterest(int $depositId, int $bankId, int $userId, float $interest, DateTime $now): void
    {
        $this->repository->adjustUserBankLiquid($bankId, -$interest);
        $this->walletService->credit(
            $userId,
            GameEconomyConfig::CURRENCY_PROGNOBAKS,
            $interest,
            'bank_deposit_interest',
            'deposit',
            $depositId
        );
        $this->repository->updateBankDeposit($depositId, ['UF_UPDATED_AT' => $now]);
    }

    private function payoutAndClose(
        int $depositId,
        int $bankId,
        int $userId,
        float $amount,
        string $reason,
        DateTime $now
    ): void {
        $this->repository->adjustUserBankLiquid($bankId, -$amount);
        $this->walletService->credit(
            $userId,
            GameEconomyConfig::CURRENCY_PROGNOBAKS,
            $amount,
            $reason,
            'deposit',
            $depositId
        );
        $this->repository->updateBankDeposit($depositId, [
            'UF_STATUS' => GameEconomyConfig::CONTRACT_STATUS_CLOSED,
            'UF_UPDATED_AT' => $now,
            'UF_CLOSED_AT' => $now,
        ]);
    }

    private function extendContract(int $depositId, DateTime $now): void
    {
        $this->repository->updateBankDeposit($depositId, [
            'UF_STATUS' => GameEconomyConfig::CONTRACT_STATUS_EXTENDED,
            'UF_MATCHES_SINCE_START' => 0,
            'UF_UPDATED_AT' => $now,
        ]);
    }

    public static function formatContract(array $row): array
    {
        $term = (int)($row['UF_TERM_MATCHES'] ?? GameEconomyConfig::BANK_TERM_MATCHES);
        $since = (int)($row['UF_MATCHES_SINCE_START'] ?? 0);
        $principal = round((float)($row['UF_PRINCIPAL'] ?? 0), 1);
        $scope = new GameEventScopeService();
        $opening = $scope->resolveOpeningMatchMeta($row);
        $lastTickMatchId = (int)($row['UF_LAST_TICK_MATCH_ID'] ?? 0);
        $eventId = (int)($row['UF_EVENT_ID'] ?? 0);
        $maturityMatchNumber = $opening['opening_match_number'] > 0
            ? $opening['opening_match_number'] + $term
            : 0;
        $cancel = (new BankContractLifecycleService())->evaluateCancelEligibility($row, 'deposit');

        return [
            'id' => (int)$row['ID'],
            'bank_id' => (int)($row['UF_BANK_ID'] ?? 0),
            'user_id' => (int)($row['UF_USER_ID'] ?? 0),
            'principal' => $principal,
            'interest_rate' => round((float)($row['UF_INTEREST_RATE'] ?? 0), 1),
            'interest_amount' => GameEconomyConfig::calculateDepositInterest($principal),
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
        ];
    }
}
