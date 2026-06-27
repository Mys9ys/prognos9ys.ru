<?php

namespace Prognos9ys\Main\Model\Repository;

use Bitrix\Main\ORM\Query\Query;
use Prognos9ys\Main\Model\BaseIblockRepository;

class EventsRepository extends BaseIblockRepository
{
    public const IBLOCK_CODE = 'events';

    public const SELECT_FIELDS = [
        'ID',
        'IBLOCK_ID',
        'NAME',
        'ACTIVE',
        'PREVIEW_TEXT',
        'PREVIEW_PICTURE',
        'DETAIL_TEXT',
        'DETAIL_PICTURE',
        'XML_ID',
        'E_TYPE_' => 'E_TYPE',
        'TABLE_' => 'TABLE',
    ];

    protected array $selectFields = self::SELECT_FIELDS;

    public function dataObjectBuilder(array $ids = [], ?string $active = null): Query
    {
        $query = $this->queryObject();

        if ($ids) {
            $query->whereIn('ID', $ids);
        }

        if ($active !== null) {
            $query->where('ACTIVE', $active);
        }

        return $query;
    }

    public function getChampionshipInfo(int $eventId): ?array
    {
        if ($eventId <= 0) {
            return null;
        }

        $row = $this->dataObjectBuilder([$eventId])->fetch();
        if (!$row) {
            return null;
        }

        $previewId = (int)($row['PREVIEW_PICTURE'] ?? 0);

        return [
            'ID' => (int)$row['ID'],
            'NAME' => (string)($row['NAME'] ?? ''),
            'ACTIVE' => (string)($row['ACTIVE'] ?? ''),
            'PREVIEW_TEXT' => (string)($row['PREVIEW_TEXT'] ?? ''),
            'DETAIL_TEXT' => (string)($row['DETAIL_TEXT'] ?? ''),
            'EXTERNAL_ID' => (string)($row['XML_ID'] ?? ''),
            'img' => $previewId > 0 ? (string)\CFile::GetPath($previewId) : '',
            'code' => (string)($row['E_TYPE_VALUE'] ?? ''),
            'table' => (string)($row['TABLE_VALUE'] ?? ''),
            'PROPERTY_E_TYPE_VALUE' => (string)($row['E_TYPE_VALUE'] ?? ''),
            'PROPERTY_TABLE_VALUE' => (string)($row['TABLE_VALUE'] ?? ''),
        ];
    }
}
