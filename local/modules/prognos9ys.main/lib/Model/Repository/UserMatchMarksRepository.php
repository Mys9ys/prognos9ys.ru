<?php

namespace Prognos9ys\Main\Model\Repository;

use Bitrix\Iblock\IblockTable;
use Bitrix\Main\Loader;

class UserMatchMarksRepository
{
    /**
     * @return array{prognosis: array<int, string>, results: array<int, string>}
     */
    public function loadForEvent(int $eventId, int $userId): array
    {
        if ($eventId <= 0 || $userId <= 0) {
            return ['prognosis' => [], 'results' => []];
        }

        if (!Loader::includeModule('iblock')) {
            return ['prognosis' => [], 'results' => []];
        }

        return [
            'prognosis' => $this->loadPrognosisTimes($eventId, $userId),
            'results' => $this->loadResultScores($eventId, $userId),
        ];
    }

  /**
     * @return array<int, string>
     */
    private function loadPrognosisTimes(int $eventId, int $userId): array
    {
        $iblockId = $this->resolveIblockId('prognosis', 6);
        $marks = [];

        $response = \CIBlockElement::GetList(
            [],
            [
                'IBLOCK_ID' => $iblockId,
                'PROPERTY_EVENTS' => $eventId,
                'PROPERTY_USER_ID' => $userId,
            ],
            false,
            false,
            ['PROPERTY_match_id', 'DATE_ACTIVE_FROM']
        );

        while ($row = $response->GetNext()) {
            $matchId = (int)($row['PROPERTY_MATCH_ID_VALUE'] ?? 0);
            if ($matchId <= 0) {
                continue;
            }
            $marks[$matchId] = (string)ConvertDateTime($row['DATE_ACTIVE_FROM'], 'DD.MM HH:Mi');
        }

        return $marks;
    }

    /**
     * @return array<int, string>
     */
    private function loadResultScores(int $eventId, int $userId): array
    {
        $iblockId = $this->resolveIblockId('result', 7);
        $marks = [];

        $response = \CIBlockElement::GetList(
            [],
            [
                'IBLOCK_ID' => $iblockId,
                'PROPERTY_EVENTS' => $eventId,
                'PROPERTY_USER_ID' => $userId,
            ],
            false,
            false,
            ['PROPERTY_all', 'PROPERTY_match_id']
        );

        while ($row = $response->GetNext()) {
            $matchId = (int)($row['PROPERTY_MATCH_ID_VALUE'] ?? 0);
            if ($matchId <= 0) {
                continue;
            }
            $marks[$matchId] = (string)($row['PROPERTY_ALL_VALUE'] ?? '');
        }

        return $marks;
    }

    private function resolveIblockId(string $code, int $fallback): int
    {
        $row = IblockTable::getRow([
            'filter' => ['=CODE' => $code],
            'select' => ['ID'],
        ]);

        return (int)($row['ID'] ?? $fallback);
    }
}
