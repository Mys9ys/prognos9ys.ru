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

    /**
     * @return array{pending:int,winners:int,losers:int,total_payout:float,official_outcome:string}
     */
    public function settleMatch(int $matchId): array
    {
        $report = [
            'pending' => 0,
            'winners' => 0,
            'losers' => 0,
            'total_payout' => 0.0,
            'official_outcome' => '',
        ];

        if ($matchId <= 0 || !Loader::includeModule('iblock')) {
            return $report;
        }

        $matchRow = $this->loadMatchRow($matchId);
        if (!$matchRow) {
            return $report;
        }

        $eventId = (int)$matchRow['PROPERTY_EVENTS_VALUE'];
        $matchNumber = (int)$matchRow['PROPERTY_NUMBER_VALUE'];
        if (!$this->scopeService->isMatchInScope($eventId, $matchNumber)) {
            return $report;
        }

        $officialOutcome = $this->resolveOfficialOutcome($matchRow);
        if ($officialOutcome === null) {
            return $report;
        }

        $report['official_outcome'] = $officialOutcome;

        $bets = $this->repository->getPendingMatchBetsByMatch($matchId);
        if (!$bets) {
            return $report;
        }

        $report['pending'] = count($bets);

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
            $report['losers']++;
        }

        $leftover = round($pool - $totalPayout, 1);
        if ($leftover > 0) {
            $this->addToBank(GameEconomyConfig::GAME_BANK_CODE_FOOTBALL_PARIMUTUEL, $leftover);
        }

        $report['winners'] = $winnersCount;
        $report['total_payout'] = $totalPayout;

        return $report;
    }

    /**
     * Удалить все ставки матча перед повторным пересчётом.
     * Сначала откатывает списания/выплаты по кошельку, чтобы пересчёт был идемпотентным.
     */
    public function resetMatchBetsForRecalc(int $matchId): int
    {
        if ($matchId <= 0) {
            return 0;
        }

        foreach ($this->repository->getMatchBetsByMatch($matchId) as $bet) {
            $this->reverseBetWalletEffects($bet, $matchId);
        }

        return $this->repository->deleteMatchBetsByMatch($matchId);
    }

    /**
     * @return array{created:int,skipped_afford:int,skipped_outcome:int,skipped_exists:int,errors:int}
     */
    public function backfillBetsFromPrognosis(int $matchId): array
    {
        $stats = [
            'created' => 0,
            'skipped_afford' => 0,
            'skipped_outcome' => 0,
            'skipped_exists' => 0,
            'errors' => 0,
        ];

        if ($matchId <= 0 || !Loader::includeModule('iblock')) {
            return $stats;
        }

        $matchRow = $this->loadMatchRow($matchId);
        if (!$matchRow) {
            return $stats;
        }

        $eventId = (int)$matchRow['PROPERTY_EVENTS_VALUE'];
        $matchNumber = (int)$matchRow['PROPERTY_NUMBER_VALUE'];

        if (!$this->scopeService->isMatchInScope($eventId, $matchNumber)) {
            return $stats;
        }

        $prognosisIbId = $this->resolvePrognosisIblockIdForMatch($matchRow);
        if ($prognosisIbId <= 0) {
            return $stats;
        }

        $rs = \CIBlockElement::GetList(
            [],
            $this->buildPrognosisFilter($prognosisIbId, $matchId, $eventId, $matchNumber),
            false,
            false,
            [
                'ID',
                'PROPERTY_user_id',
                'PROPERTY_result',
                'PROPERTY_diff',
                'PROPERTY_maps_home',
                'PROPERTY_maps_guest',
                'PROPERTY_goal_home',
                'PROPERTY_goal_guest',
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

            try {
                $this->walletService->grantStarterPackIfMissing($userId);
                $this->walletService->ensureWallet($userId);

                if ($this->repository->getMatchBet($userId, $matchId)) {
                    $stats['skipped_exists']++;
                    continue;
                }

                $outcome = $this->resolvePrognosisOutcome($row);
                if ($outcome === null) {
                    $stats['skipped_outcome']++;
                    continue;
                }

                if (!$this->canUserAffordStake($userId)) {
                    $stats['skipped_afford']++;
                    continue;
                }

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
                $stats['created']++;
            } catch (\Throwable $exception) {
                $stats['errors']++;
                continue;
            }
        }

        return $stats;
    }

    /**
     * @param array<string, mixed> $bet
     */
    private function reverseBetWalletEffects(array $bet, int $matchId): void
    {
        $userId = (int)($bet['UF_USER_ID'] ?? 0);
        if ($userId <= 0) {
            return;
        }

        $status = (string)($bet['UF_STATUS'] ?? '');
        $stake = round((float)($bet['UF_STAKE'] ?? 0), 1);
        $payout = round((float)($bet['UF_PAYOUT'] ?? 0), 1);

        try {
            if ($status === GameEconomyConfig::BET_STATUS_WON && $payout > 0) {
                $this->walletService->debit(
                    $userId,
                    GameEconomyConfig::CURRENCY_PROGNOBAKS,
                    $payout,
                    'bet_payout_reversal',
                    'match',
                    $matchId
                );
            }

            if ($stake > 0) {
                $this->walletService->credit(
                    $userId,
                    GameEconomyConfig::CURRENCY_PROGNOBAKS,
                    $stake,
                    'bet_stake_refund',
                    'match',
                    $matchId
                );
            }
        } catch (\Throwable $exception) {
            // Запись ставки всё равно удалим — иначе кошелёк и HL разъедутся.
        }
    }

    /**
     * @param array<string, mixed> $prognosisRow
     */
    private function resolvePrognosisOutcome(array $prognosisRow): ?string
    {
        $outcome = $this->normalizeOutcome($prognosisRow['PROPERTY_RESULT_VALUE'] ?? null);
        if ($outcome !== null) {
            return $outcome;
        }

        $diff = (int)($prognosisRow['PROPERTY_DIFF_VALUE'] ?? 0);
        if ($diff !== 0 || ($prognosisRow['PROPERTY_DIFF_VALUE'] ?? '') !== '') {
            if ($diff > 0) {
                return 'п1';
            }
            if ($diff === 0) {
                return 'н';
            }

            return 'п2';
        }

        $home = (int)($prognosisRow['PROPERTY_MAPS_HOME_VALUE'] ?? $prognosisRow['PROPERTY_GOAL_HOME_VALUE'] ?? 0);
        $guest = (int)($prognosisRow['PROPERTY_MAPS_GUEST_VALUE'] ?? $prognosisRow['PROPERTY_GOAL_GUEST_VALUE'] ?? 0);
        $goalDiff = $home - $guest;
        if ($goalDiff > 0) {
            return 'п1';
        }
        if ($goalDiff === 0) {
            return 'н';
        }

        if ($goalDiff < 0) {
            return 'п2';
        }

        return null;
    }

    /**
     * @param array<string, mixed> $matchRow
     */
    private function resolveOfficialOutcome(array $matchRow): ?string
    {
        $outcome = $this->normalizeOutcome($matchRow['PROPERTY_RESULT_VALUE'] ?? null);
        if ($outcome !== null) {
            return $outcome;
        }

        $home = (int)($matchRow['PROPERTY_MAPS_HOME_VALUE'] ?? $matchRow['PROPERTY_GOAL_HOME_VALUE'] ?? 0);
        $guest = (int)($matchRow['PROPERTY_MAPS_GUEST_VALUE'] ?? $matchRow['PROPERTY_GOAL_GUEST_VALUE'] ?? 0);
        $diff = $home - $guest;

        if ($diff > 0) {
            return 'п1';
        }
        if ($diff === 0) {
            return 'н';
        }

        if ($diff < 0) {
            return 'п2';
        }

        return null;
    }

    private function resolvePrognosisIblockId(): int
    {
        return (int)(\CIBlock::GetList([], ['CODE' => 'prognosis'], false)->Fetch()['ID'] ?: 6);
    }

    /**
     * @param array<string, mixed> $matchRow
     */
    private function resolvePrognosisIblockIdForMatch(array $matchRow): int
    {
        $matchIblockId = (int)($matchRow['IBLOCK_ID'] ?? 0);
        $cs2MatchesId = $this->resolveCs2MatchIblockId();

        if ($cs2MatchesId > 0 && $matchIblockId === $cs2MatchesId) {
            return $this->resolveCs2PrognosisIblockId();
        }

        return $this->resolvePrognosisIblockId();
    }

    private function resolveCs2MatchIblockId(): int
    {
        static $id = null;
        if ($id === null) {
            $id = (int)(\CIBlock::GetList([], ['CODE' => 'cs2matches'], false)->Fetch()['ID'] ?: 0);
        }

        return $id;
    }

    private function resolveCs2PrognosisIblockId(): int
    {
        static $id = null;
        if ($id === null) {
            $id = (int)(\CIBlock::GetList([], ['CODE' => 'prognoscs2'], false)->Fetch()['ID'] ?: 0);
        }

        return $id;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPrognosisFilter(int $iblockId, int $matchId, int $eventId, int $matchNumber): array
    {
        $matchKeys = [
            ['PROPERTY_match_id' => $matchId],
            ['PROPERTY_MATCH_ID' => $matchId],
        ];

        if ($eventId > 0 && $matchNumber > 0) {
            $matchKeys[] = [
                'PROPERTY_events' => $eventId,
                'PROPERTY_number' => $matchNumber,
            ];
        }

        return [
            'IBLOCK_ID' => $iblockId,
            [
                'LOGIC' => 'OR',
                ...$matchKeys,
            ],
        ];
    }

    private function loadMatchRow(int $matchId): ?array
    {
        $select = [
            'ID',
            'IBLOCK_ID',
            'ACTIVE',
            'PROPERTY_events',
            'PROPERTY_number',
            'PROPERTY_result',
            'PROPERTY_maps_home',
            'PROPERTY_maps_guest',
            'PROPERTY_goal_home',
            'PROPERTY_goal_guest',
        ];

        $iblockIds = array_values(array_filter(array_unique([
            2,
            $this->resolveCs2MatchIblockId(),
        ])));

        foreach ($iblockIds as $iblockId) {
            if ($iblockId <= 0) {
                continue;
            }

            $row = \CIBlockElement::GetList(
                [],
                [
                    'IBLOCK_ID' => $iblockId,
                    'ID' => $matchId,
                ],
                false,
                false,
                $select
            )->GetNext();

            if ($row) {
                return $row;
            }
        }

        return null;
    }

    private function normalizeOutcome($value): ?string
    {
        $outcome = mb_strtolower(trim((string)$value));
        $aliases = [
            'p1' => 'п1',
            'p2' => 'п2',
            '1' => 'п1',
            '2' => 'п2',
            'x' => 'н',
            'n' => 'н',
            'draw' => 'н',
        ];

        if (isset($aliases[$outcome])) {
            $outcome = $aliases[$outcome];
        }

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
