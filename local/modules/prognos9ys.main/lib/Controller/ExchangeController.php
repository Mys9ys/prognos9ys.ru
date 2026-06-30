<?php

namespace Prognos9ys\Main\Controller;

use Prognos9ys\Main\Model\Repository\ProfessionRepository;
use Prognos9ys\Main\Service\Auth\ImpersonationService;
use Prognos9ys\Main\Service\Auth\TokenAuthService;
use Prognos9ys\Main\Service\Game\ExchangeService;
use Prognos9ys\Main\Service\Game\GameProfileService;
use Prognos9ys\Main\Service\Game\LaborExchangeConfig;
use Prognos9ys\Main\Service\Game\LaborExchangeService;

class ExchangeController extends BaseController
{
    public function configureActions(): array
    {
        return [
            'getState' => $this->getDefaultConfigureForPostToken(),
            'getCatalog' => $this->getDefaultConfigureForPostToken(),
            'getMyListings' => $this->getDefaultConfigureForPostToken(),
            'createListing' => $this->getDefaultConfigureForPostToken(),
            'cancelListing' => $this->getDefaultConfigureForPostToken(),
            'buy' => $this->getDefaultConfigureForPostToken(),
            'getTradeHistory' => $this->getDefaultConfigureForPostToken(),
            'consignToBank' => $this->getDefaultConfigureForPostToken(),
            'getDuplicateSouvenirPlan' => $this->getDefaultConfigureForPostToken(),
            'bulkSellDuplicateSouvenirs' => $this->getDefaultConfigureForPostToken(),
            'moderatorRemoveListing' => $this->getDefaultConfigureForPostToken(),
            'getLaborState' => $this->getDefaultConfigureForPostToken(),
            'getLaborOrders' => $this->getDefaultConfigureForPostToken(),
            'getMyLaborOrders' => $this->getDefaultConfigureForPostToken(),
            'createLaborOrder' => $this->getDefaultConfigureForPostToken(),
            'cancelLaborOrder' => $this->getDefaultConfigureForPostToken(),
            'claimLaborOrder' => $this->getDefaultConfigureForPostToken(),
            'startLaborWorkshop' => $this->getDefaultConfigureForPostToken(),
        ];
    }

    public function getStateAction(): array
    {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        return array_merge(['status' => 'ok'], (new ExchangeService())->getState($userId));
    }

    public function getCatalogAction(int $offset = 0, int $limit = 25, string $catalogTab = '', string $kind = ''): array
    {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        $tab = trim($catalogTab) !== '' ? $catalogTab : $kind;

        return array_merge(
            ['status' => 'ok'],
            (new ExchangeService())->getCatalog($offset, $limit, $tab)
        );
    }

    public function getMyListingsAction(): array
    {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        return [
            'status' => 'ok',
            'items' => (new ExchangeService())->getMyListings($userId),
        ];
    }

    public function createListingAction(
        string $kind,
        string $code,
        int $qty,
        float $pricePerUnit,
        string $category = '',
        int $eventId = 0,
        string $teamCode = ''
    ): array {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        try {
            $result = (new ExchangeService())->createListing(
                $userId,
                $kind,
                $code,
                $qty,
                $pricePerUnit,
                $category,
                $eventId,
                $teamCode
            );
        } catch (\InvalidArgumentException $e) {
            throw new ApiException($e->getMessage(), 400);
        } catch (\RuntimeException $e) {
            throw new ApiException($e->getMessage(), 400);
        }

        return array_merge(['status' => 'ok'], $result, [
            'game' => (new GameProfileService())->getSummary($userId),
        ]);
    }

    public function cancelListingAction(int $listingId): array
    {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        try {
            $result = (new ExchangeService())->cancelListing($userId, $listingId);
        } catch (\RuntimeException $e) {
            throw new ApiException($e->getMessage(), 400);
        }

        return array_merge(['status' => 'ok'], $result, [
            'game' => (new GameProfileService())->getSummary($userId),
        ]);
    }

    public function buyAction(
        string $kind,
        string $code,
        int $qty,
        string $category = '',
        int $eventId = 0,
        string $teamCode = '',
        float $pricePerUnit = 0
    ): array {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        try {
            $result = (new ExchangeService())->buy(
                $userId,
                $kind,
                $code,
                $qty,
                $category,
                $eventId,
                $teamCode,
                $pricePerUnit
            );
        } catch (\InvalidArgumentException $e) {
            throw new ApiException($e->getMessage(), 400);
        } catch (\RuntimeException $e) {
            throw new ApiException($e->getMessage(), 400);
        }

        return array_merge(['status' => 'ok'], $result, [
            'game' => (new GameProfileService())->getSummary($userId),
        ]);
    }

    public function getTradeHistoryAction(int $offset = 0, int $limit = 25): array
    {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        return array_merge(
            ['status' => 'ok'],
            (new ExchangeService())->getTradeHistory($userId, $offset, $limit)
        );
    }

    public function getDuplicateSouvenirPlanAction(): array
    {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        return array_merge(
            ['status' => 'ok'],
            (new ExchangeService())->getDuplicateSouvenirSellPlan($userId)
        );
    }

