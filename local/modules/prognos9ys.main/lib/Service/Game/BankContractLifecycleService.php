<?php

namespace Prognos9ys\Main\Service\Game;

use Bitrix\Main\Type\DateTime;
use Prognos9ys\Main\Model\Repository\GameEconomyRepository;

class BankContractLifecycleService
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

    /**
     * @return array{can_cancel:bool,reason?:string}
     */
    public function evaluateCancelEligibility(array $contractRow, string $kind): array
    {
        $status = (string)($contractRow['UF_STATUS'] ?? '');
        if ($status === GameEconomyConfig::CONTRACT_STATUS_CLOSED) {
            return ['can_cancel' => false, 'reason' => 'closed'];
        }

        if ($status !== GameEconomyConfig::CONTRACT_STATUS_ACTIVE) {
            return ['can_cancel' => false, 'reason' => 'not_active'];
        }

        if ((int)($contractRow['UF_MATCHES_SINCE_START'] ?? 0) > 0) {
            return ['can_cancel' => false, 'reason' => 'match_tick_started'];
        }

        if ((int)($contractRow['UF_LAST_TICK_MATCH_ID'] ?? 0) > 0) {
            return ['can_cancel' => false, 'reason' => 'match_tick_started'];
        }

        $eventId = (int)($contractRow['UF_EVENT_ID'] ?? 0);
        $openingNumber = (int)($contractRow['UF_OPENING_MATCH_NUMBER'] ?? 0);
        if ($openingNumber <= 0) {
            $openingNumber = $this->scopeService->getMatchNumber((int)($contractRow['UF_OPENING_MATCH_ID'] ?? 0));
        }

        $lastSettled = $this->scopeService->getLastSettledMatchForEvent($eventId);
        if ($openingNumber > 0 && $lastSettled['number'] > $openingNumber) {
            return ['can_cancel' => false, 'reason' => 'next_match_settled'];
        }

        $contractId = (int)($contractRow['ID'] ?? 0);
        $userId = (int)($contractRow['UF_USER_ID'] ?? 0);
        $principal = round((float)($contractRow['UF_PRINCIPAL'] ?? 0), 1);

        if ($kind === 'loan') {
            if ($this->repository->hasWalletTx($userId, 'bank_loan_repay', 'loan', $contractId)
                || $this->repository->hasWalletTx($userId, 'bank_loan_interest', 'loan', $contractId)
                || $this->repository->hasWalletTx($userId, 'bank_loan_cancel', 'loan', $contractId)) {
                return ['can_cancel' => false, 'reason' => 'already_settled'];
            }

            $wallet = $this->walletService->getWalletSummary($userId);
            if ((float)$wallet['prognobaks'] < $principal) {
                return ['can_cancel' => false, 'reason' => 'funds_spent'];
            }

            return ['can_cancel' => true];
        }

        if ($kind === 'deposit') {
            if ($this->repository->hasWalletTx($userId, 'bank_deposit_return', 'deposit', $contractId)
                || $this->repository->hasWalletTx($userId, 'bank_deposit_return_half', 'deposit', $contractId)
                || $this->repository->hasWalletTx($userId, 'bank_deposit_interest', 'deposit', $contractId)
                || $this->repository->hasWalletTx($userId, 'bank_deposit_cancel', 'deposit', $contractId)) {
                return ['can_cancel' => false, 'reason' => 'already_settled'];
            }

            $bankId = (int)($contractRow['UF_BANK_ID'] ?? 0);
            $bank = $this->repository->getUserBankById($bankId);
            $liquid = round((float)($bank['UF_LIQUID'] ?? 0), 1);
            if ($liquid < $principal) {
                return ['can_cancel' => false, 'reason' => 'bank_liquid_moved'];
            }

            return ['can_cancel' => true];
        }

        return ['can_cancel' => false, 'reason' => 'unknown_kind'];
    }

    public function cancelLoan(int $userId, int $loanId): array
    {
        $loan = $this->repository->getBankLoanById($loanId);
        if (!$loan) {
            throw new \RuntimeException('Займ не найден');
        }

        if ((int)($loan['UF_USER_ID'] ?? 0) !== $userId) {
            throw new \RuntimeException('Нет доступа к этому займу');
        }

        $check = $this->evaluateCancelEligibility($loan, 'loan');
        if (!($check['can_cancel'] ?? false)) {
            throw new \RuntimeException($this->cancelBlockMessage($check['reason'] ?? ''));
        }

        $bankId = (int)($loan['UF_BANK_ID'] ?? 0);
        $principal = round((float)($loan['UF_PRINCIPAL'] ?? 0), 1);
        $now = new DateTime();

        $this->walletService->debit(
            $userId,
            GameEconomyConfig::CURRENCY_PROGNOBAKS,
            $principal,
            'bank_loan_cancel',
            'loan',
            $loanId
        );
        $this->repository->creditUserBankLoanRepayment($bankId, $principal);
        $this->repository->updateBankLoan($loanId, [
            'UF_STATUS' => GameEconomyConfig::CONTRACT_STATUS_CLOSED,
            'UF_UPDATED_AT' => $now,
            'UF_CLOSED_AT' => $now,
        ]);

        return BankLoanService::formatContract($this->repository->getBankLoanById($loanId));
    }

    public function cancelDeposit(int $userId, int $depositId): array
    {
        $deposit = $this->repository->getBankDepositById($depositId);
        if (!$deposit) {
            throw new \RuntimeException('Вклад не найден');
        }

        if ((int)($deposit['UF_USER_ID'] ?? 0) !== $userId) {
            throw new \RuntimeException('Нет доступа к этому вкладу');
        }

        $check = $this->evaluateCancelEligibility($deposit, 'deposit');
        if (!($check['can_cancel'] ?? false)) {
            throw new \RuntimeException($this->cancelBlockMessage($check['reason'] ?? ''));
        }

        $bankId = (int)($deposit['UF_BANK_ID'] ?? 0);
        $principal = round((float)($deposit['UF_PRINCIPAL'] ?? 0), 1);
        $now = new DateTime();

        $this->repository->adjustUserBankLiquid($bankId, -$principal);
        $this->walletService->credit(
            $userId,
            GameEconomyConfig::CURRENCY_PROGNOBAKS,
            $principal,
            'bank_deposit_cancel',
            'deposit',
            $depositId
        );
        $this->repository->updateBankDeposit($depositId, [
            'UF_STATUS' => GameEconomyConfig::CONTRACT_STATUS_CLOSED,
            'UF_UPDATED_AT' => $now,
            'UF_CLOSED_AT' => $now,
        ]);

        return BankDepositService::formatContract($this->repository->getBankDepositById($depositId));
    }

    /**
     * Досрочное изъятие вклада: только тело, без процентов (если в банке есть ликвидность).
     *
     * @return array{can_force_close:bool,reason?:string}
     */
    public function evaluateForceCloseEligibility(array $contractRow): array
    {
        if (GovSupportDepositService::isGovSupportDeposit($contractRow)) {
            return GovSupportDepositService::evaluateForceCloseEligibility($contractRow, $this->repository);
        }

        $status = (string)($contractRow['UF_STATUS'] ?? '');
        if ($status === GameEconomyConfig::CONTRACT_STATUS_CLOSED) {
            return ['can_force_close' => false, 'reason' => 'closed'];
        }

        if ($status !== GameEconomyConfig::CONTRACT_STATUS_ACTIVE
            && $status !== GameEconomyConfig::CONTRACT_STATUS_EXTENDED) {
            return ['can_force_close' => false, 'reason' => 'not_active'];
        }

        $contractId = (int)($contractRow['ID'] ?? 0);
        $userId = (int)($contractRow['UF_USER_ID'] ?? 0);
        $principal = round((float)($contractRow['UF_PRINCIPAL'] ?? 0), 1);

        if ($this->repository->hasWalletTx($userId, 'bank_deposit_return', 'deposit', $contractId)
            || $this->repository->hasWalletTx($userId, 'bank_deposit_return_half', 'deposit', $contractId)
            || $this->repository->hasWalletTx($userId, 'bank_deposit_cancel', 'deposit', $contractId)
            || $this->repository->hasWalletTx($userId, 'bank_deposit_early_close', 'deposit', $contractId)) {
            return ['can_force_close' => false, 'reason' => 'already_settled'];
        }

        $bankId = (int)($contractRow['UF_BANK_ID'] ?? 0);
        $bank = $this->repository->getUserBankById($bankId);
        if (!$bank) {
            return ['can_force_close' => false, 'reason' => 'bank_not_found'];
        }

        $liquid = round((float)($bank['UF_LIQUID'] ?? 0), 1);
        if ($liquid < $principal) {
            return ['can_force_close' => false, 'reason' => 'bank_liquid_moved'];
        }

        return ['can_force_close' => true];
    }

    public function forceCloseDeposit(int $userId, int $depositId): array
    {
        $deposit = $this->repository->getBankDepositById($depositId);
        if (!$deposit) {
            throw new \RuntimeException('Вклад не найден');
        }

        if ((int)($deposit['UF_USER_ID'] ?? 0) !== $userId) {
            throw new \RuntimeException('Нет доступа к этому вкладу');
        }

        if (GovSupportDepositService::isGovSupportDeposit($deposit)) {
            return (new GovSupportDepositService($this->repository))->forceCloseDeposit($userId, $depositId);
        }

        $check = $this->evaluateForceCloseEligibility($deposit);
        if (!($check['can_force_close'] ?? false)) {
            throw new \RuntimeException($this->forceCloseBlockMessage($check['reason'] ?? ''));
        }

        $bankId = (int)($deposit['UF_BANK_ID'] ?? 0);
        $principal = round((float)($deposit['UF_PRINCIPAL'] ?? 0), 1);
        $now = new DateTime();

        $this->repository->adjustUserBankLiquid($bankId, -$principal);
        $this->walletService->credit(
            $userId,
            GameEconomyConfig::CURRENCY_PROGNOBAKS,
            $principal,
            'bank_deposit_early_close',
            'deposit',
            $depositId
        );
        $this->repository->updateBankDeposit($depositId, [
            'UF_STATUS' => GameEconomyConfig::CONTRACT_STATUS_CLOSED,
            'UF_UPDATED_AT' => $now,
            'UF_CLOSED_AT' => $now,
        ]);

        return BankDepositService::formatContract($this->repository->getBankDepositById($depositId));
    }

    private function cancelBlockMessage(string $reason): string
    {
        switch ($reason) {
            case 'match_tick_started':
            case 'next_match_settled':
                return 'Отмена недоступна: уже внесён результат следующего матча';
            case 'funds_spent':
                return 'Отмена недоступна: займ уже потрачен';
            case 'bank_liquid_moved':
                return 'Отмена недоступна: средства вклада уже использованы банком';
            case 'already_settled':
                return 'Контракт уже закрыт или изменён';
            case 'closed':
                return 'Контракт уже закрыт';
            default:
                return 'Отмена сейчас недоступна';
        }
    }

    private function forceCloseBlockMessage(string $reason): string
    {
        switch ($reason) {
            case 'bank_liquid_moved':
                return 'Досрочное изъятие недоступно: в банке нет свободной ликвидности';
            case 'already_settled':
                return 'Вклад уже закрыт или изменён';
            case 'closed':
                return 'Вклад уже закрыт';
            case 'interest_paid_use_close':
                return 'Проценты уже в казне — заберите вклад обычной кнопкой';
            default:
                return 'Досрочное изъятие сейчас недоступно';
        }
    }
}
