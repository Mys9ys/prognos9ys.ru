<?php

namespace Prognos9ys\Main\Service\Football;

use Bitrix\Main\Loader;

/**
 * Сводная статистика события: факты матчей и прогнозы пользователя.
 */
class EventStatisticsService
{
    private const MATCHES_IBLOCK_ID = 2;
    private const RESULT_IBLOCK_ID = 7;

    public function getForEvent(int $eventId, int $userId = 0): array
    {
        if ($eventId <= 0) {
            return ['status' => 'error', 'message' => 'Некорректное событие'];
        }

        if (!Loader::includeModule('iblock')) {
            throw new \RuntimeException('Модуль iblock не установлен');
        }

        $games = $this->aggregateGameStats($eventId);
        $prognosis = $userId > 0
            ? $this->aggregatePrognosisStats($eventId, $userId)
            : $this->emptyPrognosisStats();

        return [
            'status' => 'ok',
            'event_id' => $eventId,
            'games' => $games,
            'prognosis' => array_merge($prognosis, [
                'logged_in' => $userId > 0,
            ]),
        ];
    }

    /**
     * @return array{matches_count:int, metrics: array<int, array<string, mixed>>}
     */
    private function aggregateGameStats(int $eventId): array
    {
        $totals = [
            'goals' => 0.0,
            'corners' => 0.0,
            'yellow' => 0.0,
            'red' => 0.0,
            'penalty' => 0.0,
            'extra_time' => 0.0,
            'shootout' => 0.0,
        ];
        $matchesCount = 0;

        $response = \CIBlockElement::GetList(
            ['PROPERTY_number' => 'ASC', 'ID' => 'ASC'],
            [
                'IBLOCK_ID' => self::MATCHES_IBLOCK_ID,
                'PROPERTY_EVENTS' => $eventId,
                'ACTIVE' => 'N',
            ],
            false,
            false,
            [
                'ID',
                'PROPERTY_goal_home',
                'PROPERTY_goal_guest',
                'PROPERTY_corner',
                'PROPERTY_yellow',
                'PROPERTY_red',
                'PROPERTY_penalty',
                'PROPERTY_otime',
                'PROPERTY_spenalty',
            ]
        );

        while ($row = $response->GetNext()) {
            $matchesCount++;
            $goalHome = (int)($row['PROPERTY_GOAL_HOME_VALUE'] ?? 0);
            $goalGuest = (int)($row['PROPERTY_GOAL_GUEST_VALUE'] ?? 0);
            $totals['goals'] += $goalHome + $goalGuest;
            $totals['corners'] += (int)($row['PROPERTY_CORNER_VALUE'] ?? 0);
            $totals['yellow'] += (int)($row['PROPERTY_YELLOW_VALUE'] ?? 0);
            $totals['red'] += (int)($row['PROPERTY_RED_VALUE'] ?? 0);
            $totals['penalty'] += (int)($row['PROPERTY_PENALTY_VALUE'] ?? 0);

            if ($this->isPlayoffFact((string)($row['PROPERTY_OTIME_VALUE'] ?? ''))) {
                $totals['extra_time']++;
            }
            if ($this->isPlayoffFact((string)($row['PROPERTY_SPENALTY_VALUE'] ?? ''))) {
                $totals['shootout']++;
            }
        }

        return [
            'matches_count' => $matchesCount,
            'metrics' => $this->buildGameMetrics($totals, $matchesCount),
        ];
    }

