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
    private const MAX_USER_NAME_LEN = 100;

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
        $userName = $isGuest ? '' : $this->resolveUserDisplayName($userId);

        $this->repository->addScreenVisitLog([
            'UF_SCREEN' => $screen,
            'UF_IS_GUEST' => $isGuest ? 'Y' : 'N',
            'UF_USER_ID' => $isGuest ? 0 : $userId,
            'UF_USER_NAME' => $userName,
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
     *     unique_users:int,
     *     players:array<int,array{user_id:int,user_name:string}>
     *   }>,
     *   top_screens:array<int,array{screen:string,visits:int,guest_visits:int,user_visits:int}>,
     *   top_players:array<int,array{user_id:int,user_name:string,visits:int,last_seen:string}>,
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

        /** @var array<string, array{visits:int,guest_ips:array<string,bool>,user_ids:array<int,bool>,players:array<int,array{user_id:int,user_name:string}>}> */
        $daily = [];

        /** @var array<string, array{visits:int,guest_visits:int,user_visits:int}> */
        $screens = [];

        /** @var array<int, array{user_name:string,visits:int,last_seen:string}> */
        $players = [];

        /** @var array<int, bool> */
        $missingNameUserIds = [];

        /** @var array<string, int> */
        $devices = [];

        foreach ($rows as $row) {
            $isGuest = ($row['UF_IS_GUEST'] ?? 'N') === 'Y';
            $screen = (string)($row['UF_SCREEN'] ?? '');
            $ip = (string)($row['UF_IP'] ?? '');
            $userId = (int)($row['UF_USER_ID'] ?? 0);
            $userName = trim((string)($row['UF_USER_NAME'] ?? ''));
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
                    'players' => [],
                ];
            }
            $daily[$dateKey]['visits']++;
            if ($isGuest && $ip !== '') {
                $daily[$dateKey]['guest_ips'][$ip] = true;
            } elseif (!$isGuest && $userId > 0) {
                $daily[$dateKey]['user_ids'][$userId] = true;
                if (!isset($daily[$dateKey]['players'][$userId])) {
                    $daily[$dateKey]['players'][$userId] = [
                        'user_id' => $userId,
                        'user_name' => $userName,
                    ];
                } elseif ($userName !== '' && $daily[$dateKey]['players'][$userId]['user_name'] === '') {
                    $daily[$dateKey]['players'][$userId]['user_name'] = $userName;
                }

                if (!isset($players[$userId])) {
                    $players[$userId] = [
                        'user_name' => $userName,
                        'visits' => 0,
                        'last_seen' => '',
                    ];
                } elseif ($userName !== '' && $players[$userId]['user_name'] === '') {
                    $players[$userId]['user_name'] = $userName;
                }
                $players[$userId]['visits']++;
                $seenAt = $this->formatDateTimeKey($visitedAt);
                if ($seenAt !== '' && ($players[$userId]['last_seen'] === '' || $seenAt > $players[$userId]['last_seen'])) {
                    $players[$userId]['last_seen'] = $seenAt;
                }

                if ($userName === '') {
                    $missingNameUserIds[$userId] = true;
                }
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

        if ($missingNameUserIds) {
            $resolvedNames = $this->resolveUserDisplayNames(array_keys($missingNameUserIds));
            foreach ($players as $userId => &$player) {
                if ($player['user_name'] === '' && isset($resolvedNames[$userId])) {
                    $player['user_name'] = $resolvedNames[$userId];
                }
            }
            unset($player);

            foreach ($daily as &$item) {
                foreach ($item['players'] as $userId => &$playerRow) {
                    if ($playerRow['user_name'] === '' && isset($resolvedNames[$userId])) {
                        $playerRow['user_name'] = $resolvedNames[$userId];
                    }
                }
                unset($playerRow);
            }
            unset($item);
        }

        ksort($daily);

        $dailyList = [];
        foreach ($daily as $date => $item) {
            $playerList = array_values($item['players']);
            usort($playerList, static function (array $a, array $b): int {
                return strcasecmp((string)($a['user_name'] ?? ''), (string)($b['user_name'] ?? ''));
            });

            $dailyList[] = [
                'date' => $date,
                'visits' => (int)$item['visits'],
                'unique_guests' => count($item['guest_ips']),
                'unique_users' => count($item['user_ids']),
                'players' => $playerList,
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

        $topPlayers = [];
        foreach ($players as $userId => $item) {
            $name = trim((string)($item['user_name'] ?? ''));
            if ($name === '') {
                $name = 'игрок #' . $userId;
            }

            $topPlayers[] = [
                'user_id' => (int)$userId,
                'user_name' => $name,
                'visits' => (int)$item['visits'],
                'last_seen' => (string)($item['last_seen'] ?? ''),
            ];
        }
        usort($topPlayers, static function (array $a, array $b): int {
            $byVisits = ($b['visits'] ?? 0) <=> ($a['visits'] ?? 0);
            if ($byVisits !== 0) {
                return $byVisits;
            }

            return strcasecmp((string)($a['user_name'] ?? ''), (string)($b['user_name'] ?? ''));
        });
        $topPlayers = array_slice($topPlayers, 0, 50);

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
            'top_players' => $topPlayers,
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

    /**
     * @param mixed $visitedAt
     */
    private function formatDateTimeKey($visitedAt): string
    {
        if ($visitedAt instanceof DateTime) {
            return $visitedAt->format('Y-m-d H:i');
        }

        if ($visitedAt instanceof \DateTimeInterface) {
            return $visitedAt->format('Y-m-d H:i');
        }

        if (is_string($visitedAt) && $visitedAt !== '') {
            return substr($visitedAt, 0, 16);
        }

        return '';
    }

    private function resolveUserDisplayName(int $userId): string
    {
        if ($userId <= 0) {
            return '';
        }

        $names = $this->resolveUserDisplayNames([$userId]);

        return $names[$userId] ?? ('игрок #' . $userId);
    }

    /**
     * @param int[] $userIds
     * @return array<int, string>
     */
    private function resolveUserDisplayNames(array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), static function (int $id): bool {
            return $id > 0;
        })));

        if (!$userIds) {
            return [];
        }

        $names = [];
        $response = \Bitrix\Main\UserTable::getList([
            'filter' => ['@ID' => $userIds],
            'select' => ['ID', 'LOGIN', 'NAME', 'LAST_NAME'],
        ]);

        while ($row = $response->fetch()) {
            $userId = (int)($row['ID'] ?? 0);
            if ($userId <= 0) {
                continue;
            }

            $name = trim((string)($row['NAME'] ?? ''));
            if ($name === '') {
                $name = trim((string)($row['LOGIN'] ?? ''));
            }
            if ($name === '') {
                $name = 'игрок #' . $userId;
            }

            $names[$userId] = $this->truncate($name, self::MAX_USER_NAME_LEN);
        }

        return $names;
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
