<?php

namespace Prognos9ys\Main\Service\Football;

use Prognos9ys\Main\Model\Repository\MatchesRepository;

class MatchListService
{
    public function getByEventId(int $eventId): array
    {
        if ($eventId <= 0) {
            return ['items' => [], 'total' => 0];
        }

        $rows = (new MatchesRepository())
            ->dataObjectBuilder([$eventId])
            ->setOrder(['ACTIVE_FROM' => 'ASC', 'ID' => 'ASC'])
            ->fetchAll();

        $items = [];

        foreach ($rows as $row) {
            $items[] = [
                'id' => (int)$row['ID'],
                'name' => (string)($row['NAME'] ?? ''),
                'active' => (string)($row['ACTIVE'] ?? ''),
                'date_active_from' => !empty($row['ACTIVE_FROM'])
                    ? (string)$row['ACTIVE_FROM']
                    : null,
                'home_id' => $row['HOME_VALUE'] ?? null,
                'guest_id' => $row['GUEST_VALUE'] ?? null,
                'goal_home' => $row['GOAL_HOME_VALUE'] ?? null,
                'goal_guest' => $row['GOAL_GUEST_VALUE'] ?? null,
                'group_id' => $row['GROUP_VALUE'] ?? null,
                'stage' => $row['STAGE_VALUE'] ?? null,
                'number' => $row['NUMBER_VALUE'] ?? null,
                'event_id' => $row['EVENTS_VALUE'] ?? null,
                'round' => $row['ROUND_VALUE'] ?? null,
            ];
        }

        return [
            'event_id' => $eventId,
            'items' => $items,
            'total' => count($items),
        ];
    }
}
