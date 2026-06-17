<?php

namespace Prognos9ys\Main\Controller;

use Prognos9ys\Main\Service\Auth\ImpersonationService;
use Prognos9ys\Main\Service\Auth\TokenAuthService;
use Prognos9ys\Main\Service\Game\AchievementService;
use Prognos9ys\Main\Service\Game\BankDepositService;
use Prognos9ys\Main\Service\Game\BankLoanService;
use Prognos9ys\Main\Service\Game\ExperienceService;
use Prognos9ys\Main\Service\Game\GameBankService;
use Prognos9ys\Main\Service\Game\GameEconomyConfig;
use Prognos9ys\Main\Service\Game\GameProfileService;
use Prognos9ys\Main\Service\Game\LevelService;
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
            'getLevelTiers' => $this->getDefaultConfigureForPostPublic(),
            'getWealthRating' => $this->getDefaultConfigureForPostPublic(),
            'getGameBank' => $this->getDefaultConfigureForPostToken(),
            'listBanks' => $this->getDefaultConfigureForPostToken(),
            'getMyBank' => $this->getDefaultConfigureForPostToken(),
            'getMyContracts' => $this->getDefaultConfigureForPostToken(),
            'openBank' => $this->getDefaultConfigureForPostToken(),
            'createDeposit' => $this->getDefaultConfigureForPostToken(),
            'takeLoan' => $this->getDefaultConfigureForPostToken(),
            'closeBank' => $this->getDefaultConfigureForPostToken(),
            'getAchievements' => $this->getDefaultConfigureForPostToken(),
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

    public function createDepositAction(int $bankId, float $amount = 0): array
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
            'deposit' => (new BankDepositService())->createDeposit($userId, $bankId, $amount),
            'game' => (new GameProfileService())->getSummary($userId),
        ];
    }

    public function takeLoanAction(int $bankId, float $amount = 0): array
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
            'loan' => (new BankLoanService())->takeLoan($userId, $bankId, $amount),
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

    public function getAchievementsAction(): array
    {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        return array_merge(['status' => 'ok'], (new AchievementService())->getForUser($userId));
    }
}
