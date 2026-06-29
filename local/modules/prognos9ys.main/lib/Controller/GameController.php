<?php

namespace Prognos9ys\Main\Controller;

use Prognos9ys\Main\Service\Auth\ImpersonationService;
use Prognos9ys\Main\Service\Auth\TokenAuthService;
use Prognos9ys\Main\Service\Game\AchievementService;
use Prognos9ys\Main\Service\Game\BankConsignmentService;
use Prognos9ys\Main\Service\Game\BankContractLifecycleService;
use Prognos9ys\Main\Service\Game\BankDepositService;
use Prognos9ys\Main\Service\Game\BankLoanService;
use Prognos9ys\Main\Service\Game\BankOperationsService;
use Prognos9ys\Main\Service\Game\ChestOpenLogService;
use Prognos9ys\Main\Service\Game\ChestOpenService;
use Prognos9ys\Main\Service\Game\ExperienceService;
use Prognos9ys\Main\Service\Game\GameBankService;
use Prognos9ys\Main\Service\Game\GameEconomyConfig;
use Prognos9ys\Main\Service\Game\GameProfileService;
use Prognos9ys\Main\Service\Game\GovSupportDepositService;
use Prognos9ys\Main\Service\Game\GovWarehouseService;
use Prognos9ys\Main\Service\Game\ModeratorBulkActionsService;
use Prognos9ys\Main\Service\Game\LaborExchangeConfig;
use Prognos9ys\Main\Service\Game\LaborExchangeService;
use Prognos9ys\Main\Service\Game\MacroEconomyService;
use Prognos9ys\Main\Service\Game\ProfessionFarmService;
use Prognos9ys\Main\Service\Game\ProfessionCertificateService;
use Prognos9ys\Main\Service\Game\TreasuryService;
use Prognos9ys\Main\Service\Game\TreasuryShopService;
use Prognos9ys\Main\Service\Game\UserBankService;
use Prognos9ys\Main\Service\Game\WalletService;
use Prognos9ys\Main\Service\Game\WealthRatingService;
use Prognos9ys\Main\Service\Game\XpBankService;

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
            'getTreasuryLaborOrders' => $this->getDefaultConfigureForPostToken(),
            'createTreasuryLaborOrder' => $this->getDefaultConfigureForPostToken(),
            'cancelTreasuryLaborOrder' => $this->getDefaultConfigureForPostToken(),
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
            'repayLoan' => $this->getDefaultConfigureForPostToken(),
            'repayAllLoans' => $this->getDefaultConfigureForPostToken(),
            'cancelDeposit' => $this->getDefaultConfigureForPostToken(),
            'forceCloseDeposit' => $this->getDefaultConfigureForPostToken(),
            'closeBank' => $this->getDefaultConfigureForPostToken(),
            'updateBankConsignmentSettings' => $this->getDefaultConfigureForPostToken(),
            'getAchievements' => $this->getDefaultConfigureForPostToken(),
            'claimAchievement' => $this->getDefaultConfigureForPostToken(),
            'openWc26Chests' => $this->getDefaultConfigureForPostToken(),
            'openChests' => $this->getDefaultConfigureForPostToken(),
            'openXpBanks' => $this->getDefaultConfigureForPostToken(),
            'activateProfessionCertificate' => $this->getDefaultConfigureForPostToken(),
            'getChestOpenLogMeta' => $this->getDefaultConfigureForPostToken(),
            'getChestOpenLogs' => $this->getDefaultConfigureForPostToken(),
            'moderatorBulkAction' => $this->getDefaultConfigureForPostToken(),
            'moderatorBulkCandidates' => $this->getDefaultConfigureForPostToken(),
            'moderatorBulkRunOne' => $this->getDefaultConfigureForPostToken(),
            'getFarmState' => $this->getDefaultConfigureForPostToken(),
            'pickFarmProfessions' => $this->getDefaultConfigureForPostToken(),
            'startFarmWork' => $this->getDefaultConfigureForPostToken(),
            'cancelFarmWork' => $this->getDefaultConfigureForPostToken(),
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
            'treasury' => array_merge(
                (new TreasuryService())->getSummary(),
                ['ledger' => (new TreasuryService())->getRecentLedger(40)]
            ),
            'macro' => (new MacroEconomyService())->getSummary(),
            'warehouses' => (new GovWarehouseService())->getState(),
        ];
    }

    public function getTreasuryLaborOrdersAction(): array
    {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        if (!(new ImpersonationService())->canImpersonate($userId)) {
            throw new ApiException('Нет доступа', 403);
        }

        $service = new LaborExchangeService();

        return [
            'status' => 'ok',
            'labor' => array_merge($service->getLaborMeta(), [
                'professions' => $service->getPostableProfessions(),
            ]),
            'items' => $service->getTreasuryOrders(),
        ];
    }

    public function createTreasuryLaborOrderAction(
        string $professionCode,
        int $iterations,
        float $payPerCycle = 0
    ): array {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        if (!(new ImpersonationService())->canImpersonate($userId)) {
            throw new ApiException('Нет доступа', 403);
        }

        if ($payPerCycle <= 0) {
            $payPerCycle = LaborExchangeConfig::DEFAULT_PAY_PER_CYCLE;
        }

        try {
            $order = (new LaborExchangeService())->createTreasuryOrder(
                $professionCode,
                $iterations,
                $payPerCycle
            );
        } catch (\InvalidArgumentException $e) {
            throw new ApiException($e->getMessage(), 400);
        } catch (\RuntimeException $e) {
            throw new ApiException($e->getMessage(), 400);
        }

        return [
            'status' => 'ok',
            'order' => $order,
            'treasury' => (new TreasuryService())->getSummary(),
            'warehouses' => (new GovWarehouseService())->getState(),
        ];
    }

    public function cancelTreasuryLaborOrderAction(int $orderId): array
    {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        if (!(new ImpersonationService())->canImpersonate($userId)) {
            throw new ApiException('Нет доступа', 403);
        }

        try {
            $order = (new LaborExchangeService())->cancelTreasuryOrder($orderId);
        } catch (\RuntimeException $e) {
            throw new ApiException($e->getMessage(), 400);
        }

        return [
            'status' => 'ok',
            'order' => $order,
            'treasury' => (new TreasuryService())->getSummary(),
            'warehouses' => (new GovWarehouseService())->getState(),
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

    public function buyTreasuryChestAction(string $currency, int $targetUserId = 0, int $milestone = 0): array
    {
        $actorId = TokenAuthService::getCurrentUserId();

        if (!$actorId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        $userId = $this->resolveTargetUserId($actorId, $targetUserId);
        $result = (new TreasuryShopService())->buyChest($userId, $currency, $milestone);

        $response = array_merge(['status' => 'ok', 'target_user_id' => $userId], $result);
        $response['wallet'] = (new WalletService())->getWalletSummary($userId);

        if ($userId === $actorId) {
            $response['game'] = (new GameProfileService())->getSummary($userId);
        }

        return $response;
    }

    public function buyTreasuryPremiumAction(string $offerKey = 'premium_1d', int $targetUserId = 0, int $milestone = 0): array
    {
        $actorId = TokenAuthService::getCurrentUserId();

        if (!$actorId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        $userId = $this->resolveTargetUserId($actorId, $targetUserId);
        $result = (new TreasuryShopService())->buyPremium($userId, $offerKey, $milestone);

        $response = array_merge(['status' => 'ok', 'target_user_id' => $userId], $result);
        $response['wallet'] = (new WalletService())->getWalletSummary($userId);

        if ($userId === $actorId) {
            $response['game'] = (new GameProfileService())->getSummary($userId);
        }

        return $response;
    }

    public function createGovSupportDepositAction(int $bankId, int $eventId = 0, float $amount = 0): array
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
                $eventId > 0 ? $eventId : null,
                $amount
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

        $seeAll = (new ImpersonationService())->canImpersonate($userId);

        return [
            'status' => 'ok',
            'deposits' => (new GovSupportDepositService())->getContractsForViewer($userId, $seeAll),
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
            'repay_all' => (new BankLoanService())->buildRepayAllPlan($userId),
        ];
    }

    public function getBankOperationsAction(int $limit = 30): array
    {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        $limit = max(1, min(100, $limit));

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

    public function repayLoanAction(int $loanId): array
    {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        try {
            $loan = (new BankLoanService())->repayLoanEarly($userId, $loanId);
        } catch (\RuntimeException $e) {
            throw new ApiException($e->getMessage(), 400);
        }

        return [
            'status' => 'ok',
            'loan' => $loan,
            'game' => (new GameProfileService())->getSummary($userId),
        ];
    }

    public function repayAllLoansAction(): array
    {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        try {
            $result = (new BankLoanService())->repayAllLoans($userId);
        } catch (\RuntimeException $e) {
            throw new ApiException($e->getMessage(), 400);
        }

        return array_merge(['status' => 'ok'], $result, [
            'game' => (new GameProfileService())->getSummary($userId),
            'repay_all' => (new BankLoanService())->buildRepayAllPlan($userId),
        ]);
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

    public function updateBankConsignmentSettingsAction(bool $enabled = false, string $categoriesJson = ''): array
    {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        $categories = [];
        if ($categoriesJson !== '') {
            $decoded = json_decode($categoriesJson, true);
            if (is_array($decoded)) {
                $categories = $decoded;
            }
        }

        try {
            $consignment = (new BankConsignmentService())->updateConsignmentSettings(
                $userId,
                $enabled,
                $categories
            );
        } catch (\RuntimeException $e) {
            throw new ApiException($e->getMessage(), 400);
        }

        return [
            'status' => 'ok',
            'consignment' => $consignment,
            'bank' => (new UserBankService())->getMyBank($userId),
        ];
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

    public function openWc26ChestsAction(int $openAll = 0): array
    {
        return $this->openChestsAction(ChestOpenService::POOL_WC26, $openAll);
    }

    public function openChestsAction(string $pool, int $openAll = 0): array
    {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        try {
            $service = new ChestOpenService();
            if ($pool === ChestOpenService::POOL_WC26) {
                $result = $service->openWc26Chests($userId, $openAll > 0);
            } elseif ($pool === ChestOpenService::POOL_LEVEL) {
                $result = $service->openLevelChests($userId, $openAll > 0);
            } elseif ($pool === ChestOpenService::POOL_ACHIEVEMENT) {
                $result = $service->openAchievementChests($userId, $openAll > 0);
            } elseif ($pool === ChestOpenService::POOL_PROFESSION) {
                $result = $service->openProfessionChests($userId, $openAll > 0);
            } else {
                throw new ApiException('Неизвестный пул сундуков', 400);
            }
        } catch (\InvalidArgumentException $e) {
            throw new ApiException($e->getMessage(), 400);
        } catch (\RuntimeException $e) {
            throw new ApiException($e->getMessage(), 400);
        }

        return array_merge(['status' => 'ok'], $result, [
            'game' => (new GameProfileService())->getSummary($userId),
        ]);
    }

    public function openXpBanksAction(string $code, int $openAll = 0): array
    {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        try {
            $qty = $openAll > 0 ? 30 : 1;
            $result = (new XpBankService())->open($userId, $code, $qty);
        } catch (\InvalidArgumentException $e) {
            throw new ApiException($e->getMessage(), 400);
        } catch (\RuntimeException $e) {
            throw new ApiException($e->getMessage(), 400);
        }

        return array_merge(['status' => 'ok'], $result, [
            'game' => (new GameProfileService())->getSummary($userId),
        ]);
    }

    public function activateProfessionCertificateAction(): array
    {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        try {
            $result = (new ProfessionCertificateService())->activate($userId);
        } catch (\InvalidArgumentException $e) {
            throw new ApiException($e->getMessage(), 400);
        } catch (\RuntimeException $e) {
            throw new ApiException($e->getMessage(), 400);
        }

        return array_merge(['status' => 'ok'], $result, [
            'game' => (new GameProfileService())->getSummary($userId),
        ]);
    }

    public function getChestOpenLogMetaAction(): array
    {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        return array_merge(['status' => 'ok'], (new ChestOpenLogService())->getMetaForUser($userId));
    }

    public function getChestOpenLogsAction(
        int $eventId = 0,
        string $groupKey = 'all',
        int $offset = 0,
        int $limit = 25
    ): array {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        return array_merge(
            ['status' => 'ok'],
            (new ChestOpenLogService())->getEntries($userId, $eventId, $groupKey, $offset, $limit)
        );
    }

    public function getFarmStateAction(): array
    {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        return array_merge(
            ['status' => 'ok'],
            ['farm' => (new ProfessionFarmService())->getState($userId)]
        );
    }

    public function pickFarmProfessionsAction(string $professions = '', string $profession1 = '', string $profession2 = ''): array
    {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        if ($professions !== '') {
            $codes = array_values(array_filter(array_map('trim', explode(',', $professions))));
        } else {
            $codes = array_values(array_filter([$profession1, $profession2], static function ($code) {
                return trim((string)$code) !== '';
            }));
        }

        $farm = (new ProfessionFarmService())->pickProfessions($userId, $codes);

        return [
            'status' => 'ok',
            'farm' => $farm,
        ];
    }

    public function startFarmWorkAction(string $professionCode = '', string $workMode = 'treasury', int $iterations = 0): array
    {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        $farm = (new ProfessionFarmService())->startWork($userId, $professionCode, $workMode, $iterations);

        return [
            'status' => 'ok',
            'farm' => $farm,
        ];
    }

    public function cancelFarmWorkAction(): array
    {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        $farm = (new ProfessionFarmService())->cancelWork($userId);

        return [
            'status' => 'ok',
            'farm' => $farm,
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
