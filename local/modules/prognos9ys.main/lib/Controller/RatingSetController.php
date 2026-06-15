<?php

namespace Prognos9ys\Main\Controller;

use Prognos9ys\Main\Service\Auth\TokenAuthService;
use Prognos9ys\Main\Service\Rating\RatingSetService;

class RatingSetController extends BaseController
{
    public function configureActions(): array
    {
        return [
            'create' => $this->getDefaultConfigureForPostToken(),
            'update' => $this->getDefaultConfigureForPostToken(),
            'delete' => $this->getDefaultConfigureForPostToken(),
            'listMy' => $this->getDefaultConfigureForPostToken(),
            'listPublic' => $this->getDefaultConfigureForPostPublic(),
            'get' => $this->getDefaultConfigureForPostPublic(),
        ];
    }

    public function createAction(
        string $visibility = RatingSetService::VISIBILITY_CLOSED,
        string $sport = 'football',
        ?string $title = null,
        array $userIds = [],
        array $eventIds = []
    ): array {
        $ownerId = $this->requireUserId();

        return (new RatingSetService())->create($ownerId, [
            'visibility' => $visibility,
            'sport' => $sport,
            'title' => $title,
            'userIds' => $userIds,
            'eventIds' => $eventIds,
        ]);
    }

    public function updateAction(
        int $setId,
        ?string $title = null,
        ?string $visibility = null,
        ?array $userIds = null,
        ?array $eventIds = null
    ): array {
        $ownerId = $this->requireUserId();
        $payload = [];

        if ($title !== null) {
            $payload['title'] = $title;
        }
        if ($visibility !== null) {
            $payload['visibility'] = $visibility;
        }
        if ($userIds !== null) {
            $payload['userIds'] = $userIds;
        }
        if ($eventIds !== null) {
            $payload['eventIds'] = $eventIds;
        }

        return (new RatingSetService())->update($ownerId, $setId, $payload);
    }

    public function deleteAction(int $setId): array
    {
        $ownerId = $this->requireUserId();

        return (new RatingSetService())->delete($ownerId, $setId);
    }

    public function listMyAction(string $sport = 'football', ?int $eventId = null): array
    {
        $ownerId = $this->requireUserId();

        return (new RatingSetService())->listMy($ownerId, $sport, $eventId);
    }

    public function listPublicAction(string $sport = 'football', ?int $eventId = null): array
    {
        return (new RatingSetService())->listPublic($sport, $eventId);
    }

    public function getAction(int $setId, ?string $userToken = null, ?string $token = null): array
    {
        $viewerUserId = $this->resolveViewerUserId($userToken, $token);

        return (new RatingSetService())->getById($setId, $viewerUserId);
    }

    private function requireUserId(): int
    {
        $userId = TokenAuthService::getCurrentUserId();

        if (!$userId) {
            throw new ApiException('Пользователь не авторизован', 401);
        }

        return $userId;
    }

    private function resolveViewerUserId(?string $userToken, ?string $token): ?int
    {
        $authToken = $userToken ?: $token;

        if ($authToken) {
            return (new TokenAuthService())->getUserIdByToken($authToken);
        }

        return TokenAuthService::getCurrentUserId();
    }
}
