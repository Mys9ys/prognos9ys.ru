<?php

namespace Prognos9ys\Main\Service\Game;

use Prognos9ys\Main\Controller\ApiException;
use Prognos9ys\Main\Model\Repository\GameEconomyRepository;

/**
 * Массовые действия модератора (лавка, XP, займы).
 */
class ModeratorBulkActionsService
{
    private GameEconomyRepository $repository;
    private TreasuryShopService $shopService;
    private ExperienceService $experienceService;
    private BankLoanService $loanService;

    public function __construct(
        ?GameEconomyRepository $repository = null,
        ?TreasuryShopService $shopService = null,
        ?ExperienceService $experienceService = null,
        ?BankLoanService $loanService = null
    ) {
        $this->repository = $repository ?? new GameEconomyRepository();
        $this->shopService = $shopService ?? new TreasuryShopService($this->repository);
        $this->experienceService = $experienceService ?? new ExperienceService($this->repository);
        $this->loanService = $loanService ?? new BankLoanService($this->repository);
    }

    public function run(string $action): array
    {
        switch ($action) {
            case 'prognobaks_chests':
                return $this->bulkBuyPrognobaksChests();
            case 'claim_xp':
                return $this->bulkClaimXp();
            case 'rublius_chests':
                return $this->bulkBuyRubliusChests();
            case 'premium_1d':
                return $this->bulkBuyPremium1d();
            case 'grant_loans':
                return $this->bulkGrantLoans();
            default:
                throw new \InvalidArgumentException('Неизвестное массовое действие');
        }
    }

    private function bulkBuyPrognobaksChests(): array
    {
        $price = GameEconomyConfig::TREASURY_SHOP_CHEST_PROGNOBAKS_PRICE;
        $result = $this->emptyResult('prognobaks_chests');

        foreach ($this->repository->getAllWallets() as $wallet) {
            $userId = (int)($wallet['user_id'] ?? 0);
            if ($userId <= 0) {
                continue;
            }

            $offers = $this->shopService->getCompactRowOffers($userId);
            if (!($offers['prognobaks_available'] ?? false)) {
                $result['skipped']++;
                continue;
            }

            if (round((float)($wallet['prognobaks'] ?? 0), 1) < $price) {
                $result['skipped']++;
                continue;
            }

            try {
                $this->shopService->buyChest($userId, GameEconomyConfig::CURRENCY_PROGNOBAKS);
                $result['success']++;
            } catch (\Throwable $e) {
                $result['failed']++;
                $this->pushError($result, $userId, $e->getMessage());
            }
        }

        return $result;
    }

    private function bulkBuyRubliusChests(): array
    {
        $price = GameEconomyConfig::TREASURY_SHOP_CHEST_RUBLIUS_PRICE;
        $result = $this->emptyResult('rublius_chests');

        foreach ($this->repository->getAllWallets() as $wallet) {
            $userId = (int)($wallet['user_id'] ?? 0);
            if ($userId <= 0) {
                continue;
            }

            $offers = $this->shopService->getCompactRowOffers($userId);
            if (!($offers['rublius_available'] ?? false)) {
                $result['skipped']++;
                continue;
            }

            if (round((float)($wallet['rublius'] ?? 0), 1) < $price) {
                $result['skipped']++;
                continue;
            }

            try {
                $this->shopService->buyChest($userId, GameEconomyConfig::CURRENCY_RUBLIUS);
                $result['success']++;
            } catch (\Throwable $e) {
                $result['failed']++;
                $this->pushError($result, $userId, $e->getMessage());
            }
        }

        return $result;
    }

