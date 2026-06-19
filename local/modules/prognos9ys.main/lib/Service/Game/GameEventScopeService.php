<?php

namespace Prognos9ys\Main\Service\Game;

use Bitrix\Main\Loader;

/**
 * Игровая экономика действует на события с ACTIVE_FROM не раньше ECONOMY_ACTIVITY_SINCE
 * (по умолчанию 10.06.2026). При ANCHOR_ONLY_SCOPE=true — только якорное ЧМ-событие.
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

        $eventTs = $this->getEventActiveFromTs($eventId);

        if ($eventTs === null || $eventTs < GameEconomyConfig::getEconomyActivitySinceTimestamp()) {
            return false;
        }

        $anchorId = $this->getAnchorEventId();

        if ($anchorId <= 0) {
            return false;
        }

        if (GameEconomyConfig::ANCHOR_ONLY_SCOPE) {
            return $eventId === $anchorId;
        }

        return true;
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
            return GameEconomyConfig::isMatchNumberInTestScope($matchNumber);
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

        if (GameEconomyConfig::ANCHOR_ONLY_SCOPE) {
            return [$anchorId];
        }

        $minTs = GameEconomyConfig::getEconomyActivitySinceTimestamp();
        $ids = [];

        $response = \CIBlockElement::GetList(
            ['ID' => 'ASC'],
            ['IBLOCK_ID' => self::EVENTS_IBLOCK_ID],
            false,
            false,
            ['ID', 'ACTIVE_FROM']
        );

        while ($row = $response->GetNext()) {
            $eventId = (int)$row['ID'];
            $eventTs = $this->parseActiveFrom($row['ACTIVE_FROM'] ?? null);

            if ($eventTs !== null && $eventTs >= $minTs) {
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

    public function getLastSettledMatchIdForEvent(int $eventId): int
    {
        $match = $this->getLastSettledMatchForEvent($eventId);

        return (int)($match['id'] ?? 0);
    }

    /**
     * Последний завершённый матч события (ACTIVE=N), в рамках игровой экономики.
     *
     * @return array{id:int,number:int}
     */
    public function getLastSettledMatchForEvent(int $eventId): array
    {
        if ($eventId <= 0 || !Loader::includeModule('iblock')) {
            return ['id' => 0, 'number' => 0];
        }

        $filter = [
            'IBLOCK_ID' => 2,
            'PROPERTY_events' => $eventId,
            'ACTIVE' => 'N',
        ];

        if (GameEconomyConfig::isTestMatchNumberLimitEnabled()) {
            $filter['>=PROPERTY_number'] = GameEconomyConfig::getTestMatchNumberMin();
            $filter['<=PROPERTY_number'] = GameEconomyConfig::getTestMatchNumberMax();
        }

        $response = \CIBlockElement::GetList(
            ['PROPERTY_number' => 'DESC', 'ID' => 'DESC'],
            $filter,
            false,
            false,
            ['ID', 'PROPERTY_events', 'PROPERTY_number']
        );

        while ($row = $response->GetNext()) {
            $matchId = (int)($row['ID'] ?? 0);
            $matchNumber = $this->extractMatchNumberFromRow($row);
            $rowEventId = (int)($row['PROPERTY_EVENTS_VALUE'] ?? $eventId);

            if ($matchId <= 0 || $matchNumber <= 0) {
                continue;
            }

            if (!$this->isMatchInScope($rowEventId, $matchNumber)) {
                continue;
            }

            return ['id' => $matchId, 'number' => $matchNumber];
        }

        return ['id' => 0, 'number' => 0];
    }

    public function getMatchNumber(int $matchId): int
    {
        if ($matchId <= 0 || !Loader::includeModule('iblock')) {
            return 0;
        }

        $row = \CIBlockElement::GetList(
            [],
            ['IBLOCK_ID' => 2, 'ID' => $matchId],
            false,
            false,
            ['ID', 'PROPERTY_number', 'PROPERTY_events']
        )->GetNext();

        if (!$row) {
            return 0;
        }

        return $this->extractMatchNumberFromRow($row);
    }

    public function formatMatchLabel(int $matchId): string
    {
        if ($matchId <= 0) {
            return '';
        }

        $number = $this->getMatchNumber($matchId);

        return $number > 0 ? 'матч №' . $number : '';
    }

    public function formatMatchLabelByNumber(int $matchNumber): string
    {
        return $matchNumber > 0 ? 'матч №' . $matchNumber : '';
    }

    /**
     * @param array<string, mixed> $row HL-строка вклада/займа
     * @return array{
     *   opening_match_id:int,
     *   opening_match_number:int,
     *   opening_match_label:string,
     *   created_match_label:string
     * }
     */
    public function resolveOpeningMatchMeta(array $row): array
    {
        $openingMatchId = (int)($row['UF_OPENING_MATCH_ID'] ?? 0);
        $openingMatchNumber = (int)($row['UF_OPENING_MATCH_NUMBER'] ?? 0);

        if ($openingMatchNumber <= 0 && $openingMatchId > 0) {
            $openingMatchNumber = $this->getMatchNumber($openingMatchId);
        }

        $label = $this->formatMatchLabelByNumber($openingMatchNumber);

        return [
            'opening_match_id' => $openingMatchId,
            'opening_match_number' => $openingMatchNumber,
            'opening_match_label' => $label,
            'created_match_label' => $openingMatchNumber > 0
                ? 'создан после ' . $label
                : 'создан до первого результата',
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function extractMatchNumberFromRow(array $row): int
    {
        foreach (['PROPERTY_NUMBER_VALUE', 'PROPERTY_number_VALUE'] as $key) {
            $number = (int)($row[$key] ?? 0);
            if ($number > 0) {
                return $number;
            }
        }

        return 0;
    }
}
