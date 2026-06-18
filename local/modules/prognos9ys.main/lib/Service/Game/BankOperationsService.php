<?php

namespace Prognos9ys\Main\Service\Game;

use Bitrix\Main\Type\DateTime;
use Prognos9ys\Main\Model\Repository\GameEconomyRepository;

class BankOperationsService
{
    private const REASON_LABELS = [
        'bank_reserve_lock' => 'Резерв при открытии банка',
        'bank_reserve_unlock' => 'Возврат резерва банка',
        'bank_deposit' => 'Вклад в банк',
        'bank_deposit_return' => 'Возврат вклада',
        'bank_deposit_return_half' => 'Частичный возврат вклада',
        'bank_deposit_interest' => 'Проценты по вкладу',
        'bank_loan' => 'Займ из банка',
        'bank_loan_repay' => 'Погашение займа',
        'bank_loan_interest' => 'Проценты по займу',
    ];

    private const REASON_CATEGORY = [
        'bank_reserve_lock' => 'all',
        'bank_reserve_unlock' => 'returns',
        'bank_deposit' => 'deposits',
        'bank_deposit_return' => 'returns',
        'bank_deposit_return_half' => 'returns',
        'bank_deposit_interest' => 'returns',
        'bank_loan' => 'loans',
        'bank_loan_repay' => 'returns',
        'bank_loan_interest' => 'returns',
    ];

    private GameEconomyRepository $repository;

