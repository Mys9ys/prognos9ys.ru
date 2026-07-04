<?php

namespace Prognos9ys\Main\Service\Game;

use Bitrix\Main\Loader;
use Bitrix\Main\Type\DateTime;
use Prognos9ys\Main\Model\Repository\AlbumRepository;
use Prognos9ys\Main\Model\Repository\FootballResultsRepository;
use Prognos9ys\Main\Model\Repository\GameEconomyRepository;
use Prognos9ys\Main\Model\Repository\ProfessionRepository;

class AchievementService
{
    private GameEconomyRepository $repository;
    private GameEventScopeService $scopeService;
    private WalletService $walletService;
    private TreasureService $treasureService;
    private ProfessionRepository $professionRepository;
    private AlbumRepository $albumRepository;

    /** @var array<int, array<string, int|float>>|null */
    private static ?array $batchStatsCache = null;

    /** @var array<int, array<string, array{claimed_threshold:int,id?:int}>>|null */
    private static ?array $allClaimMapsCache = null;

    public function __construct(
        ?GameEconomyRepository $repository = null,
        ?GameEventScopeService $scopeService = null
    ) {
        $this->repository = $repository ?? new GameEconomyRepository();
        $this->scopeService = $scopeService ?? new GameEventScopeService();
        $this->walletService = new WalletService($this->repository);
        $this->treasureService = new TreasureService($this->repository);
        $this->professionRepository = new ProfessionRepository();
        $this->albumRepository = new AlbumRepository();
    }

    public function getForUser(int $userId): array
    {
        if ($userId <= 0) {
            return [
                'stats' => [],
                'items' => [],
                'unlocked_count' => 0,
                'total_count' => 0,
            ];
        }

        $stats = $this->collectStats($userId);
        $claimMap = $this->repository->getAchievementClaimMapForUser($userId);
        $items = [];
        $unlockedCount = 0;

        foreach (AchievementConfig::getCatalog() as $code => $definition) {
            $progress = $this->resolveProgress($definition, $stats);
            $levels = (array)($definition['levels'] ?? []);
            $claimedThreshold = (int)($claimMap[$code]['claimed_threshold'] ?? 0);

            $maxUnlocked = 0;
            foreach ($levels as $level) {
                $t = (int)($level['threshold'] ?? 0);
                if ($t > 0 && $progress >= $t && $t > $maxUnlocked) {
                    $maxUnlocked = $t;
                }
            }

            $nextClaimableThreshold = 0;
            $nextReward = null;
            foreach ($levels as $level) {
                $t = (int)($level['threshold'] ?? 0);
                if ($t > 0 && $t > $claimedThreshold && $progress >= $t) {
                    $nextClaimableThreshold = $t;
                    $nextReward = $level['reward'] ?? null;
                    break;
                }
            }

            $items[] = [
                'code' => $code,
                'title' => $definition['title'],
                'description' => $definition['description'],
                'group' => $definition['group'],
                'icon' => (string)($definition['icon'] ?? ''),
                'progress' => $progress,
                'levels' => $levels,
                'claimed_threshold' => $claimedThreshold,
                'max_unlocked_threshold' => $maxUnlocked,
                'next_claimable_threshold' => $nextClaimableThreshold,
                'next_reward' => $nextReward,
                'profession_stage' => (int)($definition['profession_stage'] ?? 0),
            ];

            if ($maxUnlocked > 0) {
                $unlockedCount++;
            }
        }

        return [
            'stats' => $stats,
            'items' => $items,
            'unlocked_count' => $unlockedCount,
            'total_count' => count($items),
        ];
    }

    /**
     * Список ачивок, у которых есть незабранная награда (по одному уровню на ачивку).
     *
     * @return array<int, array{code:string,threshold:int,reward:array|null,title:string}>
     */
    public function getClaimableItems(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $claimable = [];
        foreach ($this->getForUser($userId)['items'] ?? [] as $item) {
            $threshold = (int)($item['next_claimable_threshold'] ?? 0);
            if ($threshold <= 0) {
                continue;
            }

            $claimable[] = [
                'code' => (string)($item['code'] ?? ''),
                'title' => (string)($item['title'] ?? ''),
                'threshold' => $threshold,
                'reward' => $item['next_reward'] ?? null,
            ];
        }

        return $claimable;
    }

