<?php

namespace Prognos9ys\Main\Service\Game;

use Bitrix\Main\Type\DateTime;
use Bitrix\Main\UserTable;
use Prognos9ys\Main\Service\Auth\ImpersonationService;
use Prognos9ys\Main\Model\Repository\GameEconomyRepository;

class GovSupportDepositService
{
    private GameEconomyRepository $repository;
    private TreasuryService $treasuryService;
    private GameEventScopeService $scopeService;

    public function __construct(
        ?GameEconomyRepository $repository = null,
        ?TreasuryService $treasuryService = null,
        ?GameEventScopeService $scopeService = null
    ) {
        $this->repository = $repository ?? new GameEconomyRepository();
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

        if (!$this->treasuryService->hasFunds(
            GameEconomyConfig::CURRENCY_PROGNOBAKS,
            $amount
        )) {
            throw new \RuntimeException('Недостаточно средств в казне');
        }

        $this->treasuryService->debit(
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

        $this->repository->adjustUserBankLiquid($bankId, $amount);

        return self::formatContract($this->repository->getBankDepositById($depositId));
    }

    public function processMaturity(array $deposit): void
    {
        if (!$this->isGovSupportDeposit($deposit)) {
            return;
        }

        $depositId = (int)$deposit['ID'];
        $bankId = (int)($deposit['UF_BANK_ID'] ?? 0);
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
            $bank = $this->repository->getUserBankById($bankId);
            if (!$bank) {
                return;
            }

            $liquid = round((float)($bank['UF_LIQUID'] ?? 0), 1);
            if ($liquid < $interest) {
                return;
            }

            $this->repository->adjustUserBankLiquid($bankId, -$interest);
            $this->treasuryService->credit(
                GameEconomyConfig::CURRENCY_PROGNOBAKS,
                $interest,
                'gov_support_interest',
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

        if (!$this->canManageGovDeposit($userId, $deposit)) {
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

        $bankId = (int)($deposit['UF_BANK_ID'] ?? 0);
        $principal = round((float)($deposit['UF_PRINCIPAL'] ?? 0), 1);
        $bank = $this->repository->getUserBankById($bankId);
        if (!$bank) {
            throw new \RuntimeException('Банк вклада не найден');
        }

        $liquid = round((float)($bank['UF_LIQUID'] ?? 0), 1);
        if ($liquid < $principal) {
            throw new \RuntimeException('В банке недостаточно ликвидности для возврата вклада');
        }

        $now = new DateTime();
        $this->repository->adjustUserBankLiquid($bankId, -$principal);
        $this->treasuryService->credit(
            GameEconomyConfig::CURRENCY_PROGNOBAKS,
            $principal,
            'gov_support_return',
            $depositId
        );

        $this->repository->updateBankDeposit($depositId, [
            'UF_STATUS' => GameEconomyConfig::CONTRACT_STATUS_CLOSED,
            'UF_UPDATED_AT' => $now,
            'UF_CLOSED_AT' => $now,
        ]);

        return self::formatContract($this->repository->getBankDepositById($depositId));
    }

    /**
     * Досрочное изъятие гос. вклада: тело с банка, проценты в казну не идут.
     */
    public function forceCloseDeposit(int $userId, int $depositId): array
    {
        $deposit = $this->repository->getBankDepositById($depositId);
        if (!$deposit) {
            throw new \RuntimeException('Вклад не найден');
        }

        if (!$this->canManageGovDeposit($userId, $deposit)) {
            throw new \RuntimeException('Нет доступа к этому вкладу');
        }

        if (!$this->isGovSupportDeposit($deposit)) {
            throw new \RuntimeException('Это не гос. вклад поддержки');
        }

        $check = self::evaluateForceCloseEligibility($deposit, $this->repository);
        if (!($check['can_force_close'] ?? false)) {
            throw new \RuntimeException(self::forceCloseBlockMessage($check['reason'] ?? ''));
        }

        $bankId = (int)($deposit['UF_BANK_ID'] ?? 0);
        $principal = round((float)($deposit['UF_PRINCIPAL'] ?? 0), 1);
        $now = new DateTime();

        $this->repository->adjustUserBankLiquid($bankId, -$principal);
        $this->treasuryService->credit(
            GameEconomyConfig::CURRENCY_PROGNOBAKS,
            $principal,
            'gov_support_early_close',
            $depositId
        );

        $this->repository->updateBankDeposit($depositId, [
            'UF_STATUS' => GameEconomyConfig::CONTRACT_STATUS_CLOSED,
            'UF_UPDATED_AT' => $now,
            'UF_CLOSED_AT' => $now,
        ]);

        return self::formatContract($this->repository->getBankDepositById($depositId));
    }

    public function getContractsForViewer(int $userId, bool $seeAll = false): array
    {
        $rows = $seeAll
            ? $this->repository->getActiveGovSupportDeposits()
            : $this->repository->getDepositsByUserId($userId);

        $items = [];
        foreach ($rows as $row) {
            if (!$seeAll && !$this->isGovSupportDeposit($row)) {
                continue;
            }

            if ($seeAll && !$this->isGovSupportDeposit($row)) {
                continue;
            }

            if (($row['UF_STATUS'] ?? '') === GameEconomyConfig::CONTRACT_STATUS_CLOSED) {
                continue;
            }

            $item = self::formatContract($row);
            $item['opened_by'] = $this->resolveModeratorBrief((int)($row['UF_USER_ID'] ?? 0));
            $items[] = $item;
        }

        return $items;
    }

    public function getMyContracts(int $userId): array
    {
        return $this->getContractsForViewer($userId, false);
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

    /**
     * @return array{can_force_close:bool,reason?:string}
     */
    public static function evaluateForceCloseEligibility(
        array $row,
        ?GameEconomyRepository $repository = null
    ): array {
        $repository = $repository ?? new GameEconomyRepository();

        if (!self::isGovSupportDeposit($row)) {
            return ['can_force_close' => false, 'reason' => 'not_gov_support'];
        }

        $status = (string)($row['UF_STATUS'] ?? '');
        if ($status === GameEconomyConfig::CONTRACT_STATUS_CLOSED) {
            return ['can_force_close' => false, 'reason' => 'closed'];
        }

        if ($status === GameEconomyConfig::CONTRACT_STATUS_INTEREST_PAID) {
            return ['can_force_close' => false, 'reason' => 'interest_paid_use_close'];
        }

        if ($status !== GameEconomyConfig::CONTRACT_STATUS_ACTIVE
            && $status !== GameEconomyConfig::CONTRACT_STATUS_EXTENDED) {
            return ['can_force_close' => false, 'reason' => 'not_active'];
        }

        $depositId = (int)($row['ID'] ?? 0);
        $principal = round((float)($row['UF_PRINCIPAL'] ?? 0), 1);

        $bankId = (int)($row['UF_BANK_ID'] ?? 0);
        $bank = $repository->getUserBankById($bankId);
        if (!$bank) {
            return ['can_force_close' => false, 'reason' => 'bank_not_found'];
        }

        $liquid = round((float)($bank['UF_LIQUID'] ?? 0), 1);
        if ($liquid < $principal) {
            return ['can_force_close' => false, 'reason' => 'bank_liquid_moved'];
        }

        return ['can_force_close' => true];
    }

    private static function forceCloseBlockMessage(string $reason): string
    {
        return self::getForceCloseBlockMessage($reason);
    }

    public static function getForceCloseBlockMessage(string $reason): string
    {
        switch ($reason) {
            case 'bank_liquid_moved':
                return 'Досрочное изъятие недоступно: в банке нет свободной ликвидности';
            case 'interest_paid_use_close':
                return 'Проценты уже ушли в казну — заберите вклад обычной кнопкой';
            case 'already_settled':
            case 'closed':
                return 'Вклад уже закрыт';
            default:
                return 'Досрочное изъятие сейчас недоступно';
        }
    }

    public static function getOwnerReturnBlockMessage(string $reason): string
    {
        if ($reason === 'bank_liquid_moved') {
            return 'В банке нет свободной ликвидности для возврата вклада';
        }

        return self::getForceCloseBlockMessage($reason);
    }

    /**
     * @return array{can_return:bool,early:bool,reason?:string}
     */
    public static function evaluateOwnerReturnEligibility(
        array $row,
        ?GameEconomyRepository $repository = null
    ): array {
        $repository = $repository ?? new GameEconomyRepository();
        $status = (string)($row['UF_STATUS'] ?? '');

        if ($status === GameEconomyConfig::CONTRACT_STATUS_CLOSED) {
            return ['can_return' => false, 'early' => false, 'reason' => 'closed'];
        }

        $principal = round((float)($row['UF_PRINCIPAL'] ?? 0), 1);
        $bankId = (int)($row['UF_BANK_ID'] ?? 0);
        $bank = $repository->getUserBankById($bankId);
        if (!$bank) {
            return ['can_return' => false, 'early' => false, 'reason' => 'bank_not_found'];
        }

        $liquid = round((float)($bank['UF_LIQUID'] ?? 0), 1);
        if ($liquid < $principal) {
            return ['can_return' => false, 'early' => false, 'reason' => 'bank_liquid_moved'];
        }

        if ($status === GameEconomyConfig::CONTRACT_STATUS_INTEREST_PAID) {
            return ['can_return' => true, 'early' => false];
        }

        $forceClose = self::evaluateForceCloseEligibility($row, $repository);
        if (!empty($forceClose['can_force_close'])) {
            return ['can_return' => true, 'early' => true];
        }

        return [
            'can_return' => false,
            'early' => false,
            'reason' => (string)($forceClose['reason'] ?? 'not_active'),
        ];
    }

    public static function formatContract(array $row, ?GameEconomyRepository $repository = null): array
    {
        $formatted = BankDepositService::formatContract($row);
        $formatted['contract_type'] = GameEconomyConfig::CONTRACT_TYPE_GOV_SUPPORT;
        $formatted['interest_amount'] = GameEconomyConfig::calculateGovSupportInterest(
            round((float)($row['UF_PRINCIPAL'] ?? 0), 1)
        );
        $returnCheck = self::evaluateOwnerReturnEligibility($row, $repository);
        $formatted['can_close'] = !empty($returnCheck['can_return']) && empty($returnCheck['early']);
        $formatted['can_force_close'] = !empty($returnCheck['can_return']) && !empty($returnCheck['early']);
        if (!empty($returnCheck['can_return'])) {
            $formatted['owner_return_hint'] = !empty($returnCheck['early']) ? 'досрочно, без процентов' : '';
        } else {
            $formatted['owner_return_hint'] = self::getOwnerReturnBlockMessage($returnCheck['reason'] ?? '');
        }
        $formatted['label'] = 'Гос. вклад поддержки';
        $formatted['is_gov_support'] = true;
        $status = (string)($row['UF_STATUS'] ?? '');
        $interestAmount = (float)($formatted['interest_amount'] ?? 0);
        $formatted['interest_paid'] = in_array($status, [
            GameEconomyConfig::CONTRACT_STATUS_INTEREST_PAID,
            GameEconomyConfig::CONTRACT_STATUS_CLOSED,
        ], true);
        if ($formatted['interest_paid'] && $interestAmount > 0) {
            $formatted['interest_status_label'] = 'Проценты выплачены в казну: ' . $interestAmount . ' 🪙';
        } elseif ($interestAmount > 0) {
            $formatted['interest_status_label'] = 'Проценты после 5 туров: ' . $interestAmount . ' 🪙';
        } else {
            $formatted['interest_status_label'] = '';
        }

        return $formatted;
    }

    private function canManageGovDeposit(int $actorId, array $deposit): bool
    {
        if ($actorId <= 0) {
            return false;
        }

        if ((int)($deposit['UF_USER_ID'] ?? 0) === $actorId) {
            return true;
        }

        if ($this->isBankOwnerOfDeposit($actorId, $deposit)) {
            return true;
        }

        return (new ImpersonationService())->canImpersonate($actorId);
    }

    private function isBankOwnerOfDeposit(int $userId, array $deposit): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $bankId = (int)($deposit['UF_BANK_ID'] ?? 0);
        if ($bankId <= 0) {
            return false;
        }

        $bank = $this->repository->getUserBankById($bankId);

        return $bank && (int)($bank['UF_OWNER_ID'] ?? 0) === $userId;
    }

    private function resolveModeratorBrief(int $userId): array
    {
        if ($userId <= 0) {
            return ['id' => 0, 'name' => '', 'ava' => ''];
        }

        $row = UserTable::getList([
            'filter' => ['=ID' => $userId],
            'select' => ['ID', 'LOGIN', 'NAME', 'LAST_NAME', 'PERSONAL_PHOTO'],
            'limit' => 1,
        ])->fetch();

        if (!$row) {
            return ['id' => $userId, 'name' => 'user#' . $userId, 'ava' => ''];
        }

        $name = trim(($row['NAME'] ?? '') . ' ' . ($row['LAST_NAME'] ?? ''));
        if ($name === '') {
            $name = (string)($row['LOGIN'] ?? ('user#' . $userId));
        }

        $photoId = (int)($row['PERSONAL_PHOTO'] ?? 0);
        $ava = $photoId > 0 ? (string)\CFile::GetPath($photoId) : '';

        return [
            'id' => $userId,
            'name' => $name,
            'ava' => $ava,
        ];
    }
}
