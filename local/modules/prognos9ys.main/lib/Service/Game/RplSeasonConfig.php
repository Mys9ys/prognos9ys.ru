<?php

namespace Prognos9ys\Main\Service\Game;

use Bitrix\Main\Loader;

/**
 * Сезон РПЛ 2026/27 (событие events, XML_ID = rpl_2026_27).
 */
class RplSeasonConfig
{
    public const EVENT_XML_ID = 'rpl_2026_27';
    public const EVENT_CODE = 'rpl-2026-27';
    public const EVENT_NAME = 'Россия - Премьер-лига 2026-2027';

    /** Тип соревнования «Футбол» (iblock eventtype). */
    public const FOOTBALL_TYPE_ELEMENT_ID = 6836;

    /** PROPERTY_table enum XML_ID = easy («Простая»), как у события 60020. */
    public const TABLE_ENUM_XML_ID = 'easy';

    public const ACTIVE_FROM = '10.06.2026 00:00:00';
    public const ACTIVE_TO = '31.05.2027 23:59:59';

    private static ?int $eventId = null;

    public static function getEventId(): int
    {
        if (self::$eventId !== null) {
            return self::$eventId;
        }

        if (!Loader::includeModule('iblock')) {
            self::$eventId = 0;

            return 0;
        }

        $row = \CIBlockElement::GetList(
            [],
            [
                'IBLOCK_ID' => 1,
                '=XML_ID' => self::EVENT_XML_ID,
            ],
            false,
            ['nTopCount' => 1],
            ['ID']
        )->Fetch();

        self::$eventId = (int)($row['ID'] ?? 0);

        return self::$eventId;
    }

    public static function isRplEvent(int $eventId): bool
    {
        if ($eventId <= 0) {
            return false;
        }

        $rplId = self::getEventId();

        return $rplId > 0 && $eventId === $rplId;
    }

    public static function resetCache(): void
    {
        self::$eventId = null;
    }
}