    /** Количество ачивок с незабранной наградой (хотя бы один уровень). */
    public function countClaimableAchievements(int $userId): int
    {
        $map = $this->getClaimableCountMapForUsers([$userId]);

        return (int)($map[$userId] ?? 0);
    }

    /**
     * Пакетный подсчёт незабранных ачивок (без N+1 запросов на пользователя).
     *
     * @param list<int> $userIds
     * @return array<int, int> userId => count (>0 only)
     */
    public function getClaimableCountMapForUsers(array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        $restrict = $userIds ? array_fill_keys($userIds, true) : null;

        $statsByUser = $this->buildBatchStatsMaps();
        $claimMaps = $this->getAllClaimMapsCached();

        $targetUserIds = $userIds;
        if (!$targetUserIds) {
            $targetUserIds = array_values(array_unique(array_merge(
                array_keys($statsByUser),
                array_keys($claimMaps)
            )));
        }

        $result = [];
        foreach ($targetUserIds as $userId) {
            if ($restrict !== null && !isset($restrict[$userId])) {
                continue;
            }

            $stats = array_merge(
                $this->emptyStatsTemplate(),
                $statsByUser[$userId] ?? []
            );
            $claimMap = $claimMaps[$userId] ?? [];
            $count = $this->countClaimableFromStatsAndClaims($stats, $claimMap);
            if ($count > 0) {
                $result[$userId] = $count;
            }
        }

        return $result;
    }

    /**
     * @param array<string, int|float> $stats
     * @param array<string, array{claimed_threshold:int}> $claimMap
     */
    public function countClaimableFromStatsAndClaims(array $stats, array $claimMap): int
    {
        $count = 0;

        foreach (AchievementConfig::getCatalog() as $code => $definition) {
            $progress = $this->resolveProgress($definition, $stats);
            $claimedThreshold = (int)($claimMap[$code]['claimed_threshold'] ?? 0);

            foreach ((array)($definition['levels'] ?? []) as $level) {
                $threshold = (int)($level['threshold'] ?? 0);
                if ($threshold > 0 && $threshold > $claimedThreshold && $progress >= $threshold) {
                    $count++;
                    break;
                }
            }
        }

        return $count;
    }

    /**
     * @return array<int, array<string, int|float>>
     */
    private function buildBatchStatsMaps(): array
    {
        if (self::$batchStatsCache !== null) {
            return self::$batchStatsCache;
        }

        $maps = [];

        foreach ($this->buildPrognosisCountMap() as $userId => $row) {
            $maps[$userId] = array_merge($maps[$userId] ?? $this->emptyStatsTemplate(), $row);
        }

        $eventId = $this->getAchievementEventId();
        if ($eventId > 0) {
            $resultsRepo = new FootballResultsRepository();
            foreach ($resultsRepo->aggregateAchievementStatsByUserForEvent($eventId) as $userId => $row) {
                $maps[$userId] = array_merge($maps[$userId] ?? $this->emptyStatsTemplate(), $row);
            }
        }

        foreach ($this->repository->getAchievementEconomyStatsMapForAllUsers() as $userId => $row) {
            $maps[$userId] = array_merge($maps[$userId] ?? $this->emptyStatsTemplate(), [
                'bet_winnings_prognobaks' => (int)($row['bet_winnings_prognobaks'] ?? 0),
                'rublius_earned' => (int)($row['rublius_earned'] ?? 0),
                'chests_opened' => (int)($row['chests_opened'] ?? 0),
                'chests_earned' => (int)($row['chests_earned'] ?? 0),
            ]);
        }

        foreach ($this->repository->getXpBankDrinkStatsMapForAllUsers() as $userId => $row) {
            $maps[$userId] = array_merge($maps[$userId] ?? $this->emptyStatsTemplate(), $row);
        }

        foreach ($this->repository->getExchangeBuyStatsMapForAllUsers() as $userId => $row) {
            $maps[$userId] = array_merge($maps[$userId] ?? $this->emptyStatsTemplate(), $row);
        }

        foreach ($this->repository->getProductionAchievementStatsMapForAllUsers() as $userId => $row) {
            $maps[$userId] = array_merge($maps[$userId] ?? $this->emptyStatsTemplate(), $row);
        }

        foreach ($this->professionRepository->getYieldStatsMapForAllUsers() as $userId => $row) {
            $maps[$userId] = array_merge($maps[$userId] ?? $this->emptyStatsTemplate(), $row);
        }

        self::$batchStatsCache = $maps;

        return $maps;
    }

