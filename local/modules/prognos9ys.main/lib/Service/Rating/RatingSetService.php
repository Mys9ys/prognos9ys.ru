<?php

namespace Prognos9ys\Main\Service\Rating;

use Bitrix\Main\UserTable;
use Prognos9ys\Main\Controller\ApiException;
use Prognos9ys\Main\Model\Repository\RatingSetRepository;

class RatingSetService
{
    public const VISIBILITY_OPEN = 'open';
    public const VISIBILITY_CLOSED = 'closed';
    public const VISIBILITY_PRIVATE = 'private';
    public const MIN_MEMBERS = 2;
    public const MAX_MEMBERS = 50;

    private RatingSetRepository $repository;

    public function __construct(?RatingSetRepository $repository = null)
    {
        $this->repository = $repository ?? new RatingSetRepository();
    }

    public function create(int $ownerId, array $payload): array
    {
        $visibility = $this->normalizeVisibility($payload['visibility'] ?? self::VISIBILITY_CLOSED);
        $sport = $this->normalizeSport($payload['sport'] ?? 'football');
        $userIds = $this->normalizeUserIds($payload['userIds'] ?? []);
        $eventIds = $this->normalizeEventIds($payload['eventIds'] ?? []);
        $title = $this->normalizeTitle($payload['title'] ?? null);

        $this->assertUsersExist($userIds);

        $setId = $this->repository->addSet([
            'UF_OWNER_ID' => $ownerId,
            'UF_TITLE' => $title,
            'UF_VISIBILITY' => $visibility,
            'UF_SPORT' => $sport,
            'UF_ACTIVE' => 1,
        ]);

        $this->repository->replaceMembers($setId, $userIds);
        $this->repository->replaceEvents($setId, $eventIds);

        return $this->getById($setId, $ownerId);
    }

    public function update(int $ownerId, int $setId, array $payload): array
    {
        $set = $this->getOwnedSet($setId, $ownerId);
        $fields = [];

        if (array_key_exists('title', $payload)) {
            $fields['UF_TITLE'] = $this->normalizeTitle($payload['title']);
        }

        if (array_key_exists('visibility', $payload)) {
            $fields['UF_VISIBILITY'] = $this->normalizeVisibility($payload['visibility']);
        }

        if ($fields) {
            $this->repository->updateSet((int)$set['ID'], $fields);
        }

        if (array_key_exists('userIds', $payload)) {
            $userIds = $this->normalizeUserIds($payload['userIds']);
            $this->assertUsersExist($userIds);
            $this->repository->replaceMembers($setId, $userIds);
        }

        if (array_key_exists('eventIds', $payload)) {
            $eventIds = $this->normalizeEventIds($payload['eventIds']);
            $this->repository->replaceEvents($setId, $eventIds);
        }

        return $this->getById($setId, $ownerId);
    }

    public function delete(int $ownerId, int $setId): array
    {
        $this->getOwnedSet($setId, $ownerId);
        $this->repository->updateSet($setId, ['UF_ACTIVE' => 0]);

        return ['status' => 'ok'];
    }

    /**
     * @return array{status: string, sets: array<int, array>}
     */
    public function listMy(int $ownerId, ?string $sport = null, ?int $eventId = null): array
    {
        $owned = $this->repository->getSetsByOwner($ownerId, $sport);
        $memberClosed = $this->repository->getClosedSetsForMember($ownerId, $sport);
        $sets = $owned + $memberClosed;

        return [
            'status' => 'ok',
            'sets' => $this->formatSetList($sets, $ownerId, $eventId),
        ];
    }

    /**
     * @return array{status: string, sets: array<int, array>}
     */
    public function listPublic(?string $sport = null, ?int $eventId = null): array
    {
        $sets = $this->repository->getPublicSets($sport);

        return [
            'status' => 'ok',
            'sets' => $this->formatSetList($sets, null, $eventId),
        ];
    }

    public function getById(int $setId, ?int $viewerUserId = null): array
    {
        $set = $this->repository->getSetById($setId);

        if (!$set || !(int)$set['UF_ACTIVE']) {
            throw new ApiException('Сборник не найден', 404);
        }

        if (!$this->canView($set, $viewerUserId)) {
            throw new ApiException('Нет доступа к этому сборнику', 403);
        }

        return [
            'status' => 'ok',
            'set' => $this->formatSet($set, true, $viewerUserId),
        ];
    }

    /**
     * @return int[]
     */
    public function getMemberIdsForRating(int $setId, ?int $viewerUserId, ?int $eventId = null): array
    {
        $set = $this->repository->getSetById($setId);

        if (!$set || !(int)$set['UF_ACTIVE']) {
            throw new ApiException('Сборник не найден', 404);
        }

        if (!$this->canView($set, $viewerUserId)) {
            throw new ApiException('Нет доступа к этому сборнику', 403);
        }

        if ($eventId && !$this->matchesEvent($setId, $eventId)) {
            throw new ApiException('Сборник не привязан к этому событию', 422);
        }

        return $this->repository->getMemberIds($setId);
    }

    public function canView(array $set, ?int $viewerUserId): bool
    {
        if ((int)$set['UF_ACTIVE'] !== 1) {
            return false;
        }

        $visibility = (string)($set['UF_VISIBILITY'] ?? '');

        if ($visibility === self::VISIBILITY_OPEN) {
            return true;
        }

        if (!$viewerUserId) {
            return false;
        }

        if ((int)$set['UF_OWNER_ID'] === $viewerUserId) {
            return true;
        }

        if ($visibility === self::VISIBILITY_CLOSED) {
            return $this->repository->isMember((int)$set['ID'], $viewerUserId);
        }

        return false;
    }

