<?php

namespace Prognos9ys\Main\Model\Repository;

use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\Loader;
use Prognos9ys\Main\Service\Rating\RatingSetHlInstaller;

class RatingSetRepository
{
    private ?string $setDataClass = null;
    private ?string $memberDataClass = null;
    private ?string $eventDataClass = null;

    public function getSetDataClass(): string
    {
        return $this->setDataClass ??= $this->compileDataClass(RatingSetHlInstaller::TABLE_SET);
    }

    public function getMemberDataClass(): string
    {
        return $this->memberDataClass ??= $this->compileDataClass(RatingSetHlInstaller::TABLE_MEMBER);
    }

    public function getEventDataClass(): string
    {
        return $this->eventDataClass ??= $this->compileDataClass(RatingSetHlInstaller::TABLE_EVENT);
    }

    public function addSet(array $fields): int
    {
        $dataClass = $this->getSetDataClass();
        $result = $dataClass::add($fields);

        if (!$result->isSuccess()) {
            throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
        }

        return (int)$result->getId();
    }

    public function updateSet(int $id, array $fields): void
    {
        $dataClass = $this->getSetDataClass();
        $result = $dataClass::update($id, $fields);

        if (!$result->isSuccess()) {
            throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
        }
    }

    public function getSetById(int $id): ?array
    {
        $dataClass = $this->getSetDataClass();
        $row = $dataClass::getById($id)->fetch();

        return $row ?: null;
    }

    /**
     * @return array<int, array>
     */
    public function getSetsByOwner(int $ownerId, ?string $sport = null): array
    {
        $filter = [
            '=UF_OWNER_ID' => $ownerId,
            '=UF_ACTIVE' => 1,
        ];

        if ($sport) {
            $filter['=UF_SPORT'] = $sport;
        }

        return $this->fetchSets($filter);
    }

    /**
     * @return array<int, array>
     */
    public function getPublicSets(?string $sport = null): array
    {
        $filter = [
            '=UF_VISIBILITY' => 'open',
            '=UF_ACTIVE' => 1,
        ];

        if ($sport) {
            $filter['=UF_SPORT'] = $sport;
        }

        return $this->fetchSets($filter);
    }

    /**
     * Закрытые сборники, где пользователь в составе, но не владелец.
     *
     * @return array<int, array>
     */
    public function getClosedSetsForMember(int $userId, ?string $sport = null): array
    {
        $memberDataClass = $this->getMemberDataClass();
        $setIds = [];

        $response = $memberDataClass::getList([
            'filter' => ['=UF_USER_ID' => $userId],
            'select' => ['UF_SET_ID'],
        ]);

        while ($row = $response->fetch()) {
            $setIds[] = (int)$row['UF_SET_ID'];
        }

        $setIds = array_values(array_unique(array_filter($setIds)));

        if (!$setIds) {
            return [];
        }

        $filter = [
            '@ID' => $setIds,
            '=UF_VISIBILITY' => 'closed',
            '=UF_ACTIVE' => 1,
            '!=UF_OWNER_ID' => $userId,
        ];

        if ($sport) {
            $filter['=UF_SPORT'] = $sport;
        }

        return $this->fetchSets($filter);
    }

    public function isMember(int $setId, int $userId): bool
    {
        $memberDataClass = $this->getMemberDataClass();

        $row = $memberDataClass::getList([
            'filter' => [
                '=UF_SET_ID' => $setId,
                '=UF_USER_ID' => $userId,
            ],
            'select' => ['ID'],
            'limit' => 1,
        ])->fetch();

        return (bool)$row;
    }

    /**
     * @param array<string, mixed> $filter
     * @return array<int, array>
     */
    private function fetchSets(array $filter): array
    {
        $dataClass = $this->getSetDataClass();
        $rows = [];
        $response = $dataClass::getList([
            'filter' => $filter,
            'order' => ['ID' => 'DESC'],
        ]);

        while ($row = $response->fetch()) {
            $rows[(int)$row['ID']] = $row;
        }

        return $rows;
    }

    public function replaceMembers(int $setId, array $userIds): void
    {
        $dataClass = $this->getMemberDataClass();

        $existing = $dataClass::getList([
            'filter' => ['=UF_SET_ID' => $setId],
            'select' => ['ID'],
        ]);

        while ($row = $existing->fetch()) {
            $dataClass::delete($row['ID']);
        }

        $sort = 100;
        foreach ($userIds as $userId) {
            $result = $dataClass::add([
                'UF_SET_ID' => $setId,
                'UF_USER_ID' => (int)$userId,
                'UF_SORT' => $sort,
            ]);

            if (!$result->isSuccess()) {
                throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
            }

            $sort += 100;
        }
    }

    /**
     * @return int[]
     */
    public function getMemberIds(int $setId): array
    {
        $dataClass = $this->getMemberDataClass();
        $ids = [];
        $response = $dataClass::getList([
            'filter' => ['=UF_SET_ID' => $setId],
            'order' => ['UF_SORT' => 'ASC', 'ID' => 'ASC'],
            'select' => ['UF_USER_ID'],
        ]);

        while ($row = $response->fetch()) {
            $ids[] = (int)$row['UF_USER_ID'];
        }

        return array_values(array_unique($ids));
    }

    public function replaceEvents(int $setId, array $eventIds): void
    {
        $dataClass = $this->getEventDataClass();

        $existing = $dataClass::getList([
            'filter' => ['=UF_SET_ID' => $setId],
            'select' => ['ID'],
        ]);

        while ($row = $existing->fetch()) {
            $dataClass::delete($row['ID']);
        }

        foreach ($eventIds as $eventId) {
            $eventId = (int)$eventId;
            if ($eventId <= 0) {
                continue;
            }

            $result = $dataClass::add([
                'UF_SET_ID' => $setId,
                'UF_EVENT_ID' => $eventId,
            ]);

            if (!$result->isSuccess()) {
                throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
            }
        }
    }

    /**
     * @return int[]
     */
    public function getEventIds(int $setId): array
    {
        $dataClass = $this->getEventDataClass();
        $ids = [];
        $response = $dataClass::getList([
            'filter' => ['=UF_SET_ID' => $setId],
            'order' => ['UF_EVENT_ID' => 'ASC'],
            'select' => ['UF_EVENT_ID'],
        ]);

        while ($row = $response->fetch()) {
            $ids[] = (int)$row['UF_EVENT_ID'];
        }

        return array_values(array_unique($ids));
    }

    private function compileDataClass(string $tableName): string
    {
        if (!Loader::includeModule('highloadblock')) {
            throw new \RuntimeException('Модуль highloadblock не установлен');
        }

        $hlblock = HighloadBlockTable::getList([
            'filter' => ['=TABLE_NAME' => $tableName],
        ])->fetch();

        if (!$hlblock) {
            throw new \RuntimeException('HL-блок не найден: ' . $tableName . '. Запустите install_rating_sets_hl.php');
        }

        $entity = HighloadBlockTable::compileEntity($hlblock);

        return $entity->getDataClass();
    }
}