    /**
     * @return array<int, array<string, array{claimed_threshold:int,id?:int}>>
     */
    private function getAllClaimMapsCached(): array
    {
        if (self::$allClaimMapsCache === null) {
            self::$allClaimMapsCache = $this->repository->getAllAchievementClaimMaps();
        }

        return self::$allClaimMapsCache;
    }

    /**
     * @return array<int, array{football_prognosis:int,chm_prognosis:int}>
     */
    private function buildPrognosisCountMap(): array
    {
        if (!Loader::includeModule('iblock')) {
            return [];
        }

        $prognosisIbId = $this->getPrognosisIblockId();
        $eventIds = $this->scopeService->getEligibleEventIds();
        $anchorEventId = $this->getAchievementEventId();
        if ($prognosisIbId <= 0 || !$eventIds) {
            return [];
        }

        $filter = [
            'IBLOCK_ID' => $prognosisIbId,
        ];
        if (count($eventIds) === 1) {
            $filter['PROPERTY_events'] = $eventIds[0];
        } else {
            $or = ['LOGIC' => 'OR'];
            foreach ($eventIds as $eventId) {
                $or[] = ['PROPERTY_events' => $eventId];
            }
            $filter[] = $or;
        }

        $map = [];
        $response = \CIBlockElement::GetList(
            [],
            $filter,
            false,
            false,
            ['PROPERTY_user_id', 'PROPERTY_events']
        );

        while ($row = $response->Fetch()) {
            $userId = (int)($row['PROPERTY_USER_ID_VALUE'] ?? 0);
            if ($userId <= 0) {
                continue;
            }

            if (!isset($map[$userId])) {
                $map[$userId] = ['football_prognosis' => 0, 'chm_prognosis' => 0];
            }

            $map[$userId]['football_prognosis']++;
            if ($anchorEventId > 0 && (int)($row['PROPERTY_EVENTS_VALUE'] ?? 0) === $anchorEventId) {
                $map[$userId]['chm_prognosis']++;
            }
        }

        return $map;
    }

    /**
     * @return array<string, int|float>
     */
    private function emptyStatsTemplate(): array
    {
        return array_merge([
            'football_prognosis' => 0,
            'chm_prognosis' => 0,
            'score_30_39' => 0,
            'score_40_plus' => 0,
            'score_0' => 0,
            'bet_winnings_prognobaks' => 0,
            'chests_opened' => 0,
            'chests_earned' => 0,
            'rublius_earned' => 0,
            'metric_exact_score' => 0,
            'metric_outcome' => 0,
            'metric_total_goals' => 0,
            'metric_goal_diff' => 0,
            'metric_corners' => 0,
            'metric_yellow' => 0,
            'metric_possession' => 0,
            'rare_red' => 0,
            'rare_penalty' => 0,
            'wow_red' => 0,
            'wow_pen' => 0,
            'metric_extra_time' => 0,
            'metric_shootout' => 0,
        ], XpBankAchievementConfig::emptyStatsTemplate(), ExchangeBuyAchievementConfig::emptyStatsTemplate(), RecipeAchievementConfig::emptyStatsTemplate(), ProductionAchievementConfig::emptyStatsTemplate());
    }

