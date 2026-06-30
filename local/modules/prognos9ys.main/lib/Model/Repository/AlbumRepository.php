<?php

namespace Prognos9ys\Main\Model\Repository;

use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\Loader;
use Bitrix\Main\Type\DateTime;
use Prognos9ys\Main\Service\Game\AlbumConfig;
use Prognos9ys\Main\Service\Game\GameEconomyHlInstaller;

class AlbumRepository
{
    /** @var bool */
    private static $schemaReady = false;

    private ?string $userAlbumDataClass = null;
    private ?string $albumSlotDataClass = null;

    public function ensureSchema(): void
    {
        if (self::$schemaReady) {
            return;
        }

        (new GameEconomyHlInstaller())->upgradeCollectionAlbumHl();
        $this->userAlbumDataClass = null;
        $this->albumSlotDataClass = null;
        self::$schemaReady = true;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAlbumsByUserId(int $userId): array
    {
        $this->ensureSchema();
        if ($userId <= 0) {
            return [];
        }

        $dataClass = $this->getUserAlbumDataClass();
        $rows = [];
        $response = $dataClass::getList([
            'filter' => ['=UF_USER_ID' => $userId],
            'order' => ['ID' => 'ASC'],
        ]);

        while ($row = $response->fetch()) {
            $rows[] = $row;
        }

        return $rows;
    }

    public function getAlbumById(int $albumId, int $userId): ?array
    {
        $this->ensureSchema();
        if ($albumId <= 0 || $userId <= 0) {
            return null;
        }

        $dataClass = $this->getUserAlbumDataClass();

        return $dataClass::getList([
            'filter' => [
                '=ID' => $albumId,
                '=UF_USER_ID' => $userId,
            ],
            'limit' => 1,
        ])->fetch() ?: null;
    }

    public function createAlbum(int $userId, int $eventId = 0): int
    {
        $this->ensureSchema();
        $now = new DateTime();
        $dataClass = $this->getUserAlbumDataClass();
        $result = $dataClass::add([
            'UF_USER_ID' => $userId,
            'UF_COLLECTION' => AlbumConfig::COLLECTION_UNIVERSAL,
            'UF_EVENT_ID' => max(0, $eventId),
            'UF_CREATED_AT' => $now,
            'UF_UPDATED_AT' => $now,
        ]);

        if (!$result->isSuccess()) {
            throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
        }

        return (int)$result->getId();
    }

    public function updateAlbum(int $albumId, array $fields): void
    {
        $this->ensureSchema();
        $fields['UF_UPDATED_AT'] = new DateTime();
        $dataClass = $this->getUserAlbumDataClass();
        $result = $dataClass::update($albumId, $fields);

        if (!$result->isSuccess()) {
            throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getSlotsByAlbumId(int $albumId): array
    {
        $grouped = $this->getSlotsByAlbumIds([$albumId]);

        return $grouped[$albumId] ?? [];
    }

    /**
     * @param array<int, int> $albumIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function getSlotsByAlbumIds(array $albumIds): array
    {
        $this->ensureSchema();
        $albumIds = array_values(array_filter(array_map('intval', $albumIds), static function (int $id): bool {
            return $id > 0;
        }));

        $grouped = [];
        foreach ($albumIds as $albumId) {
            $grouped[$albumId] = [];
        }

        if (!$albumIds) {
            return $grouped;
        }

        $dataClass = $this->getAlbumSlotDataClass();
        $response = $dataClass::getList([
            'filter' => ['@UF_ALBUM_ID' => $albumIds],
            'order' => ['UF_ALBUM_ID' => 'ASC', 'UF_TEAM_SLUG' => 'ASC'],
        ]);

        while ($row = $response->fetch()) {
            $albumId = (int)($row['UF_ALBUM_ID'] ?? 0);
            if ($albumId <= 0) {
                continue;
            }
            if (!isset($grouped[$albumId])) {
                $grouped[$albumId] = [];
            }
            $grouped[$albumId][] = $row;
        }

        return $grouped;
    }

    public function getSlotByAlbumAndTeam(int $albumId, string $teamSlug): ?array
    {
        $this->ensureSchema();
        $dataClass = $this->getAlbumSlotDataClass();

        return $dataClass::getList([
            'filter' => [
                '=UF_ALBUM_ID' => $albumId,
                '=UF_TEAM_SLUG' => $teamSlug,
            ],
            'limit' => 1,
        ])->fetch() ?: null;
    }

    public function addSlot(int $albumId, string $teamSlug, string $itemCode): int
    {
        $this->ensureSchema();
        $now = new DateTime();
        $dataClass = $this->getAlbumSlotDataClass();
        $result = $dataClass::add([
            'UF_ALBUM_ID' => $albumId,
            'UF_TEAM_SLUG' => $teamSlug,
            'UF_ITEM_CODE' => $itemCode,
            'UF_GLUED_AT' => $now,
        ]);

        if (!$result->isSuccess()) {
            throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
        }

        return (int)$result->getId();
    }

    public function deleteSlot(int $slotId): void
    {
        $this->ensureSchema();
        if ($slotId <= 0) {
            return;
        }

        $dataClass = $this->getAlbumSlotDataClass();
        $result = $dataClass::delete($slotId);
        if (!$result->isSuccess()) {
            throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
        }
    }

    public function countSlotsByAlbumId(int $albumId): int
    {
        $this->ensureSchema();
        if ($albumId <= 0) {
            return 0;
        }

        $dataClass = $this->getAlbumSlotDataClass();

        return (int)$dataClass::getCount([
            '=UF_ALBUM_ID' => $albumId,
        ]);
    }

    public function deleteAlbum(int $albumId): void
    {
        $this->ensureSchema();
        if ($albumId <= 0) {
            return;
        }

        $dataClass = $this->getUserAlbumDataClass();
        $result = $dataClass::delete($albumId);
        if (!$result->isSuccess()) {
            throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
        }
    }

    public function countGluedByUserAndCollection(int $userId, string $collection): int
    {
        $this->ensureSchema();
        if ($userId <= 0 || $collection === '') {
            return 0;
        }

        $albumIds = [];
        foreach ($this->getAlbumsByUserId($userId) as $album) {
            if ((string)($album['UF_COLLECTION'] ?? '') === $collection) {
                $albumIds[] = (int)$album['ID'];
            }
        }

        if (!$albumIds) {
            return 0;
        }

        $dataClass = $this->getAlbumSlotDataClass();

        return (int)$dataClass::getCount([
            '@UF_ALBUM_ID' => $albumIds,
        ]);
    }

    private function getUserAlbumDataClass(): string
    {
        return $this->userAlbumDataClass ??= $this->compileDataClass(GameEconomyHlInstaller::TABLE_USER_ALBUM);
    }

    private function getAlbumSlotDataClass(): string
    {
        return $this->albumSlotDataClass ??= $this->compileDataClass(GameEconomyHlInstaller::TABLE_ALBUM_SLOT);
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
            throw new \RuntimeException('HL-блок не найден: ' . $tableName);
        }

        $entity = HighloadBlockTable::compileEntity($hlblock);

        return $entity->getDataClass();
    }
}