    /**
     * @return array{matches_count:int, metrics: array<int, array<string, mixed>>}
     */
    private function aggregatePrognosisStats(int $eventId, int $userId): array
    {
        $fields = [
            'score' => ['hits' => 0, 'points' => 0.0],
            'outcome' => ['hits' => 0, 'points' => 0.0],
            'sum' => ['hits' => 0, 'points' => 0.0],
            'diff' => ['hits' => 0, 'points' => 0.0],
            'possession' => ['hits' => 0, 'points' => 0.0],
            'corners' => ['hits' => 0, 'points' => 0.0],
            'yellow' => ['hits' => 0, 'points' => 0.0],
            'red' => ['hits' => 0, 'points' => 0.0],
            'penalty' => ['hits' => 0, 'points' => 0.0],
            'extra_time' => ['hits' => 0, 'points' => 0.0],
            'shootout' => ['hits' => 0, 'points' => 0.0],
        ];
        $matchesCount = 0;

        $response = \CIBlockElement::GetList(
            ['PROPERTY_number' => 'ASC', 'ID' => 'ASC'],
            [
                'IBLOCK_ID' => self::RESULT_IBLOCK_ID,
                'PROPERTY_user_id' => $userId,
                'PROPERTY_events' => $eventId,
            ],
            false,
            false,
            [
                'ID',
                'PROPERTY_score',
                'PROPERTY_result',
                'PROPERTY_diff',
                'PROPERTY_sum',
                'PROPERTY_domination',
                'PROPERTY_corner',
                'PROPERTY_yellow',
                'PROPERTY_red',
                'PROPERTY_penalty',
                'PROPERTY_otime',
                'PROPERTY_spenalty',
            ]
        );

        while ($row = $response->GetNext()) {
            $matchesCount++;
            $this->accumulatePrognosisMetric($fields['score'], (float)($row['PROPERTY_SCORE_VALUE'] ?? 0), 10.0);
            $this->accumulatePrognosisMetric($fields['outcome'], (float)($row['PROPERTY_RESULT_VALUE'] ?? 0), 5.0);
            $this->accumulatePrognosisMetric($fields['sum'], (float)($row['PROPERTY_SUM_VALUE'] ?? 0), 5.0);
            $this->accumulatePrognosisMetric($fields['diff'], (float)($row['PROPERTY_DIFF_VALUE'] ?? 0), 5.0);
            $this->accumulatePrognosisMetric($fields['possession'], (float)($row['PROPERTY_DOMINATION_VALUE'] ?? 0));
            $this->accumulatePrognosisMetric($fields['corners'], (float)($row['PROPERTY_CORNER_VALUE'] ?? 0));
            $this->accumulatePrognosisMetric($fields['yellow'], (float)($row['PROPERTY_YELLOW_VALUE'] ?? 0));
            $this->accumulateRareRedPenaltyMetric($fields['red'], (float)($row['PROPERTY_RED_VALUE'] ?? 0));
            $this->accumulateRareRedPenaltyMetric($fields['penalty'], (float)($row['PROPERTY_PENALTY_VALUE'] ?? 0));
            $this->accumulatePrognosisMetric($fields['extra_time'], (float)($row['PROPERTY_OTIME_VALUE'] ?? 0), 5.0);
            $this->accumulatePrognosisMetric($fields['shootout'], (float)($row['PROPERTY_SPENALTY_VALUE'] ?? 0), 5.0);
        }

        return [
            'matches_count' => $matchesCount,
            'metrics' => $this->buildPrognosisMetrics($fields),
        ];
    }

    /**
     * @return array{matches_count:int, metrics: array<int, array<string, mixed>>}
     */
    private function emptyPrognosisStats(): array
    {
        $fields = [
            'score' => ['hits' => 0, 'points' => 0.0],
            'outcome' => ['hits' => 0, 'points' => 0.0],
            'sum' => ['hits' => 0, 'points' => 0.0],
            'diff' => ['hits' => 0, 'points' => 0.0],
            'possession' => ['hits' => 0, 'points' => 0.0],
            'corners' => ['hits' => 0, 'points' => 0.0],
            'yellow' => ['hits' => 0, 'points' => 0.0],
            'red' => ['hits' => 0, 'points' => 0.0],
            'penalty' => ['hits' => 0, 'points' => 0.0],
            'extra_time' => ['hits' => 0, 'points' => 0.0],
            'shootout' => ['hits' => 0, 'points' => 0.0],
        ];

        return [
            'matches_count' => 0,
            'metrics' => $this->buildPrognosisMetrics($fields),
        ];
    }

    /**
     * @param array<string, float> $totals
     * @return array<int, array<string, mixed>>
     */
    private function buildGameMetrics(array $totals, int $matchesCount): array
    {
        $definitions = [
            ['key' => 'goals', 'label' => 'Голы', 'icon' => 'sum'],
            ['key' => 'corners', 'label' => 'Угловые', 'icon' => 'corners'],
            ['key' => 'yellow', 'label' => 'Жёлтые карточки', 'icon' => 'yellow'],
            ['key' => 'red', 'label' => 'Красные карточки', 'icon' => 'red'],
            ['key' => 'penalty', 'label' => 'Пенальти', 'icon' => 'penalty'],
            ['key' => 'extra_time', 'label' => 'Доп. время', 'icon' => 'extra_time'],
            ['key' => 'shootout', 'label' => 'Серия пенальти', 'icon' => 'shootout'],
        ];

        $metrics = [];
        foreach ($definitions as $definition) {
            $key = $definition['key'];
            $total = (float)($totals[$key] ?? 0);
            $metrics[] = [
                'key' => $key,
                'label' => $definition['label'],
                'icon' => $definition['icon'],
                'total' => $this->formatNumber($total),
                'avg' => $this->formatNumber($this->avg($total, $matchesCount)),
            ];
        }

        return $metrics;
    }