    /**
     * Забрать все доступные награды (по каждой ачивке — следующий уровень, затем повтор).
     *
     * @return array<int, array{code:string,threshold:int,reward:array}>
     */
    public function claimAllAvailable(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $granted = [];
        $guard = 0;
        $maxIterations = 500;

        while ($guard < $maxIterations) {
            $guard++;
            $claimable = $this->getClaimableItems($userId);
            if (!$claimable) {
                break;
            }

            $madeProgress = false;
            foreach ($claimable as $item) {
                $code = (string)($item['code'] ?? '');
                if ($code === '') {
                    continue;
                }

                try {
                    $claimed = $this->claimNext($userId, $code);
                } catch (\Throwable $exception) {
                    $this->logClaimEvent($userId, 'claim_error', [
                        'code' => $code,
                        'error' => $exception->getMessage(),
                    ]);
                    continue;
                }

                if ($claimed) {
                    $granted[] = $claimed;
                    $madeProgress = true;
                }
            }

            if (!$madeProgress) {
                break;
            }
        }

        return $granted;
    }

    /**
     * После пересчёта результата матча: проверить ачивки у игроков этого матча.
     * Прогресс считается из iblock result на лету — отдельно не хранится.
     *
     * @return array{
     *   match_id:int,
     *   users:array<int, array{user_id:int,claimable:array,granted:array}>
     * }
     */
    public function syncAfterMatch(int $matchId, bool $autoClaim = false): array
    {
        $result = [
            'match_id' => $matchId,
            'users' => [],
        ];

        if ($matchId <= 0 || !Loader::includeModule('iblock')) {
            return $result;
        }

        if (!$this->scopeService->isMatchEligible($matchId)) {
            return $result;
        }

        $userIds = $this->getUserIdsWithResultOnMatch($matchId);
        foreach ($userIds as $userId) {
            $claimable = $this->getClaimableItems($userId);
            $granted = [];

            if ($autoClaim && $claimable) {
                $granted = $this->claimAllAvailable($userId);
            } elseif ($claimable) {
                $this->logClaimEvent($userId, 'claimable', [
                    'match_id' => $matchId,
                    'items' => $claimable,
                ]);
            }

            if ($claimable || $granted) {
                $result['users'][$userId] = [
                    'user_id' => $userId,
                    'claimable' => $claimable,
                    'granted' => $granted,
                ];
            }
        }

        if ($result['users']) {
            $this->logClaimEvent(0, 'sync_after_match', $result);
        }

        return $result;
    }

    /**
     * @return array<int, int>
     */
    private function getUserIdsWithResultOnMatch(int $matchId): array
    {
        $userIds = [];
        $response = \CIBlockElement::GetList(
            [],
            [
                'IBLOCK_ID' => 7,
                'PROPERTY_match_id' => $matchId,
            ],
            false,
            false,
            ['PROPERTY_user_id']
        );

        while ($row = $response->GetNext()) {
            $userId = (int)($row['PROPERTY_USER_ID_VALUE'] ?? 0);
            if ($userId > 0) {
                $userIds[$userId] = $userId;
            }
        }

        return array_values($userIds);
    }

    /**
     * Выдать пропущенные награды за уже достигнутый прогресс (идемпотентно).
     *
     * @return array<int, array{code:string,threshold:int,reward:array}>
     */
    public function grantMissedRewards(int $userId): array
    {
        return $this->claimAllAvailable($userId);
    }

    private function logClaimEvent(int $userId, string $event, array $payload): void
    {
        if (!GameEconomyConfig::isAchievementClaimDebugEnabled()) {
            return;
        }

        error_log(sprintf(
            '[AchievementClaim] user=%d event=%s %s',
            $userId,
            $event,
            json_encode($payload, JSON_UNESCAPED_UNICODE)
        ));
    }

