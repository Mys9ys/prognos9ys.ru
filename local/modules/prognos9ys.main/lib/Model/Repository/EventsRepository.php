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
        'EXTERNAL_ID',
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
}
