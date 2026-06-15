<?php

namespace Prognos9ys\Main\Model\Repository;

use Bitrix\Main\ORM\Query\Query;
use Prognos9ys\Main\Model\BaseIblockRepository;

class MatchesRepository extends BaseIblockRepository
{
    public const IBLOCK_CODE = 'matches';

    public const SELECT_FIELDS = [
        'ID',
        'IBLOCK_ID',
        'NAME',
        'ACTIVE',
        'ACTIVE_FROM',
        'HOME_' => 'HOME',
        'GUEST_' => 'GUEST',
        'GOAL_HOME_' => 'GOAL_HOME',
        'GOAL_GUEST_' => 'GOAL_GUEST',
        'GROUP_' => 'GROUP',
        'STAGE_' => 'STAGE',
        'NUMBER_' => 'NUMBER',
        'EVENTS_' => 'EVENTS',
        'ROUND_' => 'ROUND',
    ];

    protected array $selectFields = self::SELECT_FIELDS;

    public function dataObjectBuilder(array $eventIds = [], array $ids = [], ?string $active = null): Query
    {
        $query = $this->queryObject();

        if ($eventIds) {
            $query->whereIn('EVENTS_VALUE', $eventIds);
        }

        if ($ids) {
            $query->whereIn('ID', $ids);
        }

        if ($active !== null) {
            $query->where('ACTIVE', $active);
        }

        return $query;
    }
}
