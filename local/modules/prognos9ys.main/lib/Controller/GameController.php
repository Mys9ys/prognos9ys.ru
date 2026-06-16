<?php

namespace Prognos9ys\Main\Controller;

use Prognos9ys\Main\Service\Auth\TokenAuthService;
use Prognos9ys\Main\Service\Game\ExperienceService;
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

    public function getWealthRatingAction(int $limit = 30): array
    {
        return (new WealthRatingService())->getRating($limit);
    }
}
