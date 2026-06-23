<?php

namespace Prognos9ys\Main\Service\Game;

use Bitrix\Main\Loader;
use Bitrix\Main\Type\DateTime;
use Prognos9ys\Main\Model\Repository\GameEconomyRepository;

class AchievementService
{
    private GameEconomyRepository $repository;
    private GameEventScopeService $scopeService;
    private WalletService $walletService;
    private TreasureService $treasureService;

    public function __construct(
        ?GameEconomyRepository $repository = null,
        ?GameEventScopeService $scopeService = null
    ) {
        $this->repository = $repository ?? new GameEconomyRepository();
        $this->scopeService = $scopeService ?? new GameEventScopeService();
        $this->walletService = new WalletService($this->repository);
        $this->treasureService = new TreasureService($this->repository);
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
        return count($this->getClaimableItems($userId));
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
        if (!$reward) {
            throw new \RuntimeException('Награда для этого уровня будет добавлена позже');
        }

        $now = new DateTime();
        $this->repository->upsertAchievementClaim($userId, $code, $targetThreshold, [
            'UF_UPDATED_AT' => $now,
            'UF_CREATED_AT' => $now,
        ]);

        $given = [
            'rublius' => 0.0,
            'chests' => 0,
            'pennant' => null,
        ];

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

        $chests = (int)($reward['chests'] ?? 0);
        if ($chests > 0) {
            $granted = $this->treasureService->grantAchievementChests($userId, $code, $targetThreshold, $chests);
            $given['chests'] = $granted ? $chests : 0;
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

    private function collectStats(int $userId): array
    {
        $wc26Prognosis = $this->countWc26Prognosis($userId);
        $scores = $this->countScoreBuckets($userId);
        $metrics = $this->countMetricStats($userId);

        return array_merge([
            'football_prognosis' => $wc26Prognosis,
            'chm_prognosis' => $wc26Prognosis,
            'score_30_39' => $scores['score_30_39'],
            'score_40_plus' => $scores['score_40_plus'],
            'score_0' => $scores['score_0'],
        ], $metrics);
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
                'PROPERTY_goals',
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
            $goals = (float)($row['PROPERTY_GOALS_VALUE'] ?? 0);
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

            if ($goals >= 10) {
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