    public static function visibilityLabel(string $visibility): string
    {
        switch ($visibility) {
            case self::VISIBILITY_OPEN:
                return 'открытая';
            case self::VISIBILITY_CLOSED:
                return 'закрытая';
            case self::VISIBILITY_PRIVATE:
                return 'приватная';
            default:
                return $visibility;
        }
    }

    public function getDisplayName(array $set): string
    {
        $title = trim((string)($set['UF_TITLE'] ?? ''));

        if ($title !== '') {
            return $title;
        }

        return 'Сборник рейтингов #' . (int)$set['ID'];
    }

    private function getOwnedSet(int $setId, int $ownerId): array
    {
        $set = $this->repository->getSetById($setId);

        if (!$set || !(int)$set['UF_ACTIVE']) {
            throw new ApiException('Сборник не найден', 404);
        }

        if ((int)$set['UF_OWNER_ID'] !== $ownerId) {
            throw new ApiException('Нет прав на изменение сборника', 403);
        }

        return $set;
    }

    private function matchesEvent(int $setId, int $eventId): bool
    {
        $eventIds = $this->repository->getEventIds($setId);

        if (!$eventIds) {
            return true;
        }

        return in_array($eventId, $eventIds, true);
    }

    /**
     * @param array<int, array> $sets
     * @return array<int, array>
     */
    private function formatSetList(array $sets, ?int $viewerUserId, ?int $eventId): array
    {
        $result = [];

        foreach ($sets as $set) {
            if ($eventId && !$this->matchesEvent((int)$set['ID'], $eventId)) {
                continue;
            }

            if (!$this->canView($set, $viewerUserId)) {
                continue;
            }

            $result[] = $this->formatSet($set, false, $viewerUserId);
        }

        return array_values($result);
    }

    private function formatSet(array $set, bool $withMembers, ?int $viewerUserId = null): array
    {
        $setId = (int)$set['ID'];
        $visibility = (string)$set['UF_VISIBILITY'];
        $isOwner = $viewerUserId && (int)$set['UF_OWNER_ID'] === $viewerUserId;

        $item = [
            'id' => $setId,
            'ownerId' => (int)$set['UF_OWNER_ID'],
            'title' => trim((string)($set['UF_TITLE'] ?? '')),
            'displayName' => $this->getDisplayName($set),
            'visibility' => $visibility,
            'visibilityLabel' => self::visibilityLabel($visibility),
            'sport' => (string)$set['UF_SPORT'],
            'eventIds' => $this->repository->getEventIds($setId),
            'membersCount' => count($this->repository->getMemberIds($setId)),
            'isGlobal' => !$this->repository->getEventIds($setId),
            'isOwner' => $isOwner,
            'isMember' => $viewerUserId
                ? $this->repository->isMember($setId, $viewerUserId)
                : false,
        ];

        if ($withMembers) {
            $item['memberIds'] = $this->repository->getMemberIds($setId);
        }

        return $item;
    }

    private function normalizeVisibility(string $visibility): string
    {
        $visibility = strtolower(trim($visibility));

        if (!in_array($visibility, [
            self::VISIBILITY_OPEN,
            self::VISIBILITY_CLOSED,
            self::VISIBILITY_PRIVATE,
        ], true)) {
            throw new ApiException('Некорректный тип видимости', 422);
        }

        return $visibility;
    }

    private function normalizeSport(string $sport): string
    {
        $sport = strtolower(trim($sport));

        if (!in_array($sport, ['football', 'race'], true)) {
            throw new ApiException('Некорректный вид спорта', 422);
        }

        return $sport;
    }

    private function normalizeTitle(?string $title): ?string
    {
        if ($title === null) {
            return null;
        }

        $title = trim($title);

        return $title === '' ? null : mb_substr($title, 0, 100);
    }

    /**
     * @param mixed $userIds
     * @return int[]
     */
    private function normalizeUserIds($userIds): array
    {
        if (!is_array($userIds)) {
            throw new ApiException('Список участников обязателен', 422);
        }

        $ids = array_values(array_unique(array_map('intval', $userIds)));
        $ids = array_values(array_filter($ids, static fn($id) => $id > 0));

        if (count($ids) < self::MIN_MEMBERS) {
            throw new ApiException('Минимум ' . self::MIN_MEMBERS . ' участника в сборнике', 422);
        }

        if (count($ids) > self::MAX_MEMBERS) {
            throw new ApiException('Не больше ' . self::MAX_MEMBERS . ' участников в сборнике', 422);
        }

        return $ids;
    }

    /**
     * @param mixed $eventIds
     * @return int[]
     */
    private function normalizeEventIds($eventIds): array
    {
        if (!is_array($eventIds)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $eventIds), static fn($id) => $id > 0)));
    }

    /**
     * @param int[] $userIds
     */
    private function assertUsersExist(array $userIds): void
    {
        foreach ($userIds as $userId) {
            $user = UserTable::getRow([
                'select' => ['ID'],
                'filter' => ['=ID' => $userId],
            ]);

            if (!$user) {
                throw new ApiException('Пользователь #' . $userId . ' не найден', 422);
            }
        }
    }
}
