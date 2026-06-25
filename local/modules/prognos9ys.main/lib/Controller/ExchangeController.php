<?php

namespace Prognos9ys\Main\Controller;

use Prognos9ys\Main\Service\Auth\ImpersonationService;
use Prognos9ys\Main\Service\Auth\TokenAuthService;
use Prognos9ys\Main\Service\Game\ExchangeService;
use Prognos9ys\Main\Service\Game\GameProfileService;

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
            'moderatorRemoveListing' => $this->getDefaultConfigureForPostToken(),
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

    public function getCatalogAction(int $offset = 0, int $limit = 25, string $kind = ''): array
    {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        return array_merge(
            ['status' => 'ok'],
            (new ExchangeService())->getCatalog($offset, $limit, $kind)
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
        string $teamCode = ''
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
}