    /**
     * @return array{code:string,threshold:int,reward:array}|null
     */
    public function claimNext(int $userId, string $code): ?array
    {
        $code = trim($code);
        if ($userId <= 0 || $code === '') {
            return null;
        }

        $catalog = AchievementConfig::getCatalog();
        if (!isset($catalog[$code])) {
            throw new \RuntimeException('Ачивка не найдена');
        }

        $definition = $catalog[$code];
        $levels = (array)($definition['levels'] ?? []);
        if (!$levels) {
            throw new \RuntimeException('У ачивки нет уровней');
        }

        $stats = $this->collectStats($userId);
        $progress = $this->resolveProgress($definition, $stats);

        $claimMap = $this->repository->getAchievementClaimMapForUser($userId);
        $claimedThreshold = (int)($claimMap[$code]['claimed_threshold'] ?? 0);

        $targetThreshold = 0;
        $reward = null;
        foreach ($levels as $level) {
            $t = (int)($level['threshold'] ?? 0);
            if ($t <= 0) {
                continue;
            }
            if ($t > $claimedThreshold && $progress >= $t) {
                $targetThreshold = $t;
                $reward = $level['reward'] ?? null;
                break;
            }
        }

        if ($targetThreshold <= 0) {
            throw new \RuntimeException('Пока нечего забирать');
        }
        if ($reward === null) {
            throw new \RuntimeException('Награда для этого уровня будет добавлена позже');
        }

        $now = new DateTime();
        $this->repository->upsertAchievementClaim($userId, $code, $targetThreshold, [
            'UF_UPDATED_AT' => $now,
            'UF_CREATED_AT' => $now,
        ]);

        $given = [
            'prognobaks' => 0.0,
            'rublius' => 0.0,
            'chests' => 0,
            'pennant' => null,
            'materials' => [],
        ];

        $reward = is_array($reward) ? $reward : [];

        $prognobaks = (float)($reward['prognobaks'] ?? 0);
        if ($prognobaks > 0) {
            $this->walletService->credit(
                $userId,
                GameEconomyConfig::CURRENCY_PROGNOBAKS,
                $prognobaks,
                'achievement_' . $code . '_' . $targetThreshold,
                'achievement',
                $targetThreshold
            );
            $given['prognobaks'] = $prognobaks;
        }

        $rublius = (float)($reward['rublius'] ?? 0);
        if ($rublius > 0) {
            $this->walletService->credit(
                $userId,
                GameEconomyConfig::CURRENCY_RUBLIUS,
                $rublius,
                'achievement_' . $code . '_' . $targetThreshold,
                'achievement',
                $targetThreshold
            );
            $given['rublius'] = $rublius;
        }

        $chestPacks = $reward['chest_packs'] ?? [];
        if (is_array($chestPacks) && $chestPacks !== []) {
            $grantedPacks = [];
            $totalChests = 0;
            foreach ($chestPacks as $packIndex => $pack) {
                if (!is_array($pack)) {
                    continue;
                }
                $packCount = (int)($pack['count'] ?? 0);
                $packType = (string)($pack['type'] ?? '');
                if ($packCount <= 0 || $packType === '') {
                    continue;
                }

                $granted = $this->grantAchievementChestPack(
                    $userId,
                    $code,
                    $targetThreshold,
                    $definition,
                    $reward,
                    $packCount,
                    $packType,
                    (int)$packIndex
                );
                if ($granted) {
                    $grantedPacks[] = [
                        'type' => $packType,
                        'count' => $packCount,
                    ];
                    $totalChests += $packCount;
                }
            }

            if ($grantedPacks) {
                $given['chest_packs'] = $grantedPacks;
                $given['chests'] = $totalChests;
            }
        } else {
            $chests = (int)($reward['chests'] ?? 0);
            if ($chests > 0) {
                $granted = $this->grantAchievementChestPack(
                    $userId,
                    $code,
                    $targetThreshold,
                    $definition,
                    $reward,
                    $chests,
                    (string)($reward['chest_type'] ?? ''),
                    0
                );
                $given['chests'] = $granted ? $chests : 0;
                $given['chest_type'] = (string)($reward['chest_type'] ?? 'achievement');
            }
        }

        $materials = $reward['materials'] ?? [];
        if (is_array($materials)) {
            foreach ($materials as $material) {
                if (!is_array($material)) {
                    continue;
                }
                $materialCode = (string)($material['code'] ?? '');
                $qty = (int)($material['qty'] ?? 0);
                $isPremium = !empty($material['is_premium']);
                if ($materialCode === '' || $qty <= 0) {
                    continue;
                }
                $this->professionRepository->addUserMaterialQty($userId, $materialCode, $qty, $isPremium);
                $given['materials'][] = [
                    'code' => $materialCode,
                    'qty' => $qty,
                    'is_premium' => $isPremium,
                ];
            }
        }

        $pennant = (string)($reward['pennant'] ?? '');
        if ($pennant !== '') {
            $grantedPennant = $this->treasureService->grantPennant($userId, $pennant);
            if ($grantedPennant) {
                $given['pennant'] = $pennant;
            }
        }

        $result = [
            'code' => $code,
            'threshold' => $targetThreshold,
            'reward' => $given,
        ];

        $this->logClaimEvent($userId, 'granted', $result);

        return $result;
    }

