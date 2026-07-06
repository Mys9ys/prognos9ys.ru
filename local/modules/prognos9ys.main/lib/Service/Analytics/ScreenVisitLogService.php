<?php

namespace Prognos9ys\Main\Service\Analytics;

use Bitrix\Main\Type\DateTime;
use Prognos9ys\Main\Model\Repository\GameEconomyRepository;

class ScreenVisitLogService
{
    public const RETENTION_DAYS = 365;

    private const PURGE_OPTION = 'screen_visit_log_last_purge_at';
    private const PURGE_INTERVAL_SEC = 3600;

    private const MAX_SCREEN_LEN = 255;
    private const MAX_UA_LEN = 255;
    private const MAX_REFERRER_LEN = 512;

    private GameEconomyRepository $repository;

    public function __construct(?GameEconomyRepository $repository = null)
    {
        $this->repository = $repository ?? new GameEconomyRepository();
    }

    public function logVisit(
        string $screen,
        int $userId = 0,
        ?string $ip = null,
        ?string $userAgent = null,
        ?string $referrer = null
    ): void {
        $screen = $this->normalizeScreen($screen);
        if ($screen === '') {
            return;
        }

        $ip = $this->normalizeIp($ip ?? $this->resolveClientIp());
        $userAgent = $this->truncate((string)($userAgent ?? $this->resolveUserAgent()), self::MAX_UA_LEN);
        $referrer = $this->truncate((string)($referrer ?? ''), self::MAX_REFERRER_LEN);
        $isGuest = $userId <= 0;

        $this->repository->addScreenVisitLog([
            'UF_SCREEN' => $screen,
            'UF_IS_GUEST' => $isGuest ? 'Y' : 'N',
            'UF_USER_ID' => $isGuest ? 0 : $userId,
            'UF_IP' => $ip,
            'UF_VISITED_AT' => new DateTime(),
            'UF_USER_AGENT' => $userAgent,
            'UF_REFERRER' => $referrer,
            'UF_DEVICE' => $this->detectDevice($userAgent),
        ]);

        $this->maybePurgeOld();
    }

    /**
     * @return array{
     *   days:int,
     *   totals:array{visits:int,guest_visits:int,user_visits:int},
     *   daily:array<int,array{
     *     date:string,
     *     visits:int,
     *     unique_guests:int,
     *     unique_users:int
     *   }>,
     *   top_screens:array<int,array{screen:string,visits:int,guest_visits:int,user_visits:int}>,
     *   devices:array<int,array{device:string,visits:int}>,
     *   truncated:bool
     * }
     */
    public function buildStats(int $days = 30): array
    {
        $days = min(max($days, 1), 90);
        $since = (new DateTime())->add('-' . $days . ' days');

        $rows = $this->repository->getScreenVisitLogRowsSince($since, 100000);
        $truncated = count($rows) >= 100000;

        $totals = [
            'visits' => 0,
            'guest_visits' => 0,
            'user_visits' => 0,
        ];

        /** @var array<string, array{visits:int,guest_ips:array<string,bool>,user_ids:array<int,bool>}> */
        $daily = [];

        /** @var array<string, array{visits:int,guest_visits:int,user_visits:int}> */
        $screens = [];

        /** @var array<string, int> */
        $devices = [];

        foreach ($rows as $row) {
            $isGuest = ($row['UF_IS_GUEST'] ?? 'N') === 'Y';
            $screen = (string)($row['UF_SCREEN'] ?? '');
            $ip = (string)($row['UF_IP'] ?? '');
            $userId = (int)($row['UF_USER_ID'] ?? 0);
            $device = (string)($row['UF_DEVICE'] ?? 'unknown');
            $visitedAt = $row['UF_VISITED_AT'] ?? null;

            $dateKey = $this->formatDateKey($visitedAt);
            if ($dateKey === '') {
                continue;
            }

            $totals['visits']++;
            if ($isGuest) {
                $totals['guest_visits']++;
            } else {
                $totals['user_visits']++;
            }

            if (!isset($daily[$dateKey])) {
                $daily[$dateKey] = [
                    'visits' => 0,
                    'guest_ips' => [],
                    'user_ids' => [],
                ];
            }
            $daily[$dateKey]['visits']++;
            if ($isGuest && $ip !== '') {
                $daily[$dateKey]['guest_ips'][$ip] = true;
            } elseif (!$isGuest && $userId > 0) {
                $daily[$dateKey]['user_ids'][$userId] = true;
            }

            if ($screen !== '') {
                if (!isset($screens[$screen])) {
                    $screens[$screen] = [
                        'visits' => 0,
                        'guest_visits' => 0,
                        'user_visits' => 0,
                    ];
                }
                $screens[$screen]['visits']++;
                if ($isGuest) {
                    $screens[$screen]['guest_visits']++;
                } else {
                    $screens[$screen]['user_visits']++;
                }
            }

            if ($device === '') {
                $device = 'unknown';
            }
            $devices[$device] = ($devices[$device] ?? 0) + 1;
        }

        ksort($daily);

        $dailyList = [];
        foreach ($daily as $date => $item) {
            $dailyList[] = [
                'date' => $date,
                'visits' => (int)$item['visits'],
                'unique_guests' => count($item['guest_ips']),
                'unique_users' => count($item['user_ids']),
            ];
        }

        $topScreens = [];
        foreach ($screens as $screen => $item) {
            $topScreens[] = [
                'screen' => $screen,
                'visits' => (int)$item['visits'],
                'guest_visits' => (int)$item['guest_visits'],
                'user_visits' => (int)$item['user_visits'],
            ];
        }
        usort($topScreens, static function (array $a, array $b): int {
            return ($b['visits'] ?? 0) <=> ($a['visits'] ?? 0);
        });
        $topScreens = array_slice($topScreens, 0, 25);

        $deviceList = [];
        foreach ($devices as $device => $count) {
            $deviceList[] = [
                'device' => $device,
                'visits' => (int)$count,
            ];
        }
        usort($deviceList, static function (array $a, array $b): int {
            return ($b['visits'] ?? 0) <=> ($a['visits'] ?? 0);
        });

        return [
            'days' => $days,
            'totals' => $totals,
            'daily' => $dailyList,
            'top_screens' => $topScreens,
            'devices' => $deviceList,
            'truncated' => $truncated,
        ];
    }

