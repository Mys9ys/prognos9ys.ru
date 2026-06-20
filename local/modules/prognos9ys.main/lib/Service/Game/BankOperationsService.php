<?php

namespace Prognos9ys\Main\Service\Game;

use Bitrix\Main\Type\DateTime;
use Bitrix\Main\UserTable;
use Prognos9ys\Main\Model\Repository\GameEconomyRepository;

class BankOperationsService
{
    private const REASON_LABELS = [
        'bank_reserve_lock' => 'Резерв при открытии банка',
        'bank_reserve_unlock' => 'Возврат резерва банка',
        'bank_deposit' => 'Вклад в банк',
        'bank_deposit_return' => 'Возврат тела вклада',
        'bank_deposit_return_half' => 'Частичный возврат вклада',
        'bank_deposit_interest' => 'Проценты по вкладу',
        'bank_deposit_interest_rollback' => 'Откат процентов по вкладу',
        'bank_deposit_cancel' => 'Отмена вклада',
        'bank_loan' => 'Займ из банка',
        'bank_loan_cancel' => 'Отмена займа',
        'bank_loan_repay' => 'Погашение займа',
        'bank_loan_interest' => 'Проценты по займу',
        'bank_client_deposit' => 'Вклад клиента',
        'bank_client_deposit_cancel' => 'Отмена вклада клиента',
        'bank_client_deposit_payout' => 'Выплата по вкладу',
        'bank_client_deposit_principal' => 'Возврат тела вклада',
        'bank_client_deposit_interest' => 'Проценты по вкладу клиента',
        'bank_client_loan' => 'Выдача займа',
        'bank_client_loan_cancel' => 'Отмена займа клиента',
        'bank_client_loan_repay' => 'Погашение займа',
        'bank_client_loan_interest' => 'Проценты по займу клиента',
    ];

    private const REASON_CATEGORY = [
        'bank_reserve_lock' => 'all',
        'bank_reserve_unlock' => 'returns',
        'bank_deposit' => 'deposits',
        'bank_deposit_cancel' => 'returns',
        'bank_deposit_return' => 'returns',
        'bank_deposit_return_half' => 'returns',
        'bank_deposit_interest' => 'returns',
        'bank_deposit_interest_rollback' => 'returns',
        'bank_loan' => 'loans',
        'bank_loan_cancel' => 'returns',
        'bank_loan_repay' => 'returns',
        'bank_loan_interest' => 'returns',
        'bank_client_deposit' => 'deposits',
        'bank_client_deposit_payout' => 'returns',
        'bank_client_deposit_principal' => 'returns',
        'bank_client_deposit_interest' => 'returns',
        'bank_client_loan' => 'loans',
        'bank_client_loan_repay' => 'returns',
        'bank_client_loan_interest' => 'returns',
    ];

    private GameEconomyRepository $repository;
    private GameEventScopeService $scopeService;

    /** @var array<int, string> */
    private array $userNameCache = [];