    private function grantAchievementChestPack(
        int $userId,
        string $code,
        int $targetThreshold,
        array $definition,
        array $reward,
        int $chests,
        string $chestType,
        int $packIndex
    ): bool {
        if ($chests <= 0) {
            return false;
        }

        $isProfessionGroup = ($definition['group'] ?? '') === ProfessionAchievementConfig::GROUP
            || ($definition['group'] ?? '') === AchievementConfig::GROUP_PRODUCTION;

        if (AchievementConfig::grantsWc26Chest($code)
            || $chestType === 'wc26'
            || (($reward['chest_type'] ?? '') === 'wc26' && $packIndex === 0)) {
            return $this->treasureService->grantWc26AchievementChests(
                $userId,
                $code,
                $targetThreshold,
                $chests
            );
        }

        if ($isProfessionGroup
            || AchievementConfig::grantsProfessionChest($code)
            || in_array($chestType, [
                'profession',
                TreasureService::CHEST_TYPE_PROFESSION_TIER_1,
                TreasureService::CHEST_TYPE_PROFESSION_TIER_2,
                TreasureService::CHEST_TYPE_PROFESSION_TIER_3,
            ], true)
            || in_array((string)($reward['chest_type'] ?? ''), [
                'profession',
                TreasureService::CHEST_TYPE_PROFESSION_TIER_1,
                TreasureService::CHEST_TYPE_PROFESSION_TIER_2,
                TreasureService::CHEST_TYPE_PROFESSION_TIER_3,
            ], true)) {
            return $this->treasureService->grantProfessionAchievementChests(
                $userId,
                $code,
                $targetThreshold,
                $chests,
                $chestType
            );
        }

        return $this->treasureService->grantAchievementChests(
            $userId,
            $code,
            $targetThreshold,
            $chests
        );
    }

    private function collectStats(int $userId): array
    {
        $wc26Prognosis = $this->countWc26Prognosis($userId);
        $footballPrognosis = $this->countFootballPrognosis($userId);
        $scores = $this->countScoreBuckets($userId);
        $metrics = $this->countMetricStats($userId);

        return array_merge([
            'football_prognosis' => $footballPrognosis,
            'chm_prognosis' => $wc26Prognosis,
            'score_30_39' => $scores['score_30_39'],
            'score_40_plus' => $scores['score_40_plus'],
            'score_0' => $scores['score_0'],
            'bet_winnings_prognobaks' => (int)round($this->repository->sumMatchBetPayoutPrognobaksForUser($userId)),
            'chests_opened' => $this->repository->sumOpenedTreasureChestsForUser($userId),
            'chests_earned' => $this->repository->sumEarnedTreasureChestsForUser($userId),
            'rublius_earned' => (int)round($this->repository->sumRubliusEarnedForUser($userId)),
            'album_pennant_glued' => $this->albumRepository->countGluedByUserAndCollection(
                $userId,
                AlbumConfig::COLLECTION_PENNANT_WC26
            ),
            'album_scarf_glued' => $this->albumRepository->countGluedByUserAndCollection(
                $userId,
                AlbumConfig::COLLECTION_SCARF_WC26
            ),
            'album_achievement_pennant_glued' => $this->albumRepository->countGluedByUserAndCollection(
                $userId,
                AlbumConfig::COLLECTION_PENNANT_ACHIEVEMENT
            ),
        ], $metrics, $this->professionRepository->getYieldStatsByUserId($userId), $this->repository->getXpBankDrinkStatsForUser($userId), $this->repository->getExchangeBuyStatsForUser($userId), $this->repository->getProductionAchievementStatsForUser($userId));
    }

    private function resolveProgress(array $definition, array $stats): int
    {
        $statKey = (string)($definition['stat'] ?? '');
        if ($statKey === '' || !isset($stats[$statKey])) {
            return 0;
        }

        return (int)$stats[$statKey];
    }