    private function normalizeScreen(string $screen): string
    {
        $screen = trim($screen);
        if ($screen === '') {
            return '';
        }

        if ($screen[0] !== '/') {
            $screen = '/' . $screen;
        }

        return $this->truncate($screen, self::MAX_SCREEN_LEN);
    }

    private function normalizeIp(string $ip): string
    {
        $ip = trim($ip);
        if ($ip === '') {
            return 'unknown';
        }

        if (strpos($ip, ',') !== false) {
            $parts = explode(',', $ip);
            $ip = trim((string)($parts[0] ?? ''));
        }

        return $this->truncate($ip, 45);
    }

    private function truncate(string $value, int $maxLen): string
    {
        if ($maxLen <= 0) {
            return '';
        }

        if (mb_strlen($value) <= $maxLen) {
            return $value;
        }

        return mb_substr($value, 0, $maxLen);
    }

    private function resolveClientIp(): string
    {
        try {
            $request = \Bitrix\Main\Context::getCurrent()->getRequest();
            $ip = (string)$request->getRemoteAddress();
            if ($ip !== '') {
                return $ip;
            }
        } catch (\Throwable $e) {
            // fallback ниже
        }

        return (string)($_SERVER['REMOTE_ADDR'] ?? '');
    }

    private function resolveUserAgent(): string
    {
        try {
            $request = \Bitrix\Main\Context::getCurrent()->getRequest();
            return (string)$request->getUserAgent();
        } catch (\Throwable $e) {
            return (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
        }
    }

    private function detectDevice(string $userAgent): string
    {
        $ua = mb_strtolower($userAgent);
        if ($ua === '') {
            return 'unknown';
        }

        if (preg_match('/mobile|android|iphone|ipod|windows phone|webos|blackberry/i', $ua)) {
            return 'mobile';
        }

        if (preg_match('/ipad|tablet/i', $ua)) {
            return 'tablet';
        }

        return 'desktop';
    }

    /**
     * @param mixed $visitedAt
     */
    private function formatDateKey($visitedAt): string
    {
        if ($visitedAt instanceof DateTime) {
            return $visitedAt->format('Y-m-d');
        }

        if ($visitedAt instanceof \DateTimeInterface) {
            return $visitedAt->format('Y-m-d');
        }

        if (is_string($visitedAt) && $visitedAt !== '') {
            return substr($visitedAt, 0, 10);
        }

        return '';
    }

    private function maybePurgeOld(): void
    {
        $lastPurge = (int)\COption::GetOptionString('prognos9ys.main', self::PURGE_OPTION, '0');
        if (time() - $lastPurge < self::PURGE_INTERVAL_SEC) {
            return;
        }

        $threshold = (new DateTime())->add('-' . self::RETENTION_DAYS . ' days');
        $this->repository->deleteScreenVisitLogsBefore($threshold);
        \COption::SetOptionString('prognos9ys.main', self::PURGE_OPTION, (string)time());
    }
}
