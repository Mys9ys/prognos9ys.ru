<?php

namespace Prognos9ys\Main\Service\Game;

use Bitrix\Main\Type\DateTime;
use Prognos9ys\Main\Model\Repository\GameEconomyRepository;

class GovSupportDepositService
{
    private GameEconomyRepository $repository;
    private WalletService $walletService;
    private TreasuryService $treasuryService;
    private GameEventScopeService $scopeService;

    public function __construct(
        ?GameEconomyRepository $repository = null,
        ?WalletService $walletService = null,
        ?TreasuryService $treasuryService = null,
        ?GameEventScopeService $scopeService = null
    ) {
        $this->repository = $repository ?? new GameEconomyRepository();
        $this->walletService = $walletService ?? new WalletService($this->repository);
        $this->treasuryService = $treasuryService ?? new TreasuryService($this->repository);
        $this->scopeService = $scopeService ?? new GameEventScopeService();
    }

    public function createDeposit(int $userId, int $bankId, ?int $eventId = null): array
    {
        if ($userId <= 0 || $bankId <= 0) {
            throw new \InvalidArgumentException('Некорректные параметры вклада');
        }

        $amount = GameEconomyConfig::GOV_SUPPORT_DEPOSIT_AMOUNT_PROGNOBAKS;

        $bank = $this->repository->getUserBankById($bankId);
        if (!$bank || ($bank['UF_ACTIVE'] ?? '') !== GameEconomyConfig::USER_BANK_STATUS_ACTIVE) {
            throw new \RuntimeException('Банк не найден или закрыт');
        }

        if ($this->hasActiveGovDeposit($userId)) {
            throw new \RuntimeException('У вас уже есть активный гос. вклад поддержки');
        }

        $this->walletService->debit(
            $userId,
            GameEconomyConfig::CURRENCY_PROGNOBAKS,
            $amount,
            'gov_support_deposit',
            'bank',
            $bankId
        );

        $this->treasuryService->credit(
            GameEconomyConfig::CURRENCY_PROGNOBAKS,
            $amount,
            'gov_support_deposit',
            $bankId
        );

        $eventId = $this->scopeService->resolveContractEventId($eventId);
        $lastSettledMatch = $this->scopeService->getLastSettledMatchForEvent($eventId);
        $now = new DateTime();

        $depositId = $this->repository->addBankDeposit([
            'UF_BANK_ID' => $bankId,
            'UF_USER_ID' => $userId,
            'UF_PRINCIPAL' => $amount,
            'UF_INTEREST_RATE' => GameEconomyConfig::GOV_SUPPORT_DEPOSIT_INTEREST_PERCENT,
            'UF_STATUS' => GameEconomyConfig::CONTRACT_STATUS_ACTIVE,
            'UF_MATCHES_SINCE_START' => 0,
            'UF_TERM_MATCHES' => GameEconomyConfig::BANK_TERM_MATCHES,
            'UF_EVENT_ID' => $eventId,
            'UF_OPENING_MATCH_ID' => $lastSettledMatch['id'],
            'UF_OPENING_MATCH_NUMBER' => $lastSettledMatch['number'],
            'UF_LAST_TICK_MATCH_ID' => 0,
            'UF_CONTRACT_TYPE' => GameEconomyConfig::CONTRACT_TYPE_GOV_SUPPORT,
            'UF_CREATED_AT' => $now,
            'UF_UPDATED_AT' => $now,
        ]);

        $this->repository->updateWalletTxRefForLastReason($userId, 'gov_support_deposit', 'deposit', $depositId);

        return self::formatContract($this->repository->getBankDepositById($depositId));
    }

