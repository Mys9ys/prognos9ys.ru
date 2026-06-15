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
}
