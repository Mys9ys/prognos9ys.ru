<?php

namespace Prognos9ys\Main\Model\Repository;

use Bitrix\Main\ORM\Query\Query;
use Prognos9ys\Main\Model\BaseIblockRepository;

class CountriesRepository extends BaseIblockRepository
{
    public const IBLOCK_CODE = 'countries';

    public const SELECT_FIELDS = [
        'ID',
        'NAME',
        'PREVIEW_PICTURE',
    ];

    protected array $selectFields = self::SELECT_FIELDS;

    public function dataObjectBuilder(array $ids = []): Query
    {
        $query = $this->queryObject();

        if ($ids) {
            $query->whereIn('ID', $ids);
        }

        return $query;
    }

    /**
     * @param list<int> $ids
     * @return array<int, array<string, mixed>>
     */
    public function findIndexedByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) {
            return [];
        }

        $indexed = [];
        $rows = $this->dataObjectBuilder($ids)
            ->setOrder(['NAME' => 'ASC'])
            ->fetchAll();

        foreach ($rows as $row) {
            $id = (int)$row['ID'];
            $indexed[$id] = [
                'ID' => $id,
                'NAME' => (string)($row['NAME'] ?? ''),
                'img' => !empty($row['PREVIEW_PICTURE'])
                    ? (string)\CFile::GetPath($row['PREVIEW_PICTURE'])
                    : '',
                'flag' => !empty($row['PREVIEW_PICTURE'])
                    ? (string)\CFile::GetPath($row['PREVIEW_PICTURE'])
                    : '',
            ];
        }

        return $indexed;
    }
}