    public function bulkSellDuplicateSouvenirsAction(string $sellMode = 'listing', float $pricePerUnit = 0): array
    {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        try {
            $result = (new ExchangeService())->bulkSellDuplicateSouvenirs($userId, $sellMode, $pricePerUnit);
        } catch (\InvalidArgumentException $e) {
            throw new ApiException($e->getMessage(), 400);
        } catch (\RuntimeException $e) {
            throw new ApiException($e->getMessage(), 400);
        }

        return array_merge(['status' => 'ok'], $result, [
            'game' => (new GameProfileService())->getMutationSummary($userId),
        ]);
    }

    public function consignToBankAction(
        string $kind,
        string $code,
        int $qty,
        string $category = '',
        int $eventId = 0,
        string $teamCode = ''
    ): array {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        try {
            $result = (new ExchangeService())->consignToBank(
                $userId,
                $kind,
                $code,
                $qty,
                $category,
                $eventId,
                $teamCode
            );
        } catch (\InvalidArgumentException $e) {
            throw new ApiException($e->getMessage(), 400);
        } catch (\RuntimeException $e) {
            throw new ApiException($e->getMessage(), 400);
        }

        return array_merge(['status' => 'ok'], $result, [
            'game' => (new GameProfileService())->getSummary($userId),
        ]);
    }

    public function moderatorRemoveListingAction(int $listingId, string $reason = ''): array
    {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        if (!(new ImpersonationService())->canImpersonate($userId)) {
            throw new ApiException('Нет доступа', 403);
        }

        try {
            $result = (new ExchangeService())->moderatorRemoveListing($userId, $listingId, $reason);
        } catch (\RuntimeException $e) {
            throw new ApiException($e->getMessage(), 400);
        }

        return array_merge(['status' => 'ok'], $result);
    }

    public function getLaborStateAction(): array
    {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        $service = new LaborExchangeService();

        return [
            'status' => 'ok',
            'labor' => array_merge($service->getLaborMeta(), [
                'professions' => $service->getPostableProfessions(),
                'my_profession_codes' => $this->resolveUserProfessionCodes($userId),
            ]),
        ];
    }

    public function getLaborOrdersAction(int $offset = 0, int $limit = 25): array
    {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        return array_merge(
            ['status' => 'ok'],
            (new LaborExchangeService())->getOpenOrders($userId, $offset, $limit)
        );
    }

    public function getMyLaborOrdersAction(): array
    {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        return [
            'status' => 'ok',
            'items' => (new LaborExchangeService())->getMyOrders($userId),
        ];
    }

    public function createLaborOrderAction(
        string $professionCode,
        int $iterations,
        float $payPerCycle = 0
    ): array {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        if ($payPerCycle <= 0) {
            $payPerCycle = LaborExchangeConfig::DEFAULT_PAY_PER_CYCLE;
        }

        try {
            $order = (new LaborExchangeService())->createOrder(
                $userId,
                $professionCode,
                $iterations,
                $payPerCycle
            );
        } catch (\InvalidArgumentException $e) {
            throw new ApiException($e->getMessage(), 400);
        } catch (\RuntimeException $e) {
            throw new ApiException($e->getMessage(), 400);
        }

        return array_merge(['status' => 'ok', 'order' => $order], [
            'game' => (new GameProfileService())->getSummary($userId),
        ]);
    }

    public function cancelLaborOrderAction(int $orderId): array
    {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        try {
            $order = (new LaborExchangeService())->cancelOrder($userId, $orderId);
        } catch (\RuntimeException $e) {
            throw new ApiException($e->getMessage(), 400);
        }

        return array_merge(['status' => 'ok', 'order' => $order], [
            'game' => (new GameProfileService())->getSummary($userId),
        ]);
    }

    public function claimLaborOrderAction(int $orderId, int $iterations = 0): array
    {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        try {
            $result = (new LaborExchangeService())->claimOrder($userId, $orderId, $iterations);
        } catch (\InvalidArgumentException $e) {
            throw new ApiException($e->getMessage(), 400);
        } catch (\RuntimeException $e) {
            throw new ApiException($e->getMessage(), 400);
        }

        return array_merge(['status' => 'ok'], $result, [
            'game' => (new GameProfileService())->getSummary($userId),
        ]);
    }

    public function startLaborWorkshopAction(int $orderId, int $iterations = 0): array
    {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        try {
            $result = (new LaborExchangeService())->startPosterWorkshop($userId, $orderId, $iterations);
        } catch (\InvalidArgumentException $e) {
            throw new ApiException($e->getMessage(), 400);
        } catch (\RuntimeException $e) {
            throw new ApiException($e->getMessage(), 400);
        }

        return array_merge(['status' => 'ok'], $result, [
            'game' => (new GameProfileService())->getSummary($userId),
        ]);
    }

    /**
     * @return string[]
     */
    private function resolveUserProfessionCodes(int $userId): array
    {
        $codes = [];
        foreach ((new ProfessionRepository())->getProfessionsByUserId($userId) as $row) {
            $code = (string)($row['UF_PROFESSION_CODE'] ?? '');
            if ($code !== '') {
                $codes[] = $code;
            }
        }

        return $codes;
    }
}
