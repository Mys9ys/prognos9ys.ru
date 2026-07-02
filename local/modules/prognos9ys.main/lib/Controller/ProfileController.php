<?php

namespace Prognos9ys\Main\Controller;

use Prognos9ys\Main\Service\Auth\TokenAuthService;
use Prognos9ys\Main\Service\Profile\PublicProfileService;

class ProfileController extends BaseController
{
    public function configureActions(): array
    {
        return [
            'getPublicProfile' => $this->getDefaultConfigureForPostPublic(),
            'getMyProfile' => $this->getDefaultConfigureForPostToken(),
        ];
    }

    /**
     * Публичный профиль — без авторизации, для шаринга.
     */
    public function getPublicProfileAction(int $userId): array
    {
        $service = new PublicProfileService();
        $profile = $service->getByUserId($userId);

        return [
            'status' => 'ok',
            'profile' => $service->toLegacyFormat($profile),
        ];
    }

    /**
     * Профиль текущего пользователя по токену мобильного приложения.
     */
    public function getMyProfileAction(): array
    {
        $userId = TokenAuthService::getCurrentUserId();

        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        $service = new PublicProfileService();
        $profile = $service->getByUserId($userId, true, false);

        return [
            'status' => 'ok',
            'profile' => $service->toLegacyFormat($profile),
        ];
    }
}
