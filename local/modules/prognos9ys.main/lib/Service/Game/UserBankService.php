<?php

namespace Prognos9ys\Main\Service\Game;

use Bitrix\Main\Type\DateTime;
use Bitrix\Main\UserTable;
use Prognos9ys\Main\Model\Repository\GameEconomyRepository;

class UserBankService
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

    public function listBanks(int $limit = 30): array
    {
        $rows = $this->repository->getActiveUserBanks($limit);
        $bankIds = array_map(static function (array $row): int {
            return (int)($row['ID'] ?? 0);
        }, $rows);
        $exposureMap = (new BankConsignmentService($this->repository))->getExchangeLiquidExposureMap($bankIds);

        $banks = [];
        foreach ($rows as $row) {
            $bankId = (int)($row['ID'] ?? 0);
            $banks[] = $this->formatBankPublic($row, $exposureMap[$bankId] ?? 0.0);
        }

        return $banks;
    }

    public function getMyBank(int $userId): ?array
    {
        $row = $this->repository->getUserBankByOwnerId($userId);
        if (!$row) {
            return null;
        }

        $bankId = (int)$row['ID'];
        $exposureMap = (new BankConsignmentService($this->repository))->getExchangeLiquidExposureMap([$bankId]);

        $deposits = [];
        foreach ($this->repository->getActiveDepositsByBankId($bankId) as $deposit) {
            if (GovSupportDepositService::isGovSupportDeposit($deposit)) {
                $deposits[] = $this->enrichGovSupportContract(
                    GovSupportDepositService::formatContract($deposit)
                );
                continue;
            }

            $deposits[] = $this->enrichContractWithClient(BankDepositService::formatContract($deposit));
        }

        $loans = [];
        foreach ($this->repository->getActiveLoansByBankId($bankId) as $loan) {
            $loans[] = $this->enrichContractWithClient(BankLoanService::formatContract($loan));
        }

        return array_merge($this->formatBankPublic($row, $exposureMap[$bankId] ?? 0.0), [
            'deposits' => $deposits,
            'loans' => $loans,
            'active_contracts' => count($deposits) + count($loans),
            'lifetime' => (new BankOperationsService($this->repository))->getLifetimeTotalsForBank($bankId),
            'consignment' => (new BankConsignmentService($this->repository))->getConsignmentSettingsForBank($row),
            'branches' => (new BankBranchService($this->repository))->formatOpenedBranches($row),
            'branch_opportunities' => (new BankBranchService($this->repository))->getOpenOpportunities($row),
        ]);
    }

    public function openBank(int $userId): array
    {
        if ($userId <= 0) {
            throw new \InvalidArgumentException('Некорректный пользователь');
        }

        if ($this->repository->getUserBankByOwnerId($userId)) {
            throw new \RuntimeException('У вас уже есть активный банк');
        }

        $wallet = $this->walletService->getWalletSummary($userId);
        if ($wallet['prognobaks'] < GameEconomyConfig::BANK_OPEN_MIN_WALLET_PROGNOBAKS) {
            throw new \RuntimeException(
                'Для открытия банка нужно не менее '
                . GameEconomyConfig::BANK_OPEN_MIN_WALLET_PROGNOBAKS
                . ' прогнобаксов на кошельке'
            );
        }

        $reserve = GameEconomyConfig::BANK_RESERVED_CAPITAL_PROGNOBAKS;

        $bankId = $this->repository->addUserBank([
            'UF_OWNER_ID' => $userId,
            'UF_RESERVED' => $reserve,
            'UF_LIQUID' => 0,
            'UF_BRANCH_CITIES' => '',
            'UF_ACTIVE' => GameEconomyConfig::USER_BANK_STATUS_ACTIVE,
            'UF_CONSIGNMENT_ENABLED' => 'Y',
            'UF_CONSIGNMENT_CATEGORIES' => BankConsignmentConfig::encodeCategoryFlags(
                BankConsignmentConfig::defaultCategoryFlags()
            ),
            'UF_CREATED_AT' => new DateTime(),
        ]);

        $this->walletService->debit(
            $userId,
            GameEconomyConfig::CURRENCY_PROGNOBAKS,
            $reserve,
            'bank_reserve_lock',
            'bank',
            $bankId
        );

        return $this->formatBankPublic($this->repository->getUserBankById($bankId));
    }

    public function hasActiveBank(int $userId): bool
    {
        return $this->repository->getUserBankByOwnerId($userId) !== null;
    }

    public function closeBank(int $userId): array
    {
        $bank = $this->repository->getUserBankByOwnerId($userId);
        if (!$bank) {
            throw new \RuntimeException('Активный банк не найден');
        }

        $bankId = (int)$bank['ID'];
        if ($this->repository->countActiveContractsByBankId($bankId) > 0) {
            throw new \RuntimeException('Нельзя закрыть банк с активными вкладами или займами');
        }

        $reserved = round((float)($bank['UF_RESERVED'] ?? 0), 1);
        $liquid = round((float)($bank['UF_LIQUID'] ?? 0), 1);
        $payout = round($reserved + $liquid, 1);

        $this->repository->updateUserBank($bankId, [
            'UF_ACTIVE' => GameEconomyConfig::USER_BANK_STATUS_CLOSED,
            'UF_RESERVED' => 0,
            'UF_LIQUID' => 0,
        ]);

        if ($payout > 0) {
            $this->walletService->credit(
                $userId,
                GameEconomyConfig::CURRENCY_PROGNOBAKS,
                $payout,
                'bank_reserve_unlock',
                'bank',
                $bankId
            );
        }

        return ['closed' => true, 'bank_id' => $bankId, 'returned' => $payout];
    }

    public function formatBankPublic(array $row, ?float $consignmentLiquid = null): array
    {
        $ownerId = (int)($row['UF_OWNER_ID'] ?? 0);
        $bankId = (int)($row['ID'] ?? 0);

        if ($consignmentLiquid === null) {
            $consignmentLiquid = (new BankConsignmentService($this->repository))->getExchangeLiquidExposure($bankId);
        }

        return [
            'id' => $bankId,
            'owner_id' => $ownerId,
            'owner_name' => $this->resolveUserName($ownerId),
            'reserved' => round((float)($row['UF_RESERVED'] ?? 0), 1),
            'liquid' => round((float)($row['UF_LIQUID'] ?? 0), 1),
            'consignment_liquid' => round($consignmentLiquid, 1),
            'loanable' => $this->repository->getUserBankLoanableAmount($row),
            'branches' => (new BankBranchService($this->repository))->formatOpenedBranches($row),
            'active' => ($row['UF_ACTIVE'] ?? '') === GameEconomyConfig::USER_BANK_STATUS_ACTIVE,
            'deposit_rate_percent' => GameEconomyConfig::DEPOSIT_INTEREST_PERCENT,
            'loan_rate_percent' => GameEconomyConfig::LOAN_INTEREST_PERCENT,
            'deposit_amount' => GameEconomyConfig::DEPOSIT_MIN_AMOUNT_PROGNOBAKS,
            'loan_amount' => GameEconomyConfig::LOAN_MIN_AMOUNT_PROGNOBAKS,
            'term_matches' => GameEconomyConfig::BANK_TERM_MATCHES,
        ];
    }

    private function enrichContractWithClient(array $contract): array
    {
        $clientId = (int)($contract['user_id'] ?? 0);
        $contract['client'] = $this->resolveClientBrief($clientId);

        return $contract;
    }

    private function enrichGovSupportContract(array $contract): array
    {
        $openedById = (int)($contract['user_id'] ?? 0);
        $contract['opened_by'] = $this->resolveClientBrief($openedById);
        $contract['client'] = null;

        $depositId = (int)($contract['id'] ?? 0);
        $deposit = $depositId > 0 ? $this->repository->getBankDepositById($depositId) : null;
        if ($deposit) {
            $returnCheck = GovSupportDepositService::evaluateOwnerReturnEligibility($deposit, $this->repository);
            $contract['can_close'] = !empty($returnCheck['can_return']) && empty($returnCheck['early']);
            $contract['can_force_close'] = !empty($returnCheck['can_return']) && !empty($returnCheck['early']);
            if (!empty($returnCheck['can_return'])) {
                $contract['owner_return_hint'] = !empty($returnCheck['early']) ? 'досрочно, без процентов' : '';
            } else {
                $contract['owner_return_hint'] = GovSupportDepositService::getOwnerReturnBlockMessage(
                    $returnCheck['reason'] ?? ''
                );
            }
        }

        return $contract;
    }

    private function resolveClientBrief(int $userId): array
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

    private function resolveUserName(int $userId): string
    {
        if ($userId <= 0) {
            return '';
        }

        $row = UserTable::getList([
            'filter' => ['=ID' => $userId],
            'select' => ['ID', 'LOGIN', 'NAME', 'LAST_NAME'],
            'limit' => 1,
        ])->fetch();

        if (!$row) {
            return 'user#' . $userId;
        }

        $name = trim(($row['NAME'] ?? '') . ' ' . ($row['LAST_NAME'] ?? ''));
        if ($name !== '') {
            return $name;
        }

        return (string)($row['LOGIN'] ?? ('user#' . $userId));
    }
}
