<?php

namespace Prognos9ys\Main\Service\Game;

use Bitrix\Main\Loader;
use Bitrix\Main\Type\DateTime;
use Prognos9ys\Main\Model\Repository\GameEconomyRepository;

class BetService
{
    private GameEconomyRepository $repository;
    private WalletService $walletService;
    private GameEventScopeService $scopeService;

    public function __construct(?GameEconomyRepository $repository = null)
    {
        $this->repository = $repository ?? new GameEconomyRepository();
        $this->walletService = new WalletService($this->repository);
        $this->scopeService = new GameEventScopeService();
    }

    public function upsertBetFromPrognosis(int $userId, array $fields): void
    {
        if ($userId <= 0 || !Loader::includeModule('iblock')) {
            return;
        }

        $matchId = (int)($fields[17] ?? 0);
        $outcome = $this->normalizeOutcome($fields[18] ?? null);

        if ($matchId <= 0 || $outcome === null) {
            return;
        }

        $matchRow = $this->loadMatchRow($matchId);
        if (!$matchRow) {
            return;
        }

        $eventId = (int)$matchRow['PROPERTY_EVENTS_VALUE'];
        $matchNumber = (int)$matchRow['PROPERTY_NUMBER_VALUE'];
        $isActive = (string)$matchRow['ACTIVE'] === 'Y';

        if (!$isActive || !$this->scopeService->isMatchInScope($eventId, $matchNumber)) {
            return;
        }

        $existing = $this->repository->getMatchBet($userId, $matchId);
        if ($existing) {
            if (($existing['UF_STATUS'] ?? '') === GameEconomyConfig::BET_STATUS_PENDING) {
                $this->repository->updateMatchBet((int)$existing['ID'], [
                    'UF_OUTCOME' => $outcome,
                    'UF_UPDATED_AT' => new DateTime(),
                ]);
            }

            return;
        }

        if (!$this->canUserAffordStake($userId)) {
            return;
        }

        $this->walletService->debit(
            $userId,
            GameEconomyConfig::CURRENCY_PROGNOBAKS,
            GameEconomyConfig::BET_STAKE_PROGNOBAKS,
            'bet_stake',
            'match',
            $matchId
        );

        $now = new DateTime();
        $this->repository->addMatchBet([
            'UF_USER_ID' => $userId,
            'UF_MATCH_ID' => $matchId,
            'UF_EVENT_ID' => $eventId,
            'UF_OUTCOME' => $outcome,
            'UF_STAKE' => GameEconomyConfig::BET_STAKE_PROGNOBAKS,
            'UF_STATUS' => GameEconomyConfig::BET_STATUS_PENDING,
            'UF_PAYOUT' => 0,
            'UF_CREATED_AT' => $now,
            'UF_UPDATED_AT' => $now,
        ]);
    }

    public function canUserAffordStake(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $wallet = $this->walletService->getWalletSummary($userId);
        $prognobaks = (float)($wallet['prognobaks'] ?? 0);

        return $prognobaks >= GameEconomyConfig::BET_STAKE_PROGNOBAKS;
    }

    /**
     * @return array<int, array{name:string,count:string|int}>
     */
    public function getMatchBetOdds(int $matchId): array
    {
        $counts = $this->getMatchBetCounts($matchId);

        return $this->buildOdds($counts['plus'], $counts['equal'], $counts['minus']);
    }

    /**
     * @return array{plus:float,equal:float,minus:float,count:int}
     */
    public function getMatchBetCounts(int $matchId): array
    {
        if ($matchId <= 0) {
            return ['plus' => 0.0, 'equal' => 0.0, 'minus' => 0.0, 'count' => 0];
        }

        $matchRow = $this->loadMatchRow($matchId);
        $isFinished = $matchRow && (string)$matchRow['ACTIVE'] === 'N';

        return $this->getMatchBetCountsForMatches([
            $matchId => ['active' => $isFinished ? 'N' : 'Y'],
        ])[$matchId] ?? ['plus' => 0.0, 'equal' => 0.0, 'minus' => 0.0, 'count' => 0];
    }

    /**
     * @param array<int, array{active: string}> $matchMeta
     * @return array<int, array{plus:float,equal:float,minus:float,count:int}>
     */
    public function getMatchBetCountsForMatches(array $matchMeta): array
    {
        $result = [];
        $finishedIds = [];
        $pendingIds = [];

        foreach ($matchMeta as $matchId => $meta) {
            $matchId = (int)$matchId;
            if ($matchId <= 0) {
                continue;
            }

            $result[$matchId] = ['plus' => 0.0, 'equal' => 0.0, 'minus' => 0.0, 'count' => 0];
            if ((string)($meta['active'] ?? '') === 'N') {
                $finishedIds[] = $matchId;
            } else {
                $pendingIds[] = $matchId;
            }
        }

        if ($finishedIds) {
            $this->accumulateBetCounts(
                $result,
                $this->repository->getBetsByMatchIds($finishedIds)
            );
        }

        if ($pendingIds) {
            $this->accumulateBetCounts(
                $result,
                $this->repository->getBetsByMatchIds(
                    $pendingIds,
                    GameEconomyConfig::BET_STATUS_PENDING
                )
            );
        }

        foreach ($result as $matchId => $counts) {
            $result[$matchId]['count'] = (int)($counts['plus'] + $counts['equal'] + $counts['minus']);
        }

        return $result;
    }

