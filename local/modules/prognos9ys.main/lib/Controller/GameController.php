<?php

namespace Prognos9ys\Main\Controller;

use Prognos9ys\Main\Service\Auth\ImpersonationService;
use Prognos9ys\Main\Service\Auth\TokenAuthService;
use Prognos9ys\Main\Service\Game\AchievementService;
use Prognos9ys\Main\Service\Game\AlbumConfig;
use Prognos9ys\Main\Service\Game\AlbumCollectionBuyService;
use Prognos9ys\Main\Service\Game\AlbumCraftService;
use Prognos9ys\Main\Service\Game\AlbumRecipeService;
use Prognos9ys\Main\Service\Game\AlbumService;
use Prognos9ys\Main\Service\Game\BankConsignmentService;
use Prognos9ys\Main\Service\Game\BankContractLifecycleService;
use Prognos9ys\Main\Service\Game\BankDepositService;
use Prognos9ys\Main\Service\Game\BankLoanService;
use Prognos9ys\Main\Service\Game\BankOperationsService;
use Prognos9ys\Main\Service\Game\ChestOpenLogService;
use Prognos9ys\Main\Service\Game\ChestOpenService;
use Prognos9ys\Main\Service\Game\ExperienceService;
use Prognos9ys\Main\Service\Game\ExchangeService;
use Prognos9ys\Main\Service\Game\GameBankService;
use Prognos9ys\Main\Service\Game\GameEconomyConfig;
use Prognos9ys\Main\Service\Game\GameProfileService;
use Prognos9ys\Main\Service\Game\GovSupportDepositService;
use Prognos9ys\Main\Service\Game\GovWarehouseService;
use Prognos9ys\Main\Service\Game\ModeratorBulkActionsService;
use Prognos9ys\Main\Service\Game\LaborExchangeConfig;
use Prognos9ys\Main\Service\Game\LaborExchangeService;
use Prognos9ys\Main\Service\Game\MacroEconomyService;
use Prognos9ys\Main\Service\Game\ProfessionCraftService;
use Prognos9ys\Main\Service\Game\ProfessionFarmService;
use Prognos9ys\Main\Service\Game\PackOpenService;
use Prognos9ys\Main\Service\Game\PremiumFarmMacroPlannerService;
use Prognos9ys\Main\Service\Game\PremiumService;
use Prognos9ys\Main\Service\Game\PremiumWorkQueueService;
use Prognos9ys\Main\Service\Game\ProfessionCertificateService;
use Prognos9ys\Main\Service\Game\StarterLoanService;
use Prognos9ys\Main\Service\Game\TreasuryService;
use Prognos9ys\Main\Service\Game\EstateMapService;
use Prognos9ys\Main\Service\Game\TreasuryCityService;
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
            'listTreasuryGovMaterial' => $this->getDefaultConfigureForPostToken(),
            'cancelTreasuryGovListing' => $this->getDefaultConfigureForPostToken(),
            'getTreasuryCities' => $this->getDefaultConfigureForPostToken(),
            'getEstateMapState' => $this->getDefaultConfigureForPostToken(),
            'getEstateCityMap' => $this->getDefaultConfigureForPostToken(),
            'startTreasuryCity' => $this->getDefaultConfigureForPostToken(),
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
            'takeStarterLoan' => $this->getDefaultConfigureForPostToken(),
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
            'equipCaftan' => $this->getDefaultConfigureForPostToken(),
            'unequipCaftan' => $this->getDefaultConfigureForPostToken(),
            'activatePremiumScroll' => $this->getDefaultConfigureForPostToken(),
            'learnAlbumRecipe' => $this->getDefaultConfigureForPostToken(),
            'craftProfessionRecipe' => $this->getDefaultConfigureForPostToken(),
            'copyProfessionRecipe' => $this->getDefaultConfigureForPostToken(),
            'openLootPacks' => $this->getDefaultConfigureForPostToken(),
            'getChestOpenLogMeta' => $this->getDefaultConfigureForPostToken(),
            'getChestOpenLogs' => $this->getDefaultConfigureForPostToken(),
            'moderatorBulkAction' => $this->getDefaultConfigureForPostToken(),
            'moderatorBulkCandidates' => $this->getDefaultConfigureForPostToken(),
            'moderatorBulkRunOne' => $this->getDefaultConfigureForPostToken(),
            'getFarmState' => $this->getDefaultConfigureForPostToken(),
            'pickFarmProfessions' => $this->getDefaultConfigureForPostToken(),
            'startFarmWork' => $this->getDefaultConfigureForPostToken(),
            'cancelFarmWork' => $this->getDefaultConfigureForPostToken(),
            'enqueuePremiumWork' => $this->getDefaultConfigureForPostToken(),
            'enqueuePremiumMacro' => $this->getDefaultConfigureForPostToken(),
            'updatePremiumWorkSellMode' => $this->getDefaultConfigureForPostToken(),
            'cancelPremiumWork' => $this->getDefaultConfigureForPostToken(),
            'getAlbumState' => $this->getDefaultConfigureForPostToken(),
            'craftAlbums' => $this->getDefaultConfigureForPostToken(),
            'activateAlbum' => $this->getDefaultConfigureForPostToken(),
            'glueAlbumItem' => $this->getDefaultConfigureForPostToken(),
            'glueAllAlbumItems' => $this->getDefaultConfigureForPostToken(),
            'buyAlbumCollectionToTier' => $this->getDefaultConfigureForPostToken(),
        ];
    }

    public function getStateAction(bool $withGrants = false, bool $refresh = false): array
    {
        $userId = TokenAuthService::getCurrentUserId();

        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        return [
            'status' => 'ok',
            'game' => (new GameProfileService())->getSummary($userId, true, $withGrants, $refresh),
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

    public function getWealthRatingAction(
        int $limit = 30,
        string $wealthSort = 'rich',
        int $offset = 0,
        int $setId = 0,
        string $userToken = ''
    ): array {
        $viewerUserId = null;
        if ($userToken !== '') {
            $viewerUserId = (new TokenAuthService())->getUserIdByToken($userToken);
        }

        return (new WealthRatingService())->getRating(
            $limit,
            $wealthSort,
            $offset,
            $setId > 0 ? $setId : null,
            $viewerUserId
        );
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

        $payload = [
            'status' => 'ok',
            'treasury' => array_merge(
                (new TreasuryService())->getSummary(),
                ['ledger' => (new TreasuryService())->getRecentLedger(40)]
            ),
            'macro' => (new MacroEconomyService())->getSummary(),
            'warehouses' => (new GovWarehouseService())->getState(),
        ];

        if ((new ImpersonationService())->canImpersonate($userId)) {
            $laborService = new LaborExchangeService();
            $payload['labor_orders'] = [
                'labor' => array_merge($laborService->getLaborMeta(), [
                    'professions' => $laborService->getPostableProfessions(),
                ]),
                'items' => $laborService->getTreasuryOrders(),
            ];
        }

        return $payload;
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

    public function listTreasuryGovMaterialAction(string $materialCode = '', int $qty = 0): array
    {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        if (!(new ImpersonationService())->canImpersonate($userId)) {
            throw new ApiException('Нет доступа', 403);
        }

        try {
            $result = (new ExchangeService())->createTreasuryGovMaterialListing($materialCode, $qty);
        } catch (\InvalidArgumentException $e) {
            throw new ApiException($e->getMessage(), 400);
        } catch (\RuntimeException $e) {
            throw new ApiException($e->getMessage(), 400);
        }

        return array_merge(['status' => 'ok'], $result, [
            'warehouses' => (new GovWarehouseService())->getState(),
        ]);
    }

    public function cancelTreasuryGovListingAction(int $listingId = 0): array
    {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        if (!(new ImpersonationService())->canImpersonate($userId)) {
            throw new ApiException('Нет доступа', 403);
        }

        try {
            $result = (new ExchangeService())->cancelTreasuryGovListing($listingId);
        } catch (\InvalidArgumentException $e) {
            throw new ApiException($e->getMessage(), 400);
        } catch (\RuntimeException $e) {
            throw new ApiException($e->getMessage(), 400);
        }

        return array_merge(['status' => 'ok'], $result, [
            'warehouses' => (new GovWarehouseService())->getState(),
        ]);
    }

    public function getTreasuryCitiesAction(): array
    {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        $service = new TreasuryCityService();
        $catalog = $service->getCatalog();

        return [
            'status' => 'ok',
            'cities' => $catalog['cities'],
            'founded_count' => $catalog['founded_count'],
            'open_count' => $catalog['open_count'],
            'can_manage' => (new ImpersonationService())->canImpersonate($userId),
        ];
    }

    public function getEstateMapStateAction(): array
    {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        return [
            'status' => 'ok',
            'map' => (new EstateMapService())->getWorldMapState($userId),
        ];
    }

    public function getEstateCityMapAction(string $citySlug): array
    {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        try {
            $city = (new EstateMapService())->getCityStreetMap($citySlug, $userId);
        } catch (\InvalidArgumentException $e) {
            throw new ApiException($e->getMessage(), 400);
        }

        return [
            'status' => 'ok',
            'city' => $city,
        ];
    }

    public function startTreasuryCityAction(string $citySlug): array
    {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        if (!(new ImpersonationService())->canImpersonate($userId)) {
            throw new ApiException('Нет доступа', 403);
        }

        try {
            $city = (new TreasuryCityService())->startFounding($citySlug, $userId);
        } catch (\InvalidArgumentException $e) {
            throw new ApiException($e->getMessage(), 400);
        } catch (\RuntimeException $e) {
            throw new ApiException($e->getMessage(), 400);
        }

        $catalog = (new TreasuryCityService())->getCatalog();

        return [
            'status' => 'ok',
            'city' => $city,
            'cities' => $catalog['cities'],
            'founded_count' => $catalog['founded_count'],
            'open_count' => $catalog['open_count'],
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

    public function createGovSupportDepositAction(
        int $bankId,
        int $eventId = 0,
        float $amount = 0,
        string $currency = ''
    ): array {
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
                $amount,
                $currency
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

    public function takeStarterLoanAction(int $eventId = 0): array
    {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        GameProfileService::invalidateSummaryCache($userId);

        return [
            'status' => 'ok',
            'loan' => (new StarterLoanService())->takeStarterLoan(
                $userId,
                $eventId > 0 ? $eventId : null
            ),
            'game' => (new GameProfileService())->getSummary($userId, true, false, true),
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

    public function openWc26ChestsAction(int $openAll = 0, int $qty = 0): array
    {
        if ($qty <= 0) {
            $qty = $openAll > 0 ? 30 : 1;
        }

        return $this->openChestsAction(ChestOpenService::POOL_WC26, 0, $qty);
    }

    public function openChestsAction(string $pool, int $openAll = 0, int $qty = 0): array
    {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        if ($qty <= 0) {
            $qty = $openAll > 0 ? 30 : 1;
        }

        try {
            $service = new ChestOpenService();
            if ($pool === ChestOpenService::POOL_WC26) {
                $result = $service->openWc26Chests($userId, $qty);
            } elseif ($pool === ChestOpenService::POOL_LEVEL) {
                $result = $service->openLevelChests($userId, $qty);
            } elseif ($pool === ChestOpenService::POOL_ACHIEVEMENT) {
                $result = $service->openAchievementChests($userId, $qty);
            } elseif ($pool === ChestOpenService::POOL_PROFESSION) {
                $result = $service->openProfessionChests($userId, $qty);
            } else {
                throw new ApiException('Неизвестный пул сундуков', 400);
            }
        } catch (\InvalidArgumentException $e) {
            throw new ApiException($e->getMessage(), 400);
        } catch (\RuntimeException $e) {
            throw new ApiException($e->getMessage(), 400);
        }

        return array_merge(['status' => 'ok'], $result, [
            'game' => (new GameProfileService())->getInventoryOpenSummary($userId),
        ]);
    }

    public function openXpBanksAction(string $code, int $openAll = 0, string $professionCode = '', int $qty = 0): array
    {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        if ($qty <= 0) {
            $qty = $openAll > 0 ? 30 : 1;
        }

        try {
            $result = (new XpBankService())->open($userId, $code, $qty, $professionCode);
        } catch (\InvalidArgumentException $e) {
            throw new ApiException($e->getMessage(), 400);
        } catch (\RuntimeException $e) {
            throw new ApiException($e->getMessage(), 400);
        }

        return array_merge(['status' => 'ok'], $result, [
            'game' => (new GameProfileService())->getInventoryOpenSummary($userId, true),
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
            'game' => (new GameProfileService())->getMutationSummary($userId),
        ]);
    }

    public function equipCaftanAction(string $equipmentCode = ''): array
    {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        try {
            $result = (new EquipmentService())->equipCaftan($userId, $equipmentCode);
        } catch (\InvalidArgumentException $e) {
            throw new ApiException($e->getMessage(), 400);
        } catch (\RuntimeException $e) {
            throw new ApiException($e->getMessage(), 400);
        }

        return array_merge(['status' => 'ok'], $result, [
            'game' => (new GameProfileService())->getMutationSummary($userId),
        ]);
    }

    public function unequipCaftanAction(): array
    {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        try {
            $result = (new EquipmentService())->unequipCaftan($userId);
        } catch (\InvalidArgumentException $e) {
            throw new ApiException($e->getMessage(), 400);
        } catch (\RuntimeException $e) {
            throw new ApiException($e->getMessage(), 400);
        }

        return array_merge(['status' => 'ok'], $result, [
            'game' => (new GameProfileService())->getMutationSummary($userId),
        ]);
    }

    public function activatePremiumScrollAction(int $days = 0, int $openAll = 0, int $qty = 0): array
    {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        try {
            $activateAll = $openAll > 0 && $qty <= 0;
            $result = (new PremiumService())->activateScrolls($userId, $days, $activateAll, $qty);
        } catch (\InvalidArgumentException $e) {
            throw new ApiException($e->getMessage(), 400);
        } catch (\RuntimeException $e) {
            throw new ApiException($e->getMessage(), 400);
        }

        GameProfileService::invalidateSummaryCache($userId);

        return array_merge(['status' => 'ok'], $result, [
            'game' => (new GameProfileService())->getSummary($userId, true, false, true),
        ]);
    }

    public function learnAlbumRecipeAction(string $recipeCode = ''): array
    {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        $recipeCode = trim($recipeCode);
        if ($recipeCode === '') {
            $recipeCode = AlbumConfig::RECIPE_ITEM_CODE;
        }

        try {
            $result = (new AlbumRecipeService())->learn($userId, $recipeCode);
        } catch (\InvalidArgumentException $e) {
            throw new ApiException($e->getMessage(), 400);
        } catch (\RuntimeException $e) {
            throw new ApiException($e->getMessage(), 400);
        }

        return array_merge(['status' => 'ok'], $result, [
            'game' => (new GameProfileService())->getInventoryOpenSummary($userId),
        ]);
    }

    public function craftProfessionRecipeAction(string $recipeCode = '', string $professionCode = ''): array
    {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        try {
            $result = (new ProfessionCraftService())->craft($userId, $recipeCode, $professionCode);
        } catch (\InvalidArgumentException $e) {
            throw new ApiException($e->getMessage(), 400);
        } catch (\RuntimeException $e) {
            throw new ApiException($e->getMessage(), 400);
        }

        return array_merge(['status' => 'ok'], $result, [
            'farm' => (new ProfessionFarmService())->getState($userId),
            'game' => (new GameProfileService())->getWalletMutationSummary($userId),
        ]);
    }

    public function copyProfessionRecipeAction(string $recipeCode = '', string $professionCode = ''): array
    {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        try {
            $result = (new ProfessionCraftService())->copyRecipe($userId, $recipeCode, $professionCode);
        } catch (\InvalidArgumentException $e) {
            throw new ApiException($e->getMessage(), 400);
        } catch (\RuntimeException $e) {
            throw new ApiException($e->getMessage(), 400);
        }

        return array_merge(['status' => 'ok'], $result, [
            'farm' => (new ProfessionFarmService())->getState($userId),
            'game' => (new GameProfileService())->getWalletMutationSummary($userId),
        ]);
    }

    public function openLootPacksAction(string $code, int $openAll = 0, int $qty = 0): array
    {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        if ($qty <= 0) {
            $qty = $openAll > 0 ? 30 : 1;
        }

        try {
            $result = (new PackOpenService())->open($userId, $code, $qty);
        } catch (\InvalidArgumentException $e) {
            throw new ApiException($e->getMessage(), 400);
        } catch (\RuntimeException $e) {
            throw new ApiException($e->getMessage(), 400);
        }

        return array_merge(['status' => 'ok'], $result, [
            'game' => (new GameProfileService())->getInventoryOpenSummary($userId),
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

    public function enqueuePremiumWorkAction(string $taskType = '', string $payload = ''): array
    {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        $decoded = json_decode($payload, true);
        if (!is_array($decoded)) {
            throw new ApiException('Некорректные параметры задачи', 400);
        }

        try {
            $result = (new PremiumWorkQueueService())->enqueue($userId, $taskType, $decoded);
        } catch (\InvalidArgumentException $e) {
            throw new ApiException($e->getMessage(), 400);
        } catch (\RuntimeException $e) {
            throw new ApiException($e->getMessage(), 400);
        }

        return array_merge(['status' => 'ok'], $result, [
            'farm' => (new ProfessionFarmService())->getState($userId),
            'game' => (new GameProfileService())->getWalletMutationSummary($userId),
        ]);
    }

    public function enqueuePremiumMacroAction(string $macroType = '', string $options = ''): array
    {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        $decoded = json_decode($options, true);
        if (!is_array($decoded)) {
            throw new ApiException('Некорректные параметры макроса', 400);
        }

        try {
            $result = (new PremiumFarmMacroPlannerService())->planAndEnqueue($userId, $macroType, $decoded);
        } catch (\InvalidArgumentException $e) {
            throw new ApiException($e->getMessage(), 400);
        } catch (\RuntimeException $e) {
            throw new ApiException($e->getMessage(), 400);
        }

        return array_merge(['status' => 'ok'], $result, [
            'farm' => (new ProfessionFarmService())->getState($userId),
            'game' => (new GameProfileService())->getWalletMutationSummary($userId),
        ]);
    }

    public function updatePremiumWorkSellModeAction(int $taskId = 0, string $sellMode = 'listing'): array
    {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        try {
            $result = (new PremiumWorkQueueService())->updatePendingExchangeSellMode($userId, $taskId, $sellMode);
        } catch (\InvalidArgumentException $e) {
            throw new ApiException($e->getMessage(), 400);
        } catch (\RuntimeException $e) {
            throw new ApiException($e->getMessage(), 400);
        }

        return array_merge(['status' => 'ok'], $result, [
            'farm' => (new ProfessionFarmService())->getState($userId),
        ]);
    }

    public function cancelPremiumWorkAction(int $taskId = 0): array
    {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        try {
            $result = (new PremiumWorkQueueService())->cancel($userId, $taskId);
        } catch (\RuntimeException $e) {
            throw new ApiException($e->getMessage(), 400);
        }

        return array_merge(['status' => 'ok'], $result, [
            'farm' => (new ProfessionFarmService())->getState($userId),
        ]);
    }

    public function getAlbumStateAction(): array
    {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        return array_merge(
            ['status' => 'ok'],
            ['album' => (new AlbumService())->getState($userId)]
        );
    }

    public function craftAlbumsAction(string $professionCode = ''): array
    {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        try {
            $result = (new AlbumCraftService())->craft($userId, $professionCode);
        } catch (\InvalidArgumentException $e) {
            throw new ApiException($e->getMessage(), 400);
        } catch (\RuntimeException $e) {
            throw new ApiException($e->getMessage(), 400);
        }

        return array_merge(['status' => 'ok'], $result, [
            'album' => (new AlbumService())->getState($userId),
            'farm' => (new ProfessionFarmService())->getState($userId),
            'game' => (new GameProfileService())->getWalletMutationSummary($userId),
        ]);
    }

    public function activateAlbumAction(): array
    {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        try {
            $result = (new AlbumService())->activate($userId);
        } catch (\InvalidArgumentException $e) {
            throw new ApiException($e->getMessage(), 400);
        } catch (\RuntimeException $e) {
            throw new ApiException($e->getMessage(), 400);
        }

        return array_merge(['status' => 'ok'], $result, [
            'album' => (new AlbumService())->getState($userId),
            'game' => (new GameProfileService())->getMutationSummary($userId),
        ]);
    }

    public function glueAlbumItemAction(int $albumId = 0, string $itemCode = ''): array
    {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        try {
            $albumService = new AlbumService();
            $result = $albumService->glue($userId, $albumId, $itemCode);
        } catch (\InvalidArgumentException $e) {
            throw new ApiException($e->getMessage(), 400);
        } catch (\RuntimeException $e) {
            throw new ApiException($e->getMessage(), 400);
        }

        return array_merge(['status' => 'ok'], $result, [
            'album' => $albumService->getState($userId),
            'game' => (new GameProfileService())->getMutationSummary($userId),
        ]);
    }

    public function glueAllAlbumItemsAction(int $albumId = 0): array
    {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        try {
            $albumService = new AlbumService();
            $result = $albumService->glueAllEligible($userId, $albumId);
        } catch (\InvalidArgumentException $e) {
            throw new ApiException($e->getMessage(), 400);
        } catch (\RuntimeException $e) {
            throw new ApiException($e->getMessage(), 400);
        }

        return array_merge(['status' => 'ok'], $result, [
            'album' => $albumService->getState($userId),
            'game' => (new GameProfileService())->getMutationSummary($userId),
        ]);
    }

    public function buyAlbumCollectionToTierAction(string $collection = '', int $targetTier = 0): array
    {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        try {
            $result = (new AlbumCollectionBuyService())->buyMissingToTier($userId, $collection, $targetTier);
        } catch (\InvalidArgumentException $e) {
            throw new ApiException($e->getMessage(), 400);
        } catch (\RuntimeException $e) {
            throw new ApiException($e->getMessage(), 400);
        }

        return array_merge(['status' => 'ok'], $result, [
            'album' => (new AlbumService())->getState($userId),
            'game' => (new GameProfileService())->getMutationSummary($userId),
        ]);
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
