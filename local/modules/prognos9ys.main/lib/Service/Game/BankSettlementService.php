<?php

namespace Prognos9ys\Main\Service\Game;

use Bitrix\Main\Type\DateTime;
use Prognos9ys\Main\Model\Repository\GameEconomyRepository;

class BankSettlementService
{
    private GameEconomyRepository $repository;
    private GameEventScopeService $scopeService;
    private BankDepositService $depositService;
    private BankLoanService $loanService;

    public function __construct(
        ?GameEconomyRepository $repository = null,
        ?GameEventScopeService $scopeService = null,
        ?BankDepositService $depositService = null,
        ?BankLoanService $loanService = null
    ) {
        $this->repository = $repository ?? new GameEconomyRepository();
        $this->scopeService = $scopeService ?? new GameEventScopeService();
        $this->depositService = $depositService ?? new BankDepositService($this->repository);
        $this->loanService = $loanService ?? new BankLoanService($this->repository);
    }

    public function onMatchSettled(int $matchId): void
    {
        if ($matchId <= 0 || !$this->scopeService->isMatchEligible($matchId)) {
            return;
        }

        $eventId = $this->scopeService->getAnchorEventId();
        if ($eventId <= 0) {
            return;
        }

        foreach ($this->repository->getActiveDepositsByEvent($eventId) as $deposit) {
            $this->tickDeposit($deposit, $matchId);
        }

        foreach ($this->repository->getActiveLoansByEvent($eventId) as $loan) {
            $this->tickLoan($loan, $matchId);
        }
    }

    private function tickDeposit(array $deposit, int $matchId): void
    {
        $depositId = (int)$deposit['ID'];
        $lastTick = (int)($deposit['UF_LAST_TICK_MATCH_ID'] ?? 0);
        if ($lastTick === $matchId) {
            return;
        }

        $matches = (int)($deposit['UF_MATCHES_SINCE_START'] ?? 0) + 1;
        $term = (int)($deposit['UF_TERM_MATCHES'] ?? GameEconomyConfig::BANK_TERM_MATCHES);
        $now = new DateTime();

        $this->repository->updateBankDeposit($depositId, [
            'UF_LAST_TICK_MATCH_ID' => $matchId,
            'UF_MATCHES_SINCE_START' => $matches,
            'UF_UPDATED_AT' => $now,
        ]);

        if ($matches < $term) {
            return;
        }

        $fresh = $this->repository->getBankDepositById($depositId);
        if ($fresh && ($fresh['UF_STATUS'] ?? '') !== GameEconomyConfig::CONTRACT_STATUS_CLOSED) {
            $this->depositService->processMaturity($fresh);
        }
    }

    private function tickLoan(array $loan, int $matchId): void
    {
        $loanId = (int)$loan['ID'];
        $lastTick = (int)($loan['UF_LAST_TICK_MATCH_ID'] ?? 0);
        if ($lastTick === $matchId) {
            return;
        }

        $matches = (int)($loan['UF_MATCHES_SINCE_START'] ?? 0) + 1;
        $term = (int)($loan['UF_TERM_MATCHES'] ?? GameEconomyConfig::BANK_TERM_MATCHES);
        $now = new DateTime();

        $this->repository->updateBankLoan($loanId, [
            'UF_LAST_TICK_MATCH_ID' => $matchId,
            'UF_MATCHES_SINCE_START' => $matches,
            'UF_UPDATED_AT' => $now,
        ]);

        if ($matches < $term) {
            return;
        }

        $fresh = $this->repository->getBankLoanById($loanId);
        if ($fresh && ($fresh['UF_STATUS'] ?? '') !== GameEconomyConfig::CONTRACT_STATUS_CLOSED) {
            $this->loanService->processMaturity($fresh);
        }
    }
}
