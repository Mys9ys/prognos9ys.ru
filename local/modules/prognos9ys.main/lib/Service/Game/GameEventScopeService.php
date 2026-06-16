<?php

namespace Prognos9ys\Main\Service\Game;

use Bitrix\Main\Loader;

/**
 * Игровая экономика действует только на ЧМ-2026 и события, начавшиеся не раньше него.
 */
class GameEventScopeService
{
    private const EVENTS_IBLOCK_ID = 1;

    /** ID события «ЧМ-2026» в текущей БД (см. test_football_ratings_compare.php). */
    private const FALLBACK_ANCHOR_EVENT_ID = 63849;

    private static ?int $anchorEventId = null;
    private static ?int $anchorActiveFromTs = null;

    public function isEventEligible(int $eventId): bool
    {
        if ($eventId <= 0) {
            return false;
        }

        $anchorId = $this->getAnchorEventId();

        if ($anchorId <= 0) {
            return false;
        }

        if ($eventId === $anchorId) {
            return true;
        }

        $anchorTs = $this->getAnchorActiveFromTs();

        if ($anchorTs === null) {
            return $eventId >= $anchorId;
        }

        $eventTs = $this->getEventActiveFromTs($eventId);

        if ($eventTs === null) {
            return false;
        }

        return $eventTs >= $anchorTs;
    }

    public function isMatchEligible(int $matchId): bool
    {
        if ($matchId <= 0 || !Loader::includeModule('iblock')) {
            return false;
        }

        $row = \CIBlockElement::GetList(
            [],
            [
                'IBLOCK_ID' => 2,
                'ID' => $matchId,
            ],
            false,
            false,
            ['ID', 'PROPERTY_events', 'PROPERTY_number']
        )->GetNext();

        if (!$row) {
            return false;
        }

        return $this->isMatchInScope(
            (int)$row['PROPERTY_EVENTS_VALUE'],
            (int)$row['PROPERTY_NUMBER_VALUE']
        );
    }

    public function isMatchInScope(int $eventId, int $matchNumber): bool
    {
        if (!$this->isEventEligible($eventId)) {
            return false;
        }

        if (GameEconomyConfig::isTestMatchNumberLimitEnabled()) {
            return $matchNumber === GameEconomyConfig::TEST_ONLY_MATCH_NUMBER;
        }

        return true;
    }

    /**
     * @return int[]
     */
    public function getEligibleEventIds(): array
    {
        if (!Loader::includeModule('iblock')) {
            return [];
        }

        $anchorId = $this->getAnchorEventId();

        if ($anchorId <= 0) {
            return [];
        }

        $anchorTs = $this->getAnchorActiveFromTs();
        $ids = [$anchorId];

        $response = \CIBlockElement::GetList(
            ['ID' => 'ASC'],
            ['IBLOCK_ID' => self::EVENTS_IBLOCK_ID],
            false,
            false,
            ['ID', 'ACTIVE_FROM']
        );

        while ($row = $response->GetNext()) {
            $eventId = (int)$row['ID'];

            if ($eventId === $anchorId) {
                continue;
            }

            if ($anchorTs === null) {
                if ($eventId > $anchorId) {
                    $ids[] = $eventId;
                }
                continue;
            }

            $eventTs = $this->parseActiveFrom($row['ACTIVE_FROM'] ?? null);

            if ($eventTs !== null && $eventTs >= $anchorTs) {
                $ids[] = $eventId;
            }
        }

        return array_values(array_unique($ids));
    }

    public function getAnchorEventId(): int
    {
        if (self::$anchorEventId !== null) {
            return self::$anchorEventId;
        }

        if (GameEconomyConfig::ANCHOR_EVENT_ID > 0) {
            self::$anchorEventId = GameEconomyConfig::ANCHOR_EVENT_ID;

            return self::$anchorEventId;
        }

        if (Loader::includeModule('iblock')) {
            $response = \CIBlockElement::GetList(
                ['ID' => 'ASC'],
                [
                    'IBLOCK_ID' => self::EVENTS_IBLOCK_ID,
                    '%NAME' => '2026',
                ],
                false,
                false,
                ['ID', 'NAME', 'ACTIVE_FROM']
            );

            while ($row = $response->GetNext()) {
                $name = mb_strtolower((string)$row['NAME']);

                if (
                    mb_strpos($name, '2026') !== false
                    && (mb_strpos($name, 'мир') !== false || mb_strpos($name, 'чм') !== false)
                ) {
                    self::$anchorEventId = (int)$row['ID'];
                    self::$anchorActiveFromTs = $this->parseActiveFrom($row['ACTIVE_FROM'] ?? null);

                    return self::$anchorEventId;
                }
            }
        }

        self::$anchorEventId = self::FALLBACK_ANCHOR_EVENT_ID;

        return self::$anchorEventId;
    }

    private function getAnchorActiveFromTs(): ?int
    {
        if (self::$anchorActiveFromTs !== null) {
            return self::$anchorActiveFromTs;
        }

        $anchorId = $this->getAnchorEventId();

        if ($anchorId <= 0) {
            return null;
        }

        self::$anchorActiveFromTs = $this->getEventActiveFromTs($anchorId);

        return self::$anchorActiveFromTs;
    }

    private function getEventActiveFromTs(int $eventId): ?int
    {
        if (!Loader::includeModule('iblock')) {
            return null;
        }

        $row = \CIBlockElement::GetList(
            [],
            [
                'IBLOCK_ID' => self::EVENTS_IBLOCK_ID,
                'ID' => $eventId,
            ],
            false,
            false,
            ['ID', 'ACTIVE_FROM']
        )->GetNext();

        if (!$row) {
            return null;
        }

        return $this->parseActiveFrom($row['ACTIVE_FROM'] ?? null);
    }

    /**
     * @param mixed $activeFrom
     */
    private function parseActiveFrom($activeFrom): ?int
    {
        if ($activeFrom instanceof \Bitrix\Main\Type\DateTime) {
            return $activeFrom->getTimestamp();
        }

        if (is_string($activeFrom) && $activeFrom !== '') {
            $ts = strtotime($activeFrom);

            return $ts !== false ? $ts : null;
        }

        return null;
    }
}
