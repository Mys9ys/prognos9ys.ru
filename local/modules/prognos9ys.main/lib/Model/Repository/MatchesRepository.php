<?php

namespace Prognos9ys\Main\Model\Repository;

use Bitrix\Main\ORM\Query\Query;
use Prognos9ys\Main\Model\BaseIblockRepository;
use Prognos9ys\Main\Service\Championship\PlayoffSlotHelper;

class MatchesRepository extends BaseIblockRepository
{
    public const IBLOCK_CODE = 'matches';

    public const SELECT_FIELDS = [
        'ID',
        'IBLOCK_ID',
        'NAME',
        'ACTIVE',
        'ACTIVE_FROM',
        'XML_ID',
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

    /**
     * Один ORM-запрос: все матчи события для таблицы чемпионата.
     *
     * @return list<array<string, mixed>>
     */
    public function findForChampionshipTable(int $eventId): array
    {
        if ($eventId <= 0) {
            return [];
        }

        $meta = new MatchIblockMetaRepository();
        $stageDetailCode = $meta->getStageDetailPropertyCode();
        $select = $this->buildChampionshipSelect($meta, $stageDetailCode);

        $rows = $this->setSelect($select, false)
            ->dataObjectBuilder([$eventId])
            ->setOrder(['NUMBER_VALUE' => 'ASC', 'ID' => 'ASC'])
            ->fetchAll();

        return array_map(
            static fn(array $row) => self::normalizeChampionshipRow($row, $stageDetailCode),
            $rows
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findNormalizedById(int $matchId): ?array
    {
        if ($matchId <= 0) {
            return null;
        }

        $meta = new MatchIblockMetaRepository();
        $stageDetailCode = $meta->getStageDetailPropertyCode();
        $select = $this->buildChampionshipSelect($meta, $stageDetailCode);

        $row = $this->setSelect($select, false)
            ->dataObjectBuilder([], [$matchId])
            ->fetch();

        if (!$row) {
            return null;
        }

        return self::normalizeChampionshipRow($row, $stageDetailCode);
    }

    private function buildChampionshipSelect(MatchIblockMetaRepository $meta, ?string $stageDetailCode): array
    {
        $select = self::SELECT_FIELDS;

        foreach ([
            'result' => 'RESULT',
            'bracket_code' => 'BRACKET_CODE',
            'home_label' => 'HOME_LABEL',
            'guest_label' => 'GUEST_LABEL',
            'step' => 'STEP',
        ] as $code => $ormName) {
            if ($meta->hasProperty($code)) {
                $select[$ormName . '_'] = $ormName;
            }
        }

        if ($stageDetailCode && $stageDetailCode !== 'step' && $meta->hasProperty($stageDetailCode)) {
            $ormName = strtoupper($stageDetailCode);
            $select[$ormName . '_'] = $ormName;
        }

        return $select;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function normalizeChampionshipRow(array $row, ?string $stageDetailCode): array
    {
        $stageDetail = '';
        if ($stageDetailCode) {
            $key = strtoupper($stageDetailCode) . '_VALUE';
            $stageDetail = trim((string)($row[$key] ?? ''));
        }

        $bracketCode = trim((string)($row['BRACKET_CODE_VALUE'] ?? ''));
        $step = (int)($row['STEP_VALUE'] ?? 0);
        if ($step <= 0 && $bracketCode !== '') {
            $order = PlayoffSlotHelper::bracketCodeOrder($bracketCode);
            if ($order < 9999) {
                $step = $order;
            }
        }

        $dateFrom = $row['ACTIVE_FROM'] ?? '';
        if ($dateFrom instanceof \Bitrix\Main\Type\DateTime) {
            $dateFrom = $dateFrom->format('d.m.Y H:i:s');
        }

        return [
            'id' => (int)$row['ID'],
            'name' => (string)($row['NAME'] ?? ''),
            'active' => (string)($row['ACTIVE'] ?? 'Y'),
            'date_active_from' => (string)$dateFrom,
            'home_id' => (int)($row['HOME_VALUE'] ?? 0),
            'guest_id' => (int)($row['GUEST_VALUE'] ?? 0),
            'goal_home' => $row['GOAL_HOME_VALUE'] ?? null,
            'goal_guest' => $row['GOAL_GUEST_VALUE'] ?? null,
            'result' => (string)($row['RESULT_VALUE'] ?? ''),
            'group' => (string)($row['GROUP_VALUE'] ?? ''),
            'stage' => (string)($row['STAGE_VALUE'] ?? ''),
            'number' => (int)($row['NUMBER_VALUE'] ?? 0),
            'event_id' => (int)($row['EVENTS_VALUE'] ?? 0),
            'round' => (int)($row['ROUND_VALUE'] ?? 0),
            'bracket_code' => $bracketCode,
            'home_label' => (string)($row['HOME_LABEL_VALUE'] ?? ''),
            'guest_label' => (string)($row['GUEST_LABEL_VALUE'] ?? ''),
            'stage_detail' => $stageDetail,
            'step' => $step,
        ];
    }
}