    /**
     * @param array<int, array{plus:float,equal:float,minus:float,count:int}> $result
     * @param array<int, array> $bets
     */
    private function accumulateBetCounts(array &$result, array $bets): void
    {
        foreach ($bets as $bet) {
            $matchId = (int)($bet['UF_MATCH_ID'] ?? 0);
            if ($matchId <= 0 || !isset($result[$matchId])) {
                continue;
            }

            $outcome = $this->normalizeOutcome($bet['UF_OUTCOME'] ?? null);
            if ($outcome === 'п1') {
                $result[$matchId]['plus']++;
            } elseif ($outcome === 'н') {
                $result[$matchId]['equal']++;
            } elseif ($outcome === 'п2') {
                $result[$matchId]['minus']++;
            }
        }
    }

    public function settleMatch(int $matchId): void
    {
        if ($matchId <= 0 || !Loader::includeModule('iblock')) {
            return;
        }

        $matchRow = $this->loadMatchRow($matchId);
        if (!$matchRow) {
            return;
        }

        $eventId = (int)$matchRow['PROPERTY_EVENTS_VALUE'];
        $matchNumber = (int)$matchRow['PROPERTY_NUMBER_VALUE'];
        if (!$this->scopeService->isMatchInScope($eventId, $matchNumber)) {
            return;
        }

        $officialOutcome = $this->normalizeOutcome($matchRow['PROPERTY_RESULT_VALUE'] ?? null);
        if ($officialOutcome === null) {
            return;
        }

        $bets = $this->repository->getPendingMatchBetsByMatch($matchId);
        if (!$bets) {
            return;
        }

        $pool = 0.0;
        $winners = [];
        $winnerStakeSum = 0.0;

        foreach ($bets as $bet) {
            $stake = round((float)($bet['UF_STAKE'] ?? 0), 1);
            $pool = round($pool + $stake, 1);

            if ($this->normalizeOutcome($bet['UF_OUTCOME'] ?? null) === $officialOutcome) {
                $winners[] = $bet;
                $winnerStakeSum = round($winnerStakeSum + $stake, 1);
            }
        }

        $now = new DateTime();
        $distributed = 0.0;
        $totalPayout = 0.0;
        $winnersCount = count($winners);

        if ($winnersCount > 0 && $winnerStakeSum > 0) {
            foreach ($winners as $index => $winner) {
                $stake = round((float)($winner['UF_STAKE'] ?? 0), 1);
                if ($index === $winnersCount - 1) {
                    $payout = round($pool - $distributed, 1);
                } else {
                    $payout = round($pool * ($stake / $winnerStakeSum), 1);
                    $distributed = round($distributed + $payout, 1);
                }

                $payout = max(0.0, $payout);
                $totalPayout = round($totalPayout + $payout, 1);

                $this->walletService->credit(
                    (int)$winner['UF_USER_ID'],
                    GameEconomyConfig::CURRENCY_PROGNOBAKS,
                    $payout,
                    'bet_payout',
                    'match',
                    $matchId
                );

                $this->repository->updateMatchBet((int)$winner['ID'], [
                    'UF_STATUS' => GameEconomyConfig::BET_STATUS_WON,
                    'UF_PAYOUT' => $payout,
                    'UF_SETTLED_AT' => $now,
                    'UF_UPDATED_AT' => $now,
                ]);
            }
        }

        foreach ($bets as $bet) {
            $betId = (int)$bet['ID'];
            $isWinner = $this->normalizeOutcome($bet['UF_OUTCOME'] ?? null) === $officialOutcome;
            if ($isWinner) {
                continue;
            }

            $this->repository->updateMatchBet($betId, [
                'UF_STATUS' => GameEconomyConfig::BET_STATUS_LOST,
                'UF_PAYOUT' => 0,
                'UF_SETTLED_AT' => $now,
                'UF_UPDATED_AT' => $now,
            ]);
        }

        $leftover = round($pool - $totalPayout, 1);
        if ($leftover > 0) {
            $this->addToBank(GameEconomyConfig::GAME_BANK_CODE_FOOTBALL_PARIMUTUEL, $leftover);
        }
    }