    public function __construct(?GameEconomyRepository $repository = null)
    {
        $this->repository = $repository ?? new GameEconomyRepository();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getForUser(int $userId, int $limit = 100): array
    {
        if ($userId <= 0) {
            return [];
        }

        $operations = [];

        foreach ($this->repository->getBankWalletTxByUserId($userId, $limit) as $row) {
            $operations[] = $this->formatWalletTx($row);
        }

        $myBank = $this->repository->getUserBankByOwnerId($userId);
        if ($myBank) {
            $bankId = (int)$myBank['ID'];
            foreach ($this->repository->getDepositsByBankId($bankId) as $deposit) {
                $operations = array_merge($operations, $this->formatBankDepositEvents($deposit, $bankId));
            }
            foreach ($this->repository->getLoansByBankId($bankId) as $loan) {
                $operations = array_merge($operations, $this->formatBankLoanEvents($loan, $bankId));
            }
        }

        usort($operations, static function (array $a, array $b): int {
            return strcmp((string)($b['at'] ?? ''), (string)($a['at'] ?? ''));
        });

        if (count($operations) > $limit) {
            $operations = array_slice($operations, 0, $limit);
        }

        return array_values($operations);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function formatWalletTx(array $row): array
    {
        $reason = (string)($row['UF_REASON'] ?? '');
        $amount = round((float)($row['UF_AMOUNT'] ?? 0), 1);
        $refType = (string)($row['UF_REF_TYPE'] ?? '');
        $refId = (int)($row['UF_REF_ID'] ?? 0);

        return [
            'id' => 'tx_' . (int)$row['ID'],
            'at' => $this->formatDateTime($row['UF_CREATED_AT'] ?? null),
            'scope' => 'wallet',
            'category' => self::REASON_CATEGORY[$reason] ?? 'all',
            'reason' => $reason,
            'label' => $this->buildWalletLabel($reason, $refType, $refId),
            'amount' => $amount,
            'direction' => $amount >= 0 ? 'in' : 'out',
            'currency' => (string)($row['UF_CURRENCY'] ?? GameEconomyConfig::CURRENCY_PROGNOBAKS),
            'balance_after' => round((float)($row['UF_BALANCE_AFTER'] ?? 0), 1),
            'contract_id' => $refType === 'deposit' || $refType === 'loan' ? $refId : 0,
            'bank_id' => $refType === 'bank' ? $refId : 0,
        ];
    }

    /**
     * @param array<string, mixed> $deposit
     * @return array<int, array<string, mixed>>
     */
    private function formatBankDepositEvents(array $deposit, int $bankId): array
    {
        $depositId = (int)$deposit['ID'];
        $principal = round((float)($deposit['UF_PRINCIPAL'] ?? 0), 1);
        $clientId = (int)($deposit['UF_USER_ID'] ?? 0);
        $status = (string)($deposit['UF_STATUS'] ?? '');
        $events = [];

        $events[] = [
            'id' => 'dep_in_' . $depositId,
            'at' => $this->formatDateTime($deposit['UF_CREATED_AT'] ?? null),
            'scope' => 'bank',
            'category' => 'deposits',
            'reason' => 'bank_client_deposit',
            'label' => 'Вклад клиента #' . $depositId,
            'amount' => $principal,
            'direction' => 'in',
            'currency' => GameEconomyConfig::CURRENCY_PROGNOBAKS,
            'balance_after' => null,
            'contract_id' => $depositId,
            'bank_id' => $bankId,
            'counterparty_id' => $clientId,
        ];

        if ($status === GameEconomyConfig::CONTRACT_STATUS_CLOSED) {
            $interest = GameEconomyConfig::calculateDepositInterest($principal);
            $payout = round($principal + $interest, 1);
            $events[] = [
                'id' => 'dep_out_' . $depositId,
                'at' => $this->formatDateTime($deposit['UF_CLOSED_AT'] ?? $deposit['UF_UPDATED_AT'] ?? null),
                'scope' => 'bank',
                'category' => 'returns',
                'reason' => 'bank_client_deposit_payout',
                'label' => 'Выплата по вкладу #' . $depositId,
                'amount' => -$payout,
                'direction' => 'out',
                'currency' => GameEconomyConfig::CURRENCY_PROGNOBAKS,
                'balance_after' => null,
                'contract_id' => $depositId,
                'bank_id' => $bankId,
                'counterparty_id' => $clientId,
            ];
        }

        return $events;
    }

    /**
     * @param array<string, mixed> $loan
     * @return array<int, array<string, mixed>>
     */
    private function formatBankLoanEvents(array $loan, int $bankId): array
    {
        $loanId = (int)$loan['ID'];
        $principal = round((float)($loan['UF_PRINCIPAL'] ?? 0), 1);
        $clientId = (int)($loan['UF_USER_ID'] ?? 0);
        $status = (string)($loan['UF_STATUS'] ?? '');
        $events = [];

        $events[] = [
            'id' => 'loan_out_' . $loanId,
            'at' => $this->formatDateTime($loan['UF_CREATED_AT'] ?? null),
            'scope' => 'bank',
            'category' => 'loans',
            'reason' => 'bank_client_loan',
            'label' => 'Выдача займа #' . $loanId,
            'amount' => -$principal,
            'direction' => 'out',
            'currency' => GameEconomyConfig::CURRENCY_PROGNOBAKS,
            'balance_after' => null,
            'contract_id' => $loanId,
            'bank_id' => $bankId,
            'counterparty_id' => $clientId,
        ];

        if ($status === GameEconomyConfig::CONTRACT_STATUS_CLOSED) {
            $total = round($principal + GameEconomyConfig::calculateLoanInterest($principal), 1);
            $events[] = [
                'id' => 'loan_in_' . $loanId,
                'at' => $this->formatDateTime($loan['UF_CLOSED_AT'] ?? $loan['UF_UPDATED_AT'] ?? null),
                'scope' => 'bank',
                'category' => 'returns',
                'reason' => 'bank_client_loan_repay',
                'label' => 'Погашение займа #' . $loanId,
                'amount' => $total,
                'direction' => 'in',
                'currency' => GameEconomyConfig::CURRENCY_PROGNOBAKS,
                'balance_after' => null,
                'contract_id' => $loanId,
                'bank_id' => $bankId,
                'counterparty_id' => $clientId,
            ];
        }

        return $events;
    }

    private function buildWalletLabel(string $reason, string $refType, int $refId): string
    {
        $base = self::REASON_LABELS[$reason] ?? $reason;
        if ($refId <= 0) {
            return $base;
        }

        if ($refType === 'deposit' || $refType === 'loan') {
            return $base . ' #' . $refId;
        }

        if ($refType === 'bank') {
            return $base . ' (банк #' . $refId . ')';
        }

        return $base;
    }

    /**
     * @param mixed $value
     */
    private function formatDateTime($value): string
    {
        if ($value instanceof DateTime) {
            return $value->format('d.m.Y H:i');
        }

        if (is_string($value) && $value !== '') {
            $ts = strtotime($value);

            return $ts !== false ? date('d.m.Y H:i', $ts) : $value;
        }

        return '';
    }
}