    public function __construct(
        ?GameEconomyRepository $repository = null,
        ?GameEventScopeService $scopeService = null
    ) {
        $this->repository = $repository ?? new GameEconomyRepository();
        $this->scopeService = $scopeService ?? new GameEventScopeService();
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
        $seenIds = [];

        foreach ($this->repository->getBankWalletTxByUserId($userId, $limit) as $row) {
            $event = $this->formatWalletTx($row);
            $seenIds[$event['id']] = true;
            $operations[] = $event;
        }

        $myBank = $this->repository->getUserBankByOwnerId($userId);
        if ($myBank) {
            $bankId = (int)$myBank['ID'];
            $depositIds = [];
            $loanIds = [];
            $rolledBackDepositIds = $this->getRolledBackDepositIds($bankId);

            foreach ($this->repository->getDepositsByBankId($bankId) as $deposit) {
                $depositIds[] = (int)$deposit['ID'];
                foreach ($this->formatBankDepositEvents($deposit, $bankId) as $event) {
                    if (!isset($seenIds[$event['id']])) {
                        $seenIds[$event['id']] = true;
                        $operations[] = $event;
                    }
                }
            }

            foreach ($this->repository->getLoansByBankId($bankId) as $loan) {
                $loanIds[] = (int)$loan['ID'];
                foreach ($this->formatBankLoanEvents($loan, $bankId) as $event) {
                    if (!isset($seenIds[$event['id']])) {
                        $seenIds[$event['id']] = true;
                        $operations[] = $event;
                    }
                }
            }

            foreach ($this->repository->getWalletTxByRefs('deposit', $depositIds, [
                'bank_deposit_interest',
                'bank_deposit_interest_rollback',
                'bank_deposit_cancel',
                'bank_deposit_return',
                'bank_deposit_return_half',
            ]) as $row) {
                $reason = (string)($row['UF_REASON'] ?? '');
                $depositId = (int)($row['UF_REF_ID'] ?? 0);
                if ($reason === 'bank_deposit_interest' && isset($rolledBackDepositIds[$depositId])) {
                    continue;
                }

                if (
                    ($reason === 'bank_deposit_return' || $reason === 'bank_deposit_return_half')
                    && $depositId > 0
                ) {
                    $depositRow = $this->repository->getBankDepositById($depositId);
                    if (
                        $depositRow
                        && ($depositRow['UF_STATUS'] ?? '') === GameEconomyConfig::CONTRACT_STATUS_CLOSED
                        && !$this->repository->hasWalletTx(
                            (int)($depositRow['UF_USER_ID'] ?? 0),
                            'bank_deposit_cancel',
                            'deposit',
                            $depositId
                        )
                    ) {
                        continue;
                    }
                }

                $event = $this->formatContractWalletTxForBankOwner($row, 'deposit');
                if (!isset($seenIds[$event['id']])) {
                    $seenIds[$event['id']] = true;
                    $operations[] = $event;
                }
            }

            foreach ($this->repository->getWalletTxByRefs('loan', $loanIds, [
                'bank_loan_cancel',
                'bank_loan_repay',
                'bank_loan_interest',
            ]) as $row) {
                $reason = (string)($row['UF_REASON'] ?? '');
                $loanId = (int)($row['UF_REF_ID'] ?? 0);
                if ($reason === 'bank_loan_repay' && $loanId > 0) {
                    $loanRow = $this->repository->getBankLoanById($loanId);
                    if (
                        $loanRow
                        && ($loanRow['UF_STATUS'] ?? '') === GameEconomyConfig::CONTRACT_STATUS_CLOSED
                        && !$this->repository->hasWalletTx(
                            (int)($loanRow['UF_USER_ID'] ?? 0),
                            'bank_loan_cancel',
                            'loan',
                            $loanId
                        )
                    ) {
                        continue;
                    }
                }

                $event = $this->formatContractWalletTxForBankOwner($row, 'loan');
                if (!isset($seenIds[$event['id']])) {
                    $seenIds[$event['id']] = true;
                    $operations[] = $event;
                }
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
     * @return array<int, true>
     */
    private function getRolledBackDepositIds(int $bankId): array
    {
        $depositIds = array_map(
            static fn(array $row): int => (int)$row['ID'],
            $this->repository->getDepositsByBankId($bankId)
        );

        $rolledBack = [];
        foreach ($this->repository->getWalletTxByRefs('deposit', $depositIds, [
            'bank_deposit_interest_rollback',
        ]) as $tx) {
            $depositId = (int)($tx['UF_REF_ID'] ?? 0);
            if ($depositId > 0) {
                $rolledBack[$depositId] = true;
            }
        }

        return $rolledBack;
    }

    /**
     * Сводка по банку за всё время (для владельца).
     *
     * @return array{
     *     total_loan_interest_earned: float,
     *     total_deposit_paid: float,
     *     total_deposit_principal_returned: float,
     *     total_deposit_interest_paid: float
     * }
     */
    public function getLifetimeTotalsForBank(int $bankId): array
    {
        if ($bankId <= 0) {
            return [
                'total_loan_interest_earned' => 0.0,
                'total_deposit_paid' => 0.0,
                'total_deposit_principal_returned' => 0.0,
                'total_deposit_interest_paid' => 0.0,
            ];
        }

        $depositTotals = $this->calculateDepositLifetimeTotals($bankId);

        $loanIds = array_map(
            static fn(array $row): int => (int)$row['ID'],
            $this->repository->getLoansByBankId($bankId)
        );

        $loanPrincipalById = [];
        foreach ($this->repository->getLoansByBankId($bankId) as $loan) {
            $loanPrincipalById[(int)$loan['ID']] = round((float)($loan['UF_PRINCIPAL'] ?? 0), 1);
        }

        $totalLoanInterestEarned = 0.0;
        foreach ($this->repository->getWalletTxByRefs('loan', $loanIds, [
            'bank_loan_interest',
            'bank_loan_repay',
        ]) as $tx) {
            $reason = (string)($tx['UF_REASON'] ?? '');

            if ($reason === 'bank_loan_interest') {
                $totalLoanInterestEarned = round(
                    $totalLoanInterestEarned + abs((float)($tx['UF_AMOUNT'] ?? 0)),
                    1
                );
                continue;
            }

            if ($reason === 'bank_loan_repay') {
                $loanId = (int)($tx['UF_REF_ID'] ?? 0);
                $principal = $loanPrincipalById[$loanId] ?? 0.0;
                $totalLoanInterestEarned = round(
                    $totalLoanInterestEarned + GameEconomyConfig::calculateLoanInterest($principal),
                    1
                );
            }
        }

        return [
            'total_loan_interest_earned' => $totalLoanInterestEarned,
            'total_deposit_paid' => round(
                $depositTotals['total_deposit_principal_returned'] + $depositTotals['total_deposit_interest_paid'],
                1
            ),
            'total_deposit_principal_returned' => $depositTotals['total_deposit_principal_returned'],
            'total_deposit_interest_paid' => $depositTotals['total_deposit_interest_paid'],
        ];
    }

    /**
     * @return array{total_deposit_principal_returned: float, total_deposit_interest_paid: float}
     */
    private function calculateDepositLifetimeTotals(int $bankId): array
    {
        $principalReturned = 0.0;
        $interestPaid = 0.0;

        foreach ($this->repository->getDepositsByBankId($bankId) as $deposit) {
            $depositId = (int)$deposit['ID'];
            $clientId = (int)($deposit['UF_USER_ID'] ?? 0);
            $principal = round((float)($deposit['UF_PRINCIPAL'] ?? 0), 1);
            if ($depositId <= 0 || $clientId <= 0) {
                continue;
            }

            $interestFromTx = 0.0;
            $returnFromTx = 0.0;

            foreach ($this->repository->getWalletTxByRefs('deposit', [$depositId], [
                'bank_deposit_return',
                'bank_deposit_return_half',
                'bank_deposit_interest',
                'bank_deposit_interest_rollback',
            ]) as $tx) {
                if ((int)($tx['UF_USER_ID'] ?? 0) !== $clientId) {
                    continue;
                }

                $reason = (string)($tx['UF_REASON'] ?? '');
                $amount = abs(round((float)($tx['UF_AMOUNT'] ?? 0), 1));

                if ($reason === 'bank_deposit_interest') {
                    $interestFromTx = round($interestFromTx + $amount, 1);
                    continue;
                }

                if ($reason === 'bank_deposit_interest_rollback') {
                    $interestFromTx = round($interestFromTx - $amount, 1);
                    continue;
                }

                if ($reason === 'bank_deposit_return' || $reason === 'bank_deposit_return_half') {
                    $returnFromTx = round($returnFromTx + $amount, 1);
                }
            }

            $interestFromTx = max(0.0, $interestFromTx);
            $interestPaid = round($interestPaid + $interestFromTx, 1);

            if ($returnFromTx <= 0) {
                continue;
            }

            if ($interestFromTx > 0) {
                $principalPart = min($returnFromTx, $principal);
                $principalReturned = round($principalReturned + $principalPart, 1);
                // Старые выплаты: проценты уже в bank_deposit_interest, но return всё ещё 114.
                if ($returnFromTx > $principal + 0.05) {
                    $interestPaid = round($interestPaid + ($returnFromTx - $principal), 1);
                }
                continue;
            }

            $calcInterest = GameEconomyConfig::calculateDepositInterest($principal);
            $fullReturn = round($principal + $calcInterest, 1);

            if ($calcInterest > 0 && $returnFromTx >= $fullReturn - 0.05) {
                $principalReturned = round($principalReturned + $principal, 1);
                $interestPaid = round($interestPaid + $calcInterest, 1);
                continue;
            }

            $principalReturned = round($principalReturned + min($returnFromTx, $principal), 1);
        }

        return [
            'total_deposit_principal_returned' => max(0.0, round($principalReturned, 1)),
            'total_deposit_interest_paid' => max(0.0, round($interestPaid, 1)),
        ];
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
        $userId = (int)($row['UF_USER_ID'] ?? 0);
        [$counterpartyId, $counterpartyName] = $this->resolveCounterpartyForWalletTx($refType, $refId);

        return [
            'id' => 'tx_' . (int)$row['ID'],
            'at' => $this->formatDateTime($row['UF_CREATED_AT'] ?? null),
            'scope' => 'wallet',
            'category' => self::REASON_CATEGORY[$reason] ?? 'all',
            'reason' => $reason,
            'label' => $this->buildWalletLabel($reason, $refType, $refId, $counterpartyId, $counterpartyName),
            'amount' => $amount,
            'direction' => $amount >= 0 ? 'in' : 'out',
            'currency' => (string)($row['UF_CURRENCY'] ?? GameEconomyConfig::CURRENCY_PROGNOBAKS),
            'balance_after' => round((float)($row['UF_BALANCE_AFTER'] ?? 0), 1),
            'contract_id' => $refType === 'deposit' || $refType === 'loan' ? $refId : 0,
            'bank_id' => $refType === 'bank' ? $refId : 0,
            'counterparty_id' => $counterpartyId,
            'counterparty_name' => $counterpartyName,
            'match_id' => 0,
            'match_label' => '',
        ];
    }

    /**
     * @return array{0:int,1:string}
     */
    private function resolveCounterpartyForWalletTx(string $refType, int $refId): array
    {
        if ($refId <= 0) {
            return [0, ''];
        }

        if ($refType === 'bank') {
            $bank = $this->repository->getUserBankById($refId);
            $ownerId = (int)($bank['UF_OWNER_ID'] ?? 0);

            return [$ownerId, $this->resolveUserName($ownerId)];
        }

        if ($refType === 'deposit') {
            $deposit = $this->repository->getBankDepositById($refId);
            if (!$deposit) {
                return [0, ''];
            }

            $bank = $this->repository->getUserBankById((int)($deposit['UF_BANK_ID'] ?? 0));
            $ownerId = (int)($bank['UF_OWNER_ID'] ?? 0);

            return [$ownerId, $this->resolveUserName($ownerId)];
        }

        if ($refType === 'loan') {
            $loan = $this->repository->getBankLoanById($refId);
            if (!$loan) {
                return [0, ''];
            }

            $bank = $this->repository->getUserBankById((int)($loan['UF_BANK_ID'] ?? 0));
            $ownerId = (int)($bank['UF_OWNER_ID'] ?? 0);

            return [$ownerId, $this->resolveUserName($ownerId)];
        }

        return [0, ''];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function formatContractWalletTxForBankOwner(array $row, string $contractType): array
    {
        $reason = (string)($row['UF_REASON'] ?? '');
        $rawAmount = round((float)($row['UF_AMOUNT'] ?? 0), 1);
        $refId = (int)($row['UF_REF_ID'] ?? 0);
        $clientId = (int)($row['UF_USER_ID'] ?? 0);
        $clientName = $this->resolveUserName($clientId);
        $isRollback = $reason === 'bank_deposit_interest_rollback';
        $isLoanCancel = $reason === 'bank_loan_cancel';
        $isDepositCancel = $reason === 'bank_deposit_cancel';
        if ($isRollback || $isLoanCancel) {
            $amount = abs($rawAmount);
        } elseif ($isDepositCancel) {
            $amount = -abs($rawAmount);
        } else {
            $amount = -abs($rawAmount);
        }

        return [
            'id' => 'bank_tx_' . (int)$row['ID'],
            'at' => $this->formatDateTime($row['UF_CREATED_AT'] ?? null),
            'scope' => 'bank',
            'category' => self::REASON_CATEGORY[$reason] ?? 'returns',
            'reason' => $reason,
            'label' => $this->buildWalletLabel($reason, $contractType, $refId, $clientId, $clientName),
            'amount' => $amount,
            'direction' => ($isRollback || $isLoanCancel) ? 'in' : 'out',
            'currency' => (string)($row['UF_CURRENCY'] ?? GameEconomyConfig::CURRENCY_PROGNOBAKS),
            'balance_after' => null,
            'contract_id' => $refId,
            'bank_id' => 0,
            'counterparty_id' => $clientId,
            'counterparty_name' => $clientName,
            'match_id' => 0,
            'match_label' => '',
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
        $clientName = $this->resolveUserName($clientId);
        $status = (string)($deposit['UF_STATUS'] ?? '');
        $openingMatchId = (int)($deposit['UF_OPENING_MATCH_ID'] ?? 0);
        $lastTickMatchId = (int)($deposit['UF_LAST_TICK_MATCH_ID'] ?? 0);
        $matchLabel = $this->buildContractMatchLabel($openingMatchId, $lastTickMatchId);
        $events = [];

        $events[] = [
            'id' => 'dep_in_' . $depositId,
            'at' => $this->formatDateTime($deposit['UF_CREATED_AT'] ?? null),
            'scope' => 'bank',
            'category' => 'deposits',
            'reason' => 'bank_client_deposit',
            'label' => $this->buildContractLabel('Вклад клиента', $depositId, $clientName, $matchLabel),
            'amount' => $principal,
            'direction' => 'in',
            'currency' => GameEconomyConfig::CURRENCY_PROGNOBAKS,
            'balance_after' => null,
            'contract_id' => $depositId,
            'bank_id' => $bankId,
            'counterparty_id' => $clientId,
            'counterparty_name' => $clientName,
            'match_id' => $openingMatchId,
            'match_label' => $matchLabel,
        ];

        if ($status === GameEconomyConfig::CONTRACT_STATUS_CLOSED) {
            $closedAt = $this->formatDateTime($deposit['UF_CLOSED_AT'] ?? $deposit['UF_UPDATED_AT'] ?? null);

            if ($this->repository->hasWalletTx($clientId, 'bank_deposit_cancel', 'deposit', $depositId)) {
                $events[] = [
                    'id' => 'dep_cancel_' . $depositId,
                    'at' => $closedAt,
                    'scope' => 'bank',
                    'category' => 'returns',
                    'reason' => 'bank_client_deposit_cancel',
                    'label' => $this->buildContractLabel('Отмена вклада', $depositId, $clientName, $matchLabel),
                    'amount' => -$principal,
                    'direction' => 'out',
                    'currency' => GameEconomyConfig::CURRENCY_PROGNOBAKS,
                    'balance_after' => null,
                    'contract_id' => $depositId,
                    'bank_id' => $bankId,
                    'counterparty_id' => $clientId,
                    'counterparty_name' => $clientName,
                    'match_id' => $openingMatchId,
                    'match_label' => $matchLabel,
                ];

                return $events;
            }

            $interest = GameEconomyConfig::calculateDepositInterest($principal);
            $interestAlreadyPaid = $this->repository->hasWalletTx(
                $clientId,
                'bank_deposit_interest',
                'deposit',
                $depositId
            );
            $closedMatchId = $lastTickMatchId;
            $closedMatchLabel = $this->scopeService->formatMatchLabel($closedMatchId);

            $events[] = [
                'id' => 'dep_principal_' . $depositId,
                'at' => $closedAt,
                'scope' => 'bank',
                'category' => 'returns',
                'reason' => 'bank_client_deposit_principal',
                'label' => $this->buildContractLabel('Возврат тела вклада', $depositId, $clientName, $closedMatchLabel),
                'amount' => -$principal,
                'direction' => 'out',
                'currency' => GameEconomyConfig::CURRENCY_PROGNOBAKS,
                'balance_after' => null,
                'contract_id' => $depositId,
                'bank_id' => $bankId,
                'counterparty_id' => $clientId,
                'counterparty_name' => $clientName,
                'match_id' => $closedMatchId,
                'match_label' => $closedMatchLabel,
            ];

            if ($interest > 0 && !$interestAlreadyPaid) {
                $events[] = [
                    'id' => 'dep_interest_' . $depositId,
                    'at' => $closedAt,
                    'scope' => 'bank',
                    'category' => 'returns',
                    'reason' => 'bank_client_deposit_interest',
                    'label' => $this->buildContractLabel('Проценты по вкладу', $depositId, $clientName, $closedMatchLabel),
                    'amount' => -$interest,
                    'direction' => 'out',
                    'currency' => GameEconomyConfig::CURRENCY_PROGNOBAKS,
                    'balance_after' => null,
                    'contract_id' => $depositId,
                    'bank_id' => $bankId,
                    'counterparty_id' => $clientId,
                    'counterparty_name' => $clientName,
                    'match_id' => $closedMatchId,
                    'match_label' => $closedMatchLabel,
                ];
            }
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
        $clientName = $this->resolveUserName($clientId);
        $status = (string)($loan['UF_STATUS'] ?? '');
        $openingMatchId = (int)($loan['UF_OPENING_MATCH_ID'] ?? 0);
        $lastTickMatchId = (int)($loan['UF_LAST_TICK_MATCH_ID'] ?? 0);
        $matchLabel = $this->buildContractMatchLabel($openingMatchId, $lastTickMatchId);
        $events = [];

        $events[] = [
            'id' => 'loan_out_' . $loanId,
            'at' => $this->formatDateTime($loan['UF_CREATED_AT'] ?? null),
            'scope' => 'bank',
            'category' => 'loans',
            'reason' => 'bank_client_loan',
            'label' => $this->buildContractLabel('Выдача займа', $loanId, $clientName, $matchLabel),
            'amount' => -$principal,
            'direction' => 'out',
            'currency' => GameEconomyConfig::CURRENCY_PROGNOBAKS,
            'balance_after' => null,
            'contract_id' => $loanId,
            'bank_id' => $bankId,
            'counterparty_id' => $clientId,
            'counterparty_name' => $clientName,
            'match_id' => $openingMatchId,
            'match_label' => $matchLabel,
        ];

        if ($status === GameEconomyConfig::CONTRACT_STATUS_CLOSED) {
            $closedAt = $this->formatDateTime($loan['UF_CLOSED_AT'] ?? $loan['UF_UPDATED_AT'] ?? null);

            if ($this->repository->hasWalletTx($clientId, 'bank_loan_cancel', 'loan', $loanId)) {
                $events[] = [
                    'id' => 'loan_cancel_' . $loanId,
                    'at' => $closedAt,
                    'scope' => 'bank',
                    'category' => 'returns',
                    'reason' => 'bank_client_loan_cancel',
                    'label' => $this->buildContractLabel('Отмена займа', $loanId, $clientName, $matchLabel),
                    'amount' => $principal,
                    'direction' => 'in',
                    'currency' => GameEconomyConfig::CURRENCY_PROGNOBAKS,
                    'balance_after' => null,
                    'contract_id' => $loanId,
                    'bank_id' => $bankId,
                    'counterparty_id' => $clientId,
                    'counterparty_name' => $clientName,
                    'match_id' => $openingMatchId,
                    'match_label' => $matchLabel,
                ];

                return $events;
            }

            $interest = GameEconomyConfig::calculateLoanInterest($principal);
            $closedMatchId = $lastTickMatchId;
            $closedMatchLabel = $this->scopeService->formatMatchLabel($closedMatchId);

            $events[] = [
                'id' => 'loan_principal_' . $loanId,
                'at' => $closedAt,
                'scope' => 'bank',
                'category' => 'returns',
                'reason' => 'bank_client_loan_repay',
                'label' => $this->buildContractLabel('Погашение тела займа', $loanId, $clientName, $closedMatchLabel),
                'amount' => $principal,
                'direction' => 'in',
                'currency' => GameEconomyConfig::CURRENCY_PROGNOBAKS,
                'balance_after' => null,
                'contract_id' => $loanId,
                'bank_id' => $bankId,
                'counterparty_id' => $clientId,
                'counterparty_name' => $clientName,
                'match_id' => $closedMatchId,
                'match_label' => $closedMatchLabel,
            ];

            if ($interest > 0) {
                $events[] = [
                    'id' => 'loan_interest_' . $loanId,
                    'at' => $closedAt,
                    'scope' => 'bank',
                    'category' => 'returns',
                    'reason' => 'bank_client_loan_interest',
                    'label' => $this->buildContractLabel('Проценты по займу', $loanId, $clientName, $closedMatchLabel),
                    'amount' => $interest,
                    'direction' => 'in',
                    'currency' => GameEconomyConfig::CURRENCY_PROGNOBAKS,
                    'balance_after' => null,
                    'contract_id' => $loanId,
                    'bank_id' => $bankId,
                    'counterparty_id' => $clientId,
                    'counterparty_name' => $clientName,
                    'match_id' => $closedMatchId,
                    'match_label' => $closedMatchLabel,
                ];
            }
        }

        return $events;
    }

    private function buildWalletLabel(
        string $reason,
        string $refType,
        int $refId,
        int $userId = 0,
        string $userName = ''
    ): string {
        $base = self::REASON_LABELS[$reason] ?? $reason;
        $parts = [$base];

        if ($refId > 0 && ($refType === 'deposit' || $refType === 'loan')) {
            $parts[0] .= ' #' . $refId;
        } elseif ($refId > 0 && $refType === 'bank') {
            $parts[0] .= ' (банк #' . $refId . ')';
        }

        $name = $userName;
        if ($name !== '' && in_array($reason, [
            'bank_deposit',
            'bank_deposit_cancel',
            'bank_deposit_return',
            'bank_deposit_return_half',
            'bank_deposit_interest',
            'bank_deposit_interest_rollback',
            'bank_loan',
            'bank_loan_cancel',
            'bank_loan_repay',
            'bank_loan_interest',
        ], true)) {
            $parts[] = $name;
        }

        return implode(' — ', array_filter($parts));
    }

    private function buildContractLabel(string $base, int $contractId, string $clientName, string $matchLabel): string
    {
        $parts = [$base . ' #' . $contractId];

        if ($clientName !== '') {
            $parts[] = $clientName;
        }

        if ($matchLabel !== '') {
            $parts[] = $matchLabel;
        }

        return implode(' — ', $parts);
    }

    private function buildContractMatchLabel(int $openingMatchId, int $lastTickMatchId): string
    {
        if ($openingMatchId > 0) {
            return 'от ' . $this->scopeService->formatMatchLabel($openingMatchId);
        }

        if ($lastTickMatchId > 0) {
            return 'тик: ' . $this->scopeService->formatMatchLabel($lastTickMatchId);
        }

        return '';
    }

    private function resolveUserName(int $userId): string
    {
        if ($userId <= 0) {
            return '';
        }

        if (isset($this->userNameCache[$userId])) {
            return $this->userNameCache[$userId];
        }

        $row = UserTable::getList([
            'filter' => ['=ID' => $userId],
            'select' => ['ID', 'LOGIN', 'NAME', 'LAST_NAME'],
            'limit' => 1,
        ])->fetch();

        if (!$row) {
            $this->userNameCache[$userId] = 'user#' . $userId;

            return $this->userNameCache[$userId];
        }

        $name = trim(($row['NAME'] ?? '') . ' ' . ($row['LAST_NAME'] ?? ''));
        $this->userNameCache[$userId] = $name !== ''
            ? $name
            : (string)($row['LOGIN'] ?? ('user#' . $userId));

        return $this->userNameCache[$userId];
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