    /**
     * Удалить все ставки матча перед повторным пересчётом.
     */
    public function resetMatchBetsForRecalc(int $matchId): int
    {
        if ($matchId <= 0) {
            return 0;
        }

        return $this->repository->deleteMatchBetsByMatch($matchId);
    }

    /**
     * Backfill financial bets for an already finished match based on existing prognosis records.
     * Used for old matches that existed before the betting release.
     */
    public function backfillBetsFromPrognosis(int $matchId): void
    {
        if ($matchId <= 0 || !Loader::includeModule('iblock')) {
            return;
        }

        $matchRow = $this->loadMatchRow($matchId);
        if (!$matchRow) {
            return;
        }

        $eventId = (int)$matchRow['PROPERTY_EVENTS_VALUE'];
        $matchNumber = (int)$matchRow['PROPERTY_NUMBER_VALUE'];

        if (!$this->scopeService->isMatchInScope($eventId, $matchNumber)) {
            return;
        }

        // Prognosis iblock id by CODE to avoid hardcoding.
        $prognosisIbId = (int)(\CIBlock::GetList([], ['CODE' => 'prognosis'], false)->Fetch()['ID'] ?? 0);
        if ($prognosisIbId <= 0) {
            return;
        }

        $rs = \CIBlockElement::GetList(
            [],
            [
                'IBLOCK_ID' => $prognosisIbId,
                'PROPERTY_match_id' => $matchId,
            ],
            false,
            false,
            [
                'ID',
                'PROPERTY_user_id',
                'PROPERTY_diff',
                'PROPERTY_events',
                'PROPERTY_number',
            ]
        );

        $now = new DateTime();

        while ($row = $rs->GetNext()) {
            $userId = (int)($row['PROPERTY_USER_ID_VALUE'] ?? 0);
            if ($userId <= 0) {
                continue;
            }

            $this->walletService->ensureWallet($userId);

            // Skip if bet already exists for this user+match (even if not pending).
            if ($this->repository->getMatchBet($userId, $matchId)) {
                continue;
            }

            // Check user can afford stake right now.
            if (!$this->canUserAffordStake($userId)) {
                continue;
            }

            $diff = (int)($row['PROPERTY_DIFF_VALUE'] ?? 0);
            if ($diff > 0) {
                $outcome = 'п1';
            } elseif ($diff === 0) {
                $outcome = 'н';
            } else {
                $outcome = 'п2';
            }

            // Debit wallet and create pending bet.
            $this->walletService->debit(
                $userId,
                GameEconomyConfig::CURRENCY_PROGNOBAKS,
                GameEconomyConfig::BET_STAKE_PROGNOBAKS,
                'bet_stake',
                'match',
                $matchId
            );

            $this->repository->addMatchBet([
                'UF_USER_ID' => $userId,
                'UF_MATCH_ID' => $matchId,
                'UF_EVENT_ID' => $eventId,
                'UF_OUTCOME' => $outcome,
                'UF_STAKE' => GameEconomyConfig::BET_STAKE_PROGNOBAKS,
                'UF_STATUS' => GameEconomyConfig::BET_STATUS_PENDING,
                'UF_PAYOUT' => 0,
                'UF_CREATED_AT' => $now,
                'UF_UPDATED_AT' => $now,
            ]);
        }
    }

    private function loadMatchRow(int $matchId): ?array
    {
        $row = \CIBlockElement::GetList(
            [],
            [
                'IBLOCK_ID' => 2,
                'ID' => $matchId,
            ],
            false,
            false,
            ['ID', 'ACTIVE', 'PROPERTY_events', 'PROPERTY_number', 'PROPERTY_result']
        )->GetNext();

        return $row ?: null;
    }

    private function normalizeOutcome($value): ?string
    {
        $outcome = mb_strtolower(trim((string)$value));
        if (in_array($outcome, ['п1', 'н', 'п2'], true)) {
            return $outcome;
        }

        return null;
    }

    /**
     * @return array<int, array{name:string,count:string|int}>
     */
    private function buildOdds(float $plus, float $equal, float $minus): array
    {
        $count = $plus + $equal + $minus;

        return [
            0 => ['name' => 'п1', 'count' => number_format(($count + 1) / ($plus + 1), 2)],
            1 => ['name' => 'н', 'count' => number_format(($count + 1) / ($equal + 1), 2)],
            2 => ['name' => 'п2', 'count' => number_format(($count + 1) / ($minus + 1), 2)],
            3 => ['name' => 'Σ', 'count' => $count],
        ];
    }

    private function addToBank(string $code, float $amount): void
    {
        if ($amount <= 0) {
            return;
        }

        $bank = $this->repository->ensureGameBank($code);
        $newAmount = round((float)($bank['UF_PROGNOBAKS'] ?? 0) + $amount, 1);

        $this->repository->updateGameBank((int)$bank['ID'], [
            'UF_PROGNOBAKS' => $newAmount,
        ]);
    }
}
