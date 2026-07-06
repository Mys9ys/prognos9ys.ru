<?php

namespace Prognos9ys\Main\Controller;

use Prognos9ys\Main\Service\Analytics\ScreenVisitLogService;
use Prognos9ys\Main\Service\Auth\ImpersonationConfig;
use Prognos9ys\Main\Service\Auth\TokenAuthService;

class AnalyticsController extends BaseController
{
    public function configureActions(): array
    {
        return [
            'logScreenVisit' => $this->getDefaultConfigureForPostPublic(),
            'getVisitStats' => $this->getDefaultConfigureForPostToken(),
        ];
    }

    public function logScreenVisitAction(
        string $screen,
        string $userToken = '',
        string $referrer = ''
    ): array {
        $userId = 0;
        if ($userToken !== '') {
            $userId = (int)((new TokenAuthService())->getUserIdByToken($userToken) ?: 0);
        }

        try {
            (new ScreenVisitLogService())->logVisit(
                $screen,
                $userId,
                null,
                null,
                $referrer
            );
        } catch (\Throwable $e) {
            // Аналитика не должна ломать клиент.
        }

        return ['status' => 'ok'];
    }

    public function getVisitStatsAction(int $days = 30): array
    {
        $userId = TokenAuthService::getCurrentUserId();
        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        if (!ImpersonationConfig::canViewVisitStats($userId)) {
            throw new ApiException('Недостаточно прав', 403);
        }

        $stats = (new ScreenVisitLogService())->buildStats($days);

        return array_merge(['status' => 'ok'], $stats);
    }
}
