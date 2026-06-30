<?php

namespace Prognos9ys\Main\Service\Game;

/**
 * Очередь офлайн-работ Premium (этап 2).
 */
class PremiumWorkQueueConfig
{
    public const TASK_FARM = 'farm';
    public const TASK_ALBUM_CRAFT = 'album_craft';
    public const TASK_EXCHANGE_LIST = 'exchange_list';

    /** @var string[] */
    public const TASK_TYPES = [
        self::TASK_FARM,
        self::TASK_ALBUM_CRAFT,
        self::TASK_EXCHANGE_LIST,
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    /** @var string[] */
    public const TERMINAL_STATUSES = [
        self::STATUS_COMPLETED,
        self::STATUS_FAILED,
        self::STATUS_CANCELLED,
    ];

    public const LOG_LIMIT = 50;
}
