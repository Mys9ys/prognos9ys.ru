<?php

namespace Prognos9ys\Main\Controller;

use Prognos9ys\Main\Controller\ApiException;
use Prognos9ys\Main\Service\Auth\ImpersonationService;
use Prognos9ys\Main\Service\Auth\TokenAuthService;

class ImpersonationController extends BaseController
{
    public function configureActions(): array
    {
        return [
            'searchUsers' => $this->getDefaultConfigureForPostToken(),
            'start' => $this->getDefaultConfigureForPostToken(),
            'stop' => $this->getDefaultConfigureForPostPublic(),
        ];
    }

    public function searchUsersAction(string $query = ''): array
    {
        $actorUserId = TokenAuthService::getCurrentUserId();
        $service = new ImpersonationService();

        if (!$actorUserId || !$service->canImpersonate($actorUserId)) {
            throw new ApiException('Недостаточно прав', 403);
        }

        return [
            'users' => $service->searchUsers($query),
        ];
    }

    public function startAction(int $targetUserId): array
    {
        $actorUserId = TokenAuthService::getCurrentUserId();

        if (!$actorUserId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        return (new ImpersonationService())->start($actorUserId, $targetUserId);
    }

    public function stopAction(string $moderatorToken): array
    {
        return (new ImpersonationService())->stop($moderatorToken);
    }
}
