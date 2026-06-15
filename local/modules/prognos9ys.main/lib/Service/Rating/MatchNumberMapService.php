<?php

namespace Prognos9ys\Main\Service\Rating;

use Bitrix\Main\Loader;

/**
 * Маппинг ID матча → номер тура внутри события.
 */
class MatchNumberMapService
{
    private ?int $matchesIblockId = null;

    public function getMapForEvent(int $eventId): array
    {
        if ($eventId <= 0) {
            return [];
        }

        if (!Loader::includeModule('iblock')) {
            throw new \RuntimeException('Модуль iblock не установлен');
        }

        $map = [];

        $response = \CIBlockElement::GetList(
            ['DATE_ACTIVE_FROM' => 'ASC', 'created' => 'ASC'],
            [
                'IBLOCK_ID' => $this->getMatchesIblockId(),
                'PROPERTY_EVENTS' => $eventId,
            ],
            false,
            [],
            [
                'ID',
                'PROPERTY_number',
            ]
        );

        while ($res = $response->GetNext()) {
            $map[$res['ID']] = $res['PROPERTY_NUMBER_VALUE'];
        }

        return $map;
    }

    private function getMatchesIblockId(): int
    {
        if ($this->matchesIblockId !== null) {
            return $this->matchesIblockId;
        }

        $this->matchesIblockId = (int)(\CIBlock::GetList([], ['CODE' => 'matches'], false)->Fetch()['ID'] ?: 2);

        return $this->matchesIblockId;
    }
}
