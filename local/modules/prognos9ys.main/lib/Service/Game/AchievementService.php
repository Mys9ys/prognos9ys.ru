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
        }

        return [
            'stats' => $stats,
            'items' => $items,
            'unlocked_count' => 0,
            'total_count' => count($items),
        ];
    }

    /**
     * Выдать награду за следующий доступный уровень ачивки (строго по возрастанию).
     *
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
            // Пока фиксируем как “выдано” в ответе; витрину вымпелов добавим отдельным шагом.
            $given['pennant'] = $pennant;
        }

        return [
            'code' => $code,
            'threshold' => $targetThreshold,
            'reward' => $given,
        ];
    }

    private function collectStats(int $userId): array
    {
        $footballPrognosis = $this->countFootballPrognosis($userId);
        $chmPrognosis = $this->countChmPrognosis($userId);
        $scores = $this->countScoreBuckets($userId);

        return [
            'football_prognosis' => $footballPrognosis,
            'chm_prognosis' => $chmPrognosis,
            'score_30_39' => $scores['score_30_39'],
            'score_40_plus' => $scores['score_40_plus'],
            'score_0' => $scores['score_0'],
            // Метрики (7–11) подключим следующим шагом
            'metric_exact_score' => 0,
            'metric_outcome' => 0,
            'metric_total_goals' => 0,
            'metric_goal_diff' => 0,
            'metric_variable_points' => 0,
            'rare_events_yes' => 0,
            'wow_red' => 0,
            'wow_pen' => 0,
            'extra_time_or_pen_series' => 0,
        ];
    }

    private function resolveProgress(array $definition, array $stats): int
    {
        $statKey = (string)($definition['stat'] ?? '');
        if ($statKey === '' || !isset($stats[$statKey])) {
            return 0;
        }

        return (int)$stats[$statKey];
    }

    private function countFootballPrognosis(int $userId): int
    {
        $prognosisIbId = $this->getPrognosisIblockId();
        if ($prognosisIbId <= 0) {
            return 0;
        }

        return (int)\CIBlockElement::GetList(
            [],
            [
                'IBLOCK_ID' => $prognosisIbId,
                'PROPERTY_user_id' => $userId,
            ],
            []
        );
    }

    private function countChmPrognosis(int $userId): int
    {
        $prognosisIbId = $this->getPrognosisIblockId();
        $anchorEventId = $this->scopeService->getAnchorEventId();
        if ($prognosisIbId <= 0 || $anchorEventId <= 0) {
            return 0;
        }

        return (int)\CIBlockElement::GetList(
            [],
            [
                'IBLOCK_ID' => $prognosisIbId,
                'PROPERTY_user_id' => $userId,
                'PROPERTY_events' => $anchorEventId,
            ],
            []
        );
    }

    private function getPrognosisIblockId(): int
    {
        if (!Loader::includeModule('iblock')) {
            return 0;
        }

        return (int)(\CIBlock::GetList([], ['CODE' => 'prognosis'], false)->Fetch()['ID'] ?? 0);
    }

    /**
     * @return array{score_30_39:int,score_40_plus:int,score_0:int}
     */
    private function countScoreBuckets(int $userId): array
    {
        if ($userId <= 0 || !Loader::includeModule('iblock')) {
            return ['score_30_39' => 0, 'score_40_plus' => 0, 'score_0' => 0];
        }

        $resultIbId = 7; // как в CalcFootballPrognosisResult
        $score3039 = 0;
        $score40 = 0;
        $score0 = 0;

        $res = \CIBlockElement::GetList(
            ['ID' => 'DESC'],
            [
                'IBLOCK_ID' => $resultIbId,
                'PROPERTY_user_id' => $userId,
            ],
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
}
