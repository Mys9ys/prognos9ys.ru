<?php

namespace Prognos9ys\Main\Service\Game;

class LaborExchangeConfig
{
    public const MAX_CYCLES_PER_CLAIM = 5;

    public const MIN_ITERATIONS = 1;

    public const MAX_ITERATIONS = 10000;

    public const MIN_PAY_PER_CYCLE = 0.1;

    public const DEFAULT_PAY_PER_CYCLE = 2.0;

    public const STATUS_OPEN = 'open';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const POSTER_KIND_USER = 'user';

    public const POSTER_KIND_TREASURY = 'treasury';
}