    private function getAchievementEventId(): int
    {
        return $this->scopeService->getAnchorEventId();
    }

    /** Прогнозы только на матчи ЧМ-2026 (якорное событие). */
    private function countWc26Prognosis(int $userId): int
    {
        $prognosisIbId = $this->getPrognosisIblockId();
        $eventId = $this->getAchievementEventId();
        if ($prognosisIbId <= 0 || $eventId <= 0) {
            return 0;
        }

        return (int)\CIBlockElement::GetList(
            [],
            [
                'IBLOCK_ID' => $prognosisIbId,
                'PROPERTY_user_id' => $userId,
                'PROPERTY_events' => $eventId,
            ],
            []
        );
    }

    /** Прогнозы на все футбольные события в игровой экономике (с 10.06.2026). */
    private function countFootballPrognosis(int $userId): int
    {
        $prognosisIbId = $this->getPrognosisIblockId();
        $eventIds = $this->scopeService->getEligibleEventIds();
        if ($prognosisIbId <= 0 || !$eventIds) {
            return 0;
        }

        $filter = [
            'IBLOCK_ID' => $prognosisIbId,
            'PROPERTY_user_id' => $userId,
        ];

        if (count($eventIds) === 1) {
            $filter['PROPERTY_events'] = $eventIds[0];
        } else {
            $or = ['LOGIC' => 'OR'];
            foreach ($eventIds as $eventId) {
                $or[] = ['PROPERTY_events' => $eventId];
            }
            $filter[] = $or;
        }

        return (int)\CIBlockElement::GetList([], $filter, []);
    }

    /**
     * @return array{IBLOCK_ID:int,PROPERTY_user_id:int,PROPERTY_events:int}|null
     */
    private function buildWc26ResultFilter(int $userId): ?array
    {
        $eventId = $this->getAchievementEventId();
        if ($userId <= 0 || $eventId <= 0) {
            return null;
        }

        return [
            'IBLOCK_ID' => 7,
            'PROPERTY_user_id' => $userId,
            'PROPERTY_events' => $eventId,
        ];
    }

    private function getPrognosisIblockId(): int
    {
        if (!Loader::includeModule('iblock')) {
            return 0;
        }

        $id = (int)(\CIBlock::GetList([], ['CODE' => 'prognosis'], false)->Fetch()['ID'] ?? 0);
        if ($id > 0) {
            return $id;
        }

        // как в CalcFootballPrognosisResult
        return 6;
    }

    /**
     * @return array{score_30_39:int,score_40_plus:int,score_0:int}
     */
    private function countScoreBuckets(int $userId): array
    {
        if ($userId <= 0 || !Loader::includeModule('iblock')) {
            return ['score_30_39' => 0, 'score_40_plus' => 0, 'score_0' => 0];
        }

        $filter = $this->buildWc26ResultFilter($userId);
        if ($filter === null) {
            return ['score_30_39' => 0, 'score_40_plus' => 0, 'score_0' => 0];
        }

        $score3039 = 0;
        $score40 = 0;
        $score0 = 0;

        $res = \CIBlockElement::GetList(
            ['ID' => 'DESC'],
            $filter,
            false,
            false,
            ['ID', 'PROPERTY_user_id', 'PROPERTY_all']
        );

        while ($row = $res->Fetch()) {
            $all = (float)($row['PROPERTY_ALL_VALUE'] ?? 0);
            if ($all >= 40) {
                $score40++;
            } elseif ($all >= 30) {
                $score3039++;
            } elseif ($all == 0.0) {
                $score0++;
            }
        }

        return [
            'score_30_39' => $score3039,
            'score_40_plus' => $score40,
            'score_0' => $score0,
        ];
    }

