<?php

namespace Prognos9ys\Main\Controller;

use Prognos9ys\Main\Service\Auth\ImpersonationService;
use Prognos9ys\Main\Service\Auth\TokenAuthService;
use Prognos9ys\Main\Service\Game\AchievementService;
use Prognos9ys\Main\Service\Game\BankContractLifecycleService;
use Prognos9ys\Main\Service\Game\BankDepositService;
use Prognos9ys\Main\Service\Game\BankLoanService;
use Prognos9ys\Main\Service\Game\BankOperationsService;
use Prognos9ys\Main\Service\Game\ExperienceService;
use Prognos9ys\Main\Service\Game\GameBankService;
use Prognos9ys\Main\Service\Game\GameEconomyConfig;
use Prognos9ys\Main\Service\Game\GameProfileService;
use Prognos9ys\Main\Service\Game\GovSupportDepositService;
use Prognos9ys\Main\Service\Game\ModeratorBulkActionsService;
use Prognos9ys\Main\Service\Game\MacroEconomyService;
use Prognos9ys\Main\Service\Game\TreasuryService;
use Prognos9ys\Main\Service\Game\TreasuryShopService;
use Prognos9ys\Main\Service\Game\UserBankService;
use Prognos9ys\Main\Service\Game\WalletService;
use Prognos9ys\Main\Service\Game\WealthRatingService;

class GameController extends BaseController
{
    public function configureActions(): array
    {
        return [
            'getState' => $this->getDefaultConfigureForPostToken(),
            'claimXp' => $this->getDefaultConfigureForPostToken(),
            'claimAllXp' => $this->getDefaultConfigureForPostToken(),
            'getLevelTiers' => $this->getDefaultConfigureForPostPublic(),
            'getWealthRating' => $this->getDefaultConfigureForPostPublic(),
            'getGameBank' => $this->getDefaultConfigureForPostToken(),
            'getTreasury' => $this->getDefaultConfigureForPostToken(),
            'getTreasuryShop' => $this->getDefaultConfigureForPostToken(),
            'buyTreasuryChest' => $this->getDefaultConfigureForPostToken(),
            'buyTreasuryPremium' => $this->getDefaultConfigureForPostToken(),
            'createGovSupportDeposit' => $this->getDefaultConfigureForPostToken(),
            'closeGovSupportDeposit' => $this->getDefaultConfigureForPostToken(),
            'getGovSupportDeposits' => $this->getDefaultConfigureForPostToken(),
            'listBanks' => $this->getDefaultConfigureForPostToken(),
            'getMyBank' => $this->getDefaultConfigureForPostToken(),
            'getMyContracts' => $this->getDefaultConfigureForPostToken(),
            'getBankOperations' => $this->getDefaultConfigureForPostToken(),
            'openBank' => $this->getDefaultConfigureForPostToken(),
            'createDeposit' => $this->getDefaultConfigureForPostToken(),
            'takeLoan' => $this->getDefaultConfigureForPostToken(),
            'cancelLoan' => $this->getDefaultConfigureForPostToken(),
            'cancelDeposit' => $this->getDefaultConfigureForPostToken(),
            'forceCloseDeposit' => $this->getDefaultConfigureForPostToken(),
            'closeBank' => $this->getDefaultConfigureForPostToken(),
            'getAchievements' => $this->getDefaultConfigureForPostToken(),
            'claimAchievement' => $this->getDefaultConfigureForPostToken(),
            'moderatorBulkAction' => $this->getDefaultConfigureForPostToken(),
            'moderatorBulkCandidates' => $this->getDefaultConfigureForPostToken(),
            'moderatorBulkRunOne' => $this->getDefaultConfigureForPostToken(),
        ];
    }

    public function getStateAction(): array
    {
        $userId = TokenAuthService::getCurrentUserId();

        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        return [
            'status' => 'ok',
            'game' => (new GameProfileService())->getSummary($userId),
        ];
    }

    public function claimXpAction(int $matchId): array
    {
        $userId = TokenAuthService::getCurrentUserId();

        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        $result = (new ExperienceService())->claim($userId, $matchId);

        return array_merge(['status' => 'ok'], $result);
    }

