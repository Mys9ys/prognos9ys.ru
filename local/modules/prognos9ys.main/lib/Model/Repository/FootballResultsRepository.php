<?php

namespace Prognos9ys\Main\Model\Repository;

use Bitrix\Main\Loader;

class FootballResultsRepository
{
    private const SELECT_FIELDS = [
        'ID',
        'DATE_ACTIVE_FROM',
        'PROPERTY_all',
        'PROPERTY_score',
        'PROPERTY_number',
        'PROPERTY_match_id',
        'PROPERTY_user_id',
        'PROPERTY_result',
        'PROPERTY_diff',
        'PROPERTY_corner',
        'PROPERTY_yellow',
        'PROPERTY_red',
        'PROPERTY_penalty',
        'PROPERTY_sum',
        'PROPERTY_domination',
        'PROPERTY_otime',
        'PROPERTY_spenalty',
    ];

    private ?int $iblockId = null;

    /**
     * @return \Generator<int, array<string, mixed>>
     */
    public function fetchByEvent(int $eventId): \Generator
    {
        if ($eventId <= 0) {
            return;
        }

        if (!Loader::includeModule('iblock')) {
            throw new \RuntimeException('Модуль iblock не установлен');
        }

        $response = \CIBlockElement::GetList(
            ['PROPERTY_NUMBER' => 'ASC'],
            $this->buildFilter($eventId),
            false,
            [],
            self::SELECT_FIELDS
        );

        while ($res = $response->GetNext()) {
            yield $res;
        }
    }

    private function buildFilter(int $eventId): array
    {
        $filter = ['IBLOCK_ID' => $this->getIblockId()];

        if ($eventId === 34) {
            $filter['!=PROPERTY_events'] = 6664;
        } else {
            $filter['PROPERTY_events'] = $eventId;
        }

        return $filter;
    }

    private function getIblockId(): int
    {
        if ($this->iblockId !== null) {
            return $this->iblockId;
        }

        $this->iblockId = (int)(\CIBlock::GetList([], ['CODE' => 'result'], false)->Fetch()['ID'] ?: 7);

        return $this->iblockId;
    }

    /**
     * Агрегаты для ачивок по всем результатам события (один проход по iblock).
     *
     * @return array<int, array<string, int>>
     */
    public function aggregateAchievementStatsByUserForEvent(int $eventId): array
    {
        if ($eventId <= 0) {
            return [];
        }

        $map = [];
        foreach ($this->fetchByEvent($eventId) as $row) {
            $userId = (int)($row['PROPERTY_USER_ID_VALUE'] ?? 0);
            if ($userId <= 0) {
                continue;
            }

            if (!isset($map[$userId])) {
                $map[$userId] = $this->emptyAchievementStatsRow();
            }

            $stats = &$map[$userId];
            $all = (float)($row['PROPERTY_ALL_VALUE'] ?? 0);
            if ($all >= 40) {
                $stats['score_40_plus']++;
            } elseif ($all >= 30) {
                $stats['score_30_39']++;
            } elseif ($all == 0.0) {
                $stats['score_0']++;
            }

            $exactScore = (float)($row['PROPERTY_SCORE_VALUE'] ?? 0);
            $outcome = (float)($row['PROPERTY_RESULT_VALUE'] ?? 0);
            $diff = (float)($row['PROPERTY_DIFF_VALUE'] ?? 0);
            $sum = (float)($row['PROPERTY_SUM_VALUE'] ?? 0);
            $domination = (float)($row['PROPERTY_DOMINATION_VALUE'] ?? 0);
            $yellow = (float)($row['PROPERTY_YELLOW_VALUE'] ?? 0);
            $red = (float)($row['PROPERTY_RED_VALUE'] ?? 0);
            $corner = (float)($row['PROPERTY_CORNER_VALUE'] ?? 0);
            $penalty = (float)($row['PROPERTY_PENALTY_VALUE'] ?? 0);
            $otime = (float)($row['PROPERTY_OTIME_VALUE'] ?? 0);
            $spenalty = (float)($row['PROPERTY_SPENALTY_VALUE'] ?? 0);

            if ($exactScore >= 10) {
                $stats['metric_exact_score']++;
            }
            if ($outcome >= 5) {
                $stats['metric_outcome']++;
            }
            if ($sum >= 5) {
                $stats['metric_total_goals']++;
            }
            if ($diff >= 5) {
                $stats['metric_goal_diff']++;
            }

            $stats['metric_corners'] += (int)round($corner);
            $stats['metric_yellow'] += (int)round($yellow);
            $stats['metric_possession'] += (int)round($domination);

            if ($this->isRareRedPenaltyFactScore($red)) {
                $stats['rare_red']++;
            }
            if ($this->isRareRedPenaltyFactScore($penalty)) {
                $stats['rare_penalty']++;
            }
            if ($this->isWowRedPenaltyScore($red)) {
                $stats['wow_red']++;
            }
            if ($this->isWowRedPenaltyScore($penalty)) {
                $stats['wow_pen']++;
            }
            if ($otime >= 5) {
                $stats['metric_extra_time']++;
            }
            if ($spenalty >= 5) {
                $stats['metric_shootout']++;
            }
            unset($stats);
        }

        return $map;
    }

    /**
     * @return array<string, int>
     */
    private function emptyAchievementStatsRow(): array
    {
        return [
            'score_30_39' => 0,
            'score_40_plus' => 0,
            'score_0' => 0,
            'metric_exact_score' => 0,
            'metric_outcome' => 0,
            'metric_total_goals' => 0,
            'metric_goal_diff' => 0,
            'metric_corners' => 0,
            'metric_yellow' => 0,
            'metric_possession' => 0,
            'rare_red' => 0,
            'rare_penalty' => 0,
            'wow_red' => 0,
            'wow_pen' => 0,
            'metric_extra_time' => 0,
            'metric_shootout' => 0,
        ];
    }

    private function isRareRedPenaltyFactScore(float $score): bool
    {
        return abs($score - 5.0) < 0.001;
    }

    private function isWowRedPenaltyScore(float $score): bool
    {
        return $score >= 6.999;
    }
}