    /**
     * @param array<string, array{hits:int, points:float}> $fields
     * @return array<int, array<string, mixed>>
     */
    private function buildPrognosisMetrics(array $fields): array
    {
        $definitions = [
            ['key' => 'score', 'label' => 'Счёт матча', 'icon' => 'score', 'achievement_stat' => 'metric_exact_score'],
            ['key' => 'outcome', 'label' => 'Исход матча', 'icon' => 'outcome', 'achievement_stat' => 'metric_outcome'],
            ['key' => 'sum', 'label' => 'Сумма голов', 'icon' => 'sum', 'achievement_stat' => 'metric_total_goals'],
            ['key' => 'diff', 'label' => 'Разница голов', 'icon' => 'diff', 'achievement_stat' => 'metric_goal_diff'],
            ['key' => 'possession', 'label' => 'Владение', 'icon' => 'possession', 'achievement_stat' => 'metric_possession'],
            ['key' => 'corners', 'label' => 'Угловые', 'icon' => 'corners', 'achievement_stat' => 'metric_corners'],
            ['key' => 'yellow', 'label' => 'Жёлтые карточки', 'icon' => 'yellow', 'achievement_stat' => 'metric_yellow'],
            ['key' => 'red', 'label' => 'Красные карточки', 'icon' => 'red', 'achievement_stat' => 'rare_red'],
            ['key' => 'penalty', 'label' => 'Пенальти', 'icon' => 'penalty', 'achievement_stat' => 'rare_penalty'],
            ['key' => 'extra_time', 'label' => 'Доп. время', 'icon' => 'extra_time', 'achievement_stat' => 'metric_extra_time'],
            ['key' => 'shootout', 'label' => 'Серия пенальти', 'icon' => 'shootout', 'achievement_stat' => 'metric_shootout'],
        ];

        $metrics = [];
        foreach ($definitions as $definition) {
            $key = $definition['key'];
            $row = $fields[$key] ?? ['hits' => 0, 'points' => 0.0];
            $metrics[] = [
                'key' => $key,
                'label' => $definition['label'],
                'icon' => $definition['icon'],
                'achievement_stat' => $definition['achievement_stat'] ?? '',
                'hits' => (int)$row['hits'],
                'exact_hits' => (int)($row['exact_hits'] ?? 0),
                'points' => $this->formatNumber((float)$row['points']),
            ];
        }

        return $metrics;
    }

    /**
     * @param array{hits:int, points:float, exact_hits?:int} $bucket
     */
    private function accumulatePrognosisMetric(array &$bucket, float $points, ?float $exactMin = null): void
    {
        if ($points <= 0) {
            return;
        }

        $bucket['hits']++;
        $bucket['points'] = round($bucket['points'] + $points, 1);

        if ($exactMin !== null && $points >= $exactMin) {
            $bucket['exact_hits'] = (int)($bucket['exact_hits'] ?? 0) + 1;
        }
    }

    /**
     * @param array{hits:int, points:float, exact_hits?:int} $bucket
     */
    private function accumulateRareRedPenaltyMetric(array &$bucket, float $points): void
    {
        if ($points <= 0) {
            return;
        }

        $bucket['hits']++;
        $bucket['points'] = round($bucket['points'] + $points, 1);

        if (abs($points - 5.0) < 0.001) {
            $bucket['exact_hits'] = (int)($bucket['exact_hits'] ?? 0) + 1;
        }
    }

    private function isPlayoffFact(string $value): bool
    {
        return mb_strtolower(trim($value)) === 'будет';
    }

    private function avg(float $total, int $count): float
    {
        if ($count <= 0) {
            return 0.0;
        }

        return round($total / $count, 1);
    }

    private function formatNumber(float $value): float
    {
        return round($value, 1);
    }
}