    private function bulkBuyPremium1d(): array
    {
        $price = GameEconomyConfig::TREASURY_SHOP_PREMIUM_1D_RUBLIUS_PRICE;
        $result = $this->emptyResult('premium_1d');

        foreach ($this->repository->getAllWallets() as $wallet) {
            $userId = (int)($wallet['user_id'] ?? 0);
            if ($userId <= 0) {
                continue;
            }

            $offers = $this->shopService->getCompactRowOffers($userId);
            if (!($offers['premium_available'] ?? false)) {
                $result['skipped']++;
                continue;
            }

            if (round((float)($wallet['rublius'] ?? 0), 1) < $price) {
                $result['skipped']++;
                continue;
            }

            try {
                $this->shopService->buyPremium($userId, 'premium_1d');
                $result['success']++;
            } catch (\Throwable $e) {
                $result['failed']++;
                $this->pushError($result, $userId, $e->getMessage());
            }
        }

        return $result;
    }

    private function bulkClaimXp(): array
    {
        $result = $this->emptyResult('claim_xp');
        $userIds = array_unique(array_map(
            static fn(array $w): int => (int)($w['user_id'] ?? 0),
            $this->repository->getAllWallets()
        ));

        foreach ($userIds as $userId) {
            if ($userId <= 0) {
                continue;
            }

            try {
                $claim = $this->experienceService->claimAll($userId);
                if ((int)($claim['claimed_count'] ?? 0) > 0) {
                    $result['success']++;
                } else {
                    $result['skipped']++;
                }
            } catch (ApiException $e) {
                if ((int)$e->getCode() === 404) {
                    $result['skipped']++;
                    continue;
                }
                $result['failed']++;
                $this->pushError($result, $userId, $e->getMessage());
            } catch (\Throwable $e) {
                $result['failed']++;
                $this->pushError($result, $userId, $e->getMessage());
            }
        }

        return $result;
    }

    private function bulkGrantLoans(): array
    {
        $amount = GameEconomyConfig::LOAN_MIN_AMOUNT_PROGNOBAKS;
        $result = $this->emptyResult('grant_loans');

        foreach ($this->repository->getAllWallets() as $wallet) {
            $userId = (int)($wallet['user_id'] ?? 0);
            if ($userId <= 0) {
                continue;
            }

            if (round((float)($wallet['prognobaks'] ?? 0), 1) >= $amount) {
                $result['skipped']++;
                continue;
            }

            if ($this->hasActiveLoan($userId)) {
                $result['skipped']++;
                continue;
            }

            $bank = $this->findBestBankForLoan($userId, $amount);
            if (!$bank) {
                $result['skipped']++;
                continue;
            }

            try {
                $this->loanService->takeLoan($userId, (int)$bank['ID'], $amount);
                $result['success']++;
            } catch (\Throwable $e) {
                $result['failed']++;
                $this->pushError($result, $userId, $e->getMessage());
            }
        }

        return $result;
    }

    private function findBestBankForLoan(int $borrowerId, float $minAmount): ?array
    {
        $best = null;
        $bestLoanable = 0.0;

        foreach ($this->repository->getActiveUserBanks(500) as $bank) {
            if ((int)($bank['UF_OWNER_ID'] ?? 0) === $borrowerId) {
                continue;
            }

            $loanable = $this->repository->getUserBankLoanableAmount($bank);
            if ($loanable >= $minAmount && $loanable > $bestLoanable) {
                $best = $bank;
                $bestLoanable = $loanable;
            }
        }

        return $best;
    }

    private function hasActiveLoan(int $userId): bool
    {
        foreach ($this->repository->getLoansByUserId($userId) as $loan) {
            $status = (string)($loan['UF_STATUS'] ?? '');
            if ($status !== GameEconomyConfig::CONTRACT_STATUS_CLOSED) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{action:string,success:int,skipped:int,failed:int,errors:array<int,array{user_id:int,message:string}>}
     */
    private function emptyResult(string $action): array
    {
        return [
            'action' => $action,
            'success' => 0,
            'skipped' => 0,
            'failed' => 0,
            'errors' => [],
        ];
    }

    /**
     * @param array{errors:array} $result
     */
    private function pushError(array &$result, int $userId, string $message): void
    {
        if (count($result['errors']) >= 20) {
            return;
        }

        $result['errors'][] = [
            'user_id' => $userId,
            'message' => $message,
        ];
    }
}