    public function claimAllXpAction(int $targetUserId = 0): array
    {
        $actorId = TokenAuthService::getCurrentUserId();

        if (!$actorId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        $userId = $this->resolveTargetUserId($actorId, $targetUserId);
        $result = (new ExperienceService())->claimAll($userId);

        $response = array_merge(['status' => 'ok', 'target_user_id' => $userId], $result);

        if ($userId === $actorId) {
            $response['game'] = (new GameProfileService())->getSummary($userId);
        }

        return $response;
    }

    public function getLevelTiersAction(): array
    {
        return [
            'status' => 'ok',
            'tiers' => array_values((new LevelService())->getTiers()),
        ];
    }

    public function getWealthRatingAction(int $limit = 30, string $wealthSort = 'rich', int $offset = 0): array
    {
        return (new WealthRatingService())->getRating($limit, $wealthSort, $offset);
    }

    public function getGameBankAction(): array
    {
        $userId = TokenAuthService::getCurrentUserId();

        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        if (!(new ImpersonationService())->canImpersonate($userId)) {
            throw new ApiException('Нет доступа', 403);
        }

        return [
            'status' => 'ok',
            'bank' => (new GameBankService())->getSummary(),
            'treasury' => (new TreasuryService())->getSummary(),
        ];
    }

    public function getTreasuryAction(): array
    {
        $userId = TokenAuthService::getCurrentUserId();

        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        return [
            'status' => 'ok',
            'treasury' => (new TreasuryService())->getSummary(),
            'macro' => (new MacroEconomyService())->getSummary(),
        ];
    }

    public function getTreasuryShopAction(): array
    {
        $userId = TokenAuthService::getCurrentUserId();

        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        return [
            'status' => 'ok',
            'shop' => (new TreasuryShopService())->getShopState($userId),
        ];
    }

    public function buyTreasuryChestAction(string $currency, int $targetUserId = 0): array
    {
        $actorId = TokenAuthService::getCurrentUserId();

        if (!$actorId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        $userId = $this->resolveTargetUserId($actorId, $targetUserId);
        $result = (new TreasuryShopService())->buyChest($userId, $currency);

        $response = array_merge(['status' => 'ok', 'target_user_id' => $userId], $result);
        $response['wallet'] = (new WalletService())->getWalletSummary($userId);

        if ($userId === $actorId) {
            $response['game'] = (new GameProfileService())->getSummary($userId);
        }

        return $response;
    }

    public function buyTreasuryPremiumAction(string $offerKey = 'premium_1d', int $targetUserId = 0): array
    {
        $actorId = TokenAuthService::getCurrentUserId();

        if (!$actorId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        $userId = $this->resolveTargetUserId($actorId, $targetUserId);
        $result = (new TreasuryShopService())->buyPremium($userId, $offerKey);

        $response = array_merge(['status' => 'ok', 'target_user_id' => $userId], $result);
        $response['wallet'] = (new WalletService())->getWalletSummary($userId);

        if ($userId === $actorId) {
            $response['game'] = (new GameProfileService())->getSummary($userId);
        }

        return $response;
    }

    public function createGovSupportDepositAction(int $bankId, int $eventId = 0): array
    {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        return [
            'status' => 'ok',
            'deposit' => (new GovSupportDepositService())->createDeposit(
                $userId,
                $bankId,
                $eventId > 0 ? $eventId : null
            ),
            'game' => (new GameProfileService())->getSummary($userId),
        ];
    }

    public function closeGovSupportDepositAction(int $depositId): array
    {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        return [
            'status' => 'ok',
            'deposit' => (new GovSupportDepositService())->closeDeposit($userId, $depositId),
            'game' => (new GameProfileService())->getSummary($userId),
        ];
    }

    public function getGovSupportDepositsAction(): array
    {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        return [
            'status' => 'ok',
            'deposits' => (new GovSupportDepositService())->getMyContracts($userId),
        ];
    }

    public function listBanksAction(int $limit = 30): array
    {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        return [
            'status' => 'ok',
            'banks' => (new UserBankService())->listBanks($limit),
        ];
    }

    public function getMyBankAction(): array
    {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        return [
            'status' => 'ok',
            'bank' => (new UserBankService())->getMyBank($userId),
        ];
    }

    public function getMyContractsAction(): array
    {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        return [
            'status' => 'ok',
            'deposits' => (new BankDepositService())->getMyContracts($userId),
            'loans' => (new BankLoanService())->getMyContracts($userId),
        ];
    }

    public function getBankOperationsAction(int $limit = 100): array
    {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        return [
            'status' => 'ok',
            'operations' => (new BankOperationsService())->getForUser($userId, $limit),
        ];
    }

    public function openBankAction(): array
    {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        return [
            'status' => 'ok',
            'bank' => (new UserBankService())->openBank($userId),
            'game' => (new GameProfileService())->getSummary($userId),
        ];
    }

    public function createDepositAction(int $bankId, float $amount = 0, int $eventId = 0): array
    {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        if ($amount <= 0) {
            $amount = GameEconomyConfig::DEPOSIT_MIN_AMOUNT_PROGNOBAKS;
        }

        return [
            'status' => 'ok',
            'deposit' => (new BankDepositService())->createDeposit(
                $userId,
                $bankId,
                $amount,
                $eventId > 0 ? $eventId : null
            ),
            'game' => (new GameProfileService())->getSummary($userId),
        ];
    }

    public function takeLoanAction(int $bankId, float $amount = 0, int $eventId = 0): array
    {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        if ($amount <= 0) {
            $amount = GameEconomyConfig::LOAN_MIN_AMOUNT_PROGNOBAKS;
        }

        return [
            'status' => 'ok',
            'loan' => (new BankLoanService())->takeLoan(
                $userId,
                $bankId,
                $amount,
                $eventId > 0 ? $eventId : null
            ),
            'game' => (new GameProfileService())->getSummary($userId),
        ];
    }

    public function cancelLoanAction(int $loanId): array
    {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        return [
            'status' => 'ok',
            'loan' => (new BankContractLifecycleService())->cancelLoan($userId, $loanId),
            'game' => (new GameProfileService())->getSummary($userId),
        ];
    }

    public function cancelDepositAction(int $depositId): array
    {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        return [
            'status' => 'ok',
            'deposit' => (new BankContractLifecycleService())->cancelDeposit($userId, $depositId),
            'game' => (new GameProfileService())->getSummary($userId),
        ];
    }

    public function forceCloseDepositAction(int $depositId): array
    {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        return [
            'status' => 'ok',
            'deposit' => (new BankContractLifecycleService())->forceCloseDeposit($userId, $depositId),
            'game' => (new GameProfileService())->getSummary($userId),
        ];
    }

    public function closeBankAction(): array
    {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        $result = (new UserBankService())->closeBank($userId);

        return array_merge(['status' => 'ok'], $result, [
            'game' => (new GameProfileService())->getSummary($userId),
        ]);
    }

    public function moderatorBulkActionAction(string $bulkAction): array
    {
        $actorId = TokenAuthService::getCurrentUserId();

        if (!$actorId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        if (!(new ImpersonationService())->canImpersonate($actorId)) {
            throw new ApiException('Нет доступа', 403);
        }

        try {
            $result = (new ModeratorBulkActionsService())->run($bulkAction);
        } catch (\InvalidArgumentException $e) {
            throw new ApiException($e->getMessage(), 400);
        }

        return array_merge(['status' => 'ok'], $result);
    }

    public function moderatorBulkCandidatesAction(string $bulkAction): array
    {
        $actorId = TokenAuthService::getCurrentUserId();

        if (!$actorId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        if (!(new ImpersonationService())->canImpersonate($actorId)) {
            throw new ApiException('Нет доступа', 403);
        }

        try {
            $result = (new ModeratorBulkActionsService())->getCandidates($bulkAction);
        } catch (\InvalidArgumentException $e) {
            throw new ApiException($e->getMessage(), 400);
        }

        return array_merge(['status' => 'ok'], $result);
    }

    public function moderatorBulkRunOneAction(string $bulkAction, int $targetUserId): array
    {
        $actorId = TokenAuthService::getCurrentUserId();

        if (!$actorId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        if (!(new ImpersonationService())->canImpersonate($actorId)) {
            throw new ApiException('Нет доступа', 403);
        }

        if ($targetUserId <= 0) {
            throw new ApiException('Некорректный пользователь', 400);
        }

        try {
            $result = (new ModeratorBulkActionsService())->runOne($bulkAction, $targetUserId);
        } catch (\InvalidArgumentException $e) {
            throw new ApiException($e->getMessage(), 400);
        }

        return array_merge(['status' => 'ok'], $result);
    }

    public function getAchievementsAction(): array
    {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        return array_merge(['status' => 'ok'], (new AchievementService())->getForUser($userId));
    }

    public function claimAchievementAction(string $code): array
    {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        $service = new AchievementService();
        $claimed = $service->claimNext($userId, $code);

        return [
            'status' => 'ok',
            'claimed' => $claimed,
            'achievements' => $service->getForUser($userId),
            'game' => (new GameProfileService())->getSummary($userId),
        ];
    }

    private function resolveTargetUserId(int $actorId, int $targetUserId): int
    {
        if ($targetUserId <= 0 || $targetUserId === $actorId) {
            return $actorId;
        }

        if (!(new ImpersonationService())->canImpersonate($actorId)) {
            throw new ApiException('Нет доступа', 403);
        }

        return $targetUserId;
    }
}
