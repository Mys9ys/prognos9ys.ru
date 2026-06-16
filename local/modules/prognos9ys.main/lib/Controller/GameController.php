<?php

namespace Prognos9ys\Main\Controller;

use Prognos9ys\Main\Service\Auth\ImpersonationService;
use Prognos9ys\Main\Service\Auth\TokenAuthService;
use Prognos9ys\Main\Service\Game\ExperienceService;
use Prognos9ys\Main\Service\Game\GameBankService;
use Prognos9ys\Main\Service\Game\GameProfileService;
use Prognos9ys\Main\Service\Game\LevelService;
use Prognos9ys\Main\Service\Game\WalletService;
use Prognos9ys\Main\Service\Game\WealthRatingService;

class GameController extends BaseController
{
    public function configureActions(): array
    {
        return [
            'getState' => $this->getDefaultConfigureForPostToken(),
            'claimXp' => $this->getDefaultConfigureForPostToken(),
            'getLevelTiers' => $this->getDefaultConfigureForPostPublic(),
            'getWealthRating' => $this->getDefaultConfigureForPostPublic(),
            'getGameBank' => $this->getDefaultConfigureForPostToken(),
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

    public function getLevelTiersAction(): array
    {
        return [
            'status' => 'ok',
            'tiers' => array_values((new LevelService())->getTiers()),
        ];
    }

    public function getWealthRatingAction(int $limit = 30, string $wealthSort = 'rich'): array
    {
        return (new WealthRatingService())->getRating($limit, $wealthSort);
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
        ];
    }
}