    /**
     * Агрегация по iblock result (id=7), поля как в CalcFootballPrognosisResult.
     *
     * @return array<string, int>
     */
    private function countMetricStats(int $userId): array
    {
        $stats = [
            'metric_exact_score' => 0,
            'metric_outcome' => 0,
            'metric_total_goals' => 0,
            'metric_goal_diff' => 0,
            'metric_corners' => 0,
            'metric_yellow' => 0,
            'metric_possession' => 0,
            'rare_red' => 0,
            'rare_penalty' => 0,
            'wow_red' => 0,
            'wow_pen' => 0,
            'metric_extra_time' => 0,
            'metric_shootout' => 0,
        ];

        if ($userId <= 0 || !Loader::includeModule('iblock')) {
            return $stats;
        }

        $filter = $this->buildWc26ResultFilter($userId);
        if ($filter === null) {
            return $stats;
        }

        $res = \CIBlockElement::GetList(
            ['ID' => 'ASC'],
            $filter,
            false,
            false,
            [
                'ID',
                'PROPERTY_score',
                'PROPERTY_result',
                'PROPERTY_diff',
                'PROPERTY_sum',
                'PROPERTY_domination',
                'PROPERTY_yellow',
                'PROPERTY_red',
                'PROPERTY_corner',
                'PROPERTY_penalty',
                'PROPERTY_otime',
                'PROPERTY_spenalty',
            ]
        );

        while ($row = $res->Fetch()) {
            // В iblock result (id=33) точный счёт хранится в свойстве CODE=score, не goals.
            $exactScore = (float)($row['PROPERTY_SCORE_VALUE'] ?? 0);
            $outcome = (float)($row['PROPERTY_RESULT_VALUE'] ?? 0);
            $diff = (float)($row['PROPERTY_DIFF_VALUE'] ?? 0);
            $sum = (float)($row['PROPERTY_SUM_VALUE'] ?? 0);
            $domination = (float)($row['PROPERTY_DOMINATION_VALUE'] ?? 0);
            $yellow = (float)($row['PROPERTY_YELLOW_VALUE'] ?? 0);
            $red = (float)($row['PROPERTY_RED_VALUE'] ?? 0);
            $corner = (float)($row['PROPERTY_CORNER_VALUE'] ?? 0);
            $penalty = (float)($row['PROPERTY_PENALTY_VALUE'] ?? 0);
            $otime = (float)($row['PROPERTY_OTIME_VALUE'] ?? 0);
            $spenalty = (float)($row['PROPERTY_SPENALTY_VALUE'] ?? 0);

            if ($exactScore >= 10) {
                $stats['metric_exact_score']++;
            }
            if ($outcome >= 5) {
                $stats['metric_outcome']++;
            }
            if ($sum >= 5) {
                $stats['metric_total_goals']++;
            }
            if ($diff >= 5) {
                $stats['metric_goal_diff']++;
            }

            $stats['metric_corners'] += (int)round($corner);
            $stats['metric_yellow'] += (int)round($yellow);
            $stats['metric_possession'] += (int)round($domination);

            // Красные/пенальти: зеркало calcRedCard() в CalcFootballPrognosisResult.
            // 0.5 — «оба 0» или оба >0 но неверное кол-во; в ачивки не идёт.
            // 5   — точно 1 в матче (факт «ДА», единственная).
            // 7+  — точное кол-во ≥ 2 («Ого …»).
            if ($this->isRareRedPenaltyFactScore($red)) {
                $stats['rare_red']++;
            }
            if ($this->isRareRedPenaltyFactScore($penalty)) {
                $stats['rare_penalty']++;
            }

            if ($this->isWowRedPenaltyScore($red)) {
                $stats['wow_red']++;
            }
            if ($this->isWowRedPenaltyScore($penalty)) {
                $stats['wow_pen']++;
            }

            if ($otime >= 5) {
                $stats['metric_extra_time']++;
            }
            if ($spenalty >= 5) {
                $stats['metric_shootout']++;
            }
        }

        return $stats;
    }

    /**
     * Точно угадана ровно 1 красная / 1 пенальти (calcRedCard: prognos === res === 1 → 5 баллов).
     */
    private function isRareRedPenaltyFactScore(float $score): bool
    {
        return abs($score - 5.0) < 0.001;
    }

    /**
     * Точно угадано кол-во ≥ 2 (calcRedCard: 5 + (res-1)*2, при res=2 → 7).
     */
    private function isWowRedPenaltyScore(float $score): bool
    {
        return $score >= 6.999;
    }
}
