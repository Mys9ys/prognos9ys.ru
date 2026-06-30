<?php

namespace Prognos9ys\Main\Service\Game;

/**
 * Конфигурация подписки Premium (свитки, биржа, очередь работ — см. PremiumService / docs).
 */
class PremiumEconomyConfig
{
    /** @var int[] */
    public const SCROLL_DAYS = [1, 3, 5];

    public const COMMISSION_PERCENT = 5.0;
    public const COMMISSION_PERCENT_DEFAULT = 20.0;

    public const MAX_LISTINGS = 30;
    public const MAX_LISTINGS_DEFAULT = 10;

    public const LISTING_DAYS = 7;
    public const LISTING_DAYS_DEFAULT = 3;

    /** Окно правки прогноза после старта матча (минуты) — этап 5. */
    public const PROGNOSIS_EDIT_WINDOW_MINUTES = 30;
}