    public function processMaturity(array $deposit): void
    {
        if (!$this->isGovSupportDeposit($deposit)) {
            return;
        }

        $depositId = (int)$deposit['ID'];
        $userId = (int)($deposit['UF_USER_ID'] ?? 0);
        $status = (string)($deposit['UF_STATUS'] ?? '');

        if ($status === GameEconomyConfig::CONTRACT_STATUS_CLOSED) {
            return;
        }

        if ($status === GameEconomyConfig::CONTRACT_STATUS_INTEREST_PAID) {
            return;
        }

        $principal = round((float)($deposit['UF_PRINCIPAL'] ?? 0), 1);
        $interest = GameEconomyConfig::calculateGovSupportInterest($principal);
        $now = new DateTime();

        if ($interest > 0) {
            if (!$this->treasuryService->hasFunds(GameEconomyConfig::CURRENCY_PROGNOBAKS, $interest)) {
                return;
            }

            $this->treasuryService->debit(
                GameEconomyConfig::CURRENCY_PROGNOBAKS,
                $interest,
                'gov_support_interest',
                $depositId
            );

            $this->walletService->credit(
                $userId,
                GameEconomyConfig::CURRENCY_PROGNOBAKS,
                $interest,
                'gov_support_interest',
                'deposit',
                $depositId
            );
        }

        $this->repository->updateBankDeposit($depositId, [
            'UF_STATUS' => GameEconomyConfig::CONTRACT_STATUS_INTEREST_PAID,
            'UF_UPDATED_AT' => $now,
        ]);
    }

    public function closeDeposit(int $userId, int $depositId): array
    {
        $deposit = $this->repository->getBankDepositById($depositId);
        if (!$deposit) {
            throw new \RuntimeException('Вклад не найден');
        }

        if ((int)($deposit['UF_USER_ID'] ?? 0) !== $userId) {
            throw new \RuntimeException('Нет доступа к этому вкладу');
        }

        if (!$this->isGovSupportDeposit($deposit)) {
            throw new \RuntimeException('Это не гос. вклад поддержки');
        }

        $status = (string)($deposit['UF_STATUS'] ?? '');
        if ($status === GameEconomyConfig::CONTRACT_STATUS_CLOSED) {
            throw new \RuntimeException('Вклад уже закрыт');
        }

        if ($status !== GameEconomyConfig::CONTRACT_STATUS_INTEREST_PAID) {
            throw new \RuntimeException('Сначала дождитесь выплаты процентов (5 туров)');
        }

        $principal = round((float)($deposit['UF_PRINCIPAL'] ?? 0), 1);
        if (!$this->treasuryService->hasFunds(GameEconomyConfig::CURRENCY_PROGNOBAKS, $principal)) {
            throw new \RuntimeException('В казне недостаточно средств для возврата вклада');
        }

        $now = new DateTime();
        $this->treasuryService->debit(
            GameEconomyConfig::CURRENCY_PROGNOBAKS,
            $principal,
            'gov_support_return',
            $depositId
        );

        $this->walletService->credit(
            $userId,
            GameEconomyConfig::CURRENCY_PROGNOBAKS,
            $principal,
            'gov_support_return',
            'deposit',
            $depositId
        );

        $this->repository->updateBankDeposit($depositId, [
            'UF_STATUS' => GameEconomyConfig::CONTRACT_STATUS_CLOSED,
            'UF_UPDATED_AT' => $now,
            'UF_CLOSED_AT' => $now,
        ]);

        return self::formatContract($this->repository->getBankDepositById($depositId));
    }

    public function getMyContracts(int $userId): array
    {
        $items = [];
        foreach ($this->repository->getDepositsByUserId($userId) as $row) {
            if (!$this->isGovSupportDeposit($row)) {
                continue;
            }

            if (($row['UF_STATUS'] ?? '') === GameEconomyConfig::CONTRACT_STATUS_CLOSED) {
                continue;
            }

            $items[] = self::formatContract($row);
        }

        return $items;
    }

    public function hasActiveGovDeposit(int $userId): bool
    {
        return count($this->getMyContracts($userId)) > 0;
    }

    public static function isGovSupportDeposit(array $row): bool
    {
        return (string)($row['UF_CONTRACT_TYPE'] ?? GameEconomyConfig::CONTRACT_TYPE_REGULAR)
            === GameEconomyConfig::CONTRACT_TYPE_GOV_SUPPORT;
    }

    public static function formatContract(array $row): array
    {
        $formatted = BankDepositService::formatContract($row);
        $formatted['contract_type'] = GameEconomyConfig::CONTRACT_TYPE_GOV_SUPPORT;
        $formatted['interest_amount'] = GameEconomyConfig::calculateGovSupportInterest(
            round((float)($row['UF_PRINCIPAL'] ?? 0), 1)
        );
        $formatted['can_close'] = ($row['UF_STATUS'] ?? '') === GameEconomyConfig::CONTRACT_STATUS_INTEREST_PAID;
        $formatted['label'] = 'Гос. вклад поддержки';

        return $formatted;
    }
}
