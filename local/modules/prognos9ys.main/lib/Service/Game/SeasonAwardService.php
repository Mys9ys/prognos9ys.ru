<?php

namespace Prognos9ys\Main\Service\Game;

use Bitrix\Main\Type\DateTime;
use Prognos9ys\Main\Model\Repository\GameEconomyRepository;
use Prognos9ys\Main\Service\Rating\FootballRatingCalculator;

class SeasonAwardService
{
    private GameEconomyRepository $repository;
    private WalletService $walletService;
    private TreasureService $treasureService;

    public function __construct(
        ?GameEconomyRepository $repository = null,
        ?WalletService $walletService = null,
        ?TreasureService $treasureService = null
    ) {
        $this->repository = $repository ?? new GameEconomyRepository();
        $this->walletService = $walletService ?? new WalletService($this->repository);
        $this->treasureService = $treasureService ?? new TreasureService($this->repository);
    }

    /**
     * Идемпотентный freeze: если для event уже есть rows — ошибка «уже закрыт».
     * С $force=true: удаляет старые rows (если никто ещё не claim) и пишет заново.
     * С $append=true: дописывает только номинации, которых ещё нет у события (claimed не трогает).
     *
     * @return array{event_id: int, created: int, match_number: int|null, by_selector: array<string, int>, reset?: int, append?: bool}
     */
    public function freezeEvent(int $eventId, bool $force = false, bool $append = false): array
    {
        if ($eventId <= 0) {
            throw new \InvalidArgumentException('Некорректный eventId');
        }

        if ($force && $append) {
            throw new \InvalidArgumentException('Нельзя одновременно --force и --append');
        }

        $stats = $this->repository->getSeasonAwardStatsForEvent($eventId);
        $reset = 0;
        $existingSelectors = [];

        if ($stats['total'] > 0) {
            if ($append) {
                $existingSelectors = $this->repository->getSeasonAwardSelectorsForEvent($eventId);
            } elseif (!$force) {
                throw new \RuntimeException(
                    'Сезонные награды для этого события уже закрыты (freeze). '
                    . 'Дописать номинации: php freeze_season_awards.php ' . $eventId . ' --append'
                    . ' | полный сброс pending: --force'
                    . ' (pending=' . $stats['pending'] . ', claimed=' . $stats['claimed'] . ')'
                );
            } else {
                if ($stats['claimed'] > 0) {
                    throw new \RuntimeException(
                        'Нельзя перезаписать freeze: уже есть claimed-награды (' . $stats['claimed'] . '). '
                        . 'Для новых номинаций используй --append.'
                    );
                }

                $reset = $this->repository->deleteSeasonAwardsForEvent($eventId);
            }
        }

        $now = new DateTime();
        $created = 0;
        $bySelector = [];
        $matchNumber = null;

        $podiums = $this->buildAllPodiums($eventId);

        foreach (SeasonAwardConfig::getNominations() as $selector => $meta) {
            unset($meta);
            if ($append && isset($existingSelectors[$selector])) {
                $bySelector[$selector] = 0;
                continue;
            }

            $podium = $podiums[$selector] ?? ['match_number' => null, 'rows' => []];

            if ($podium['match_number'] !== null) {
                $matchNumber = $podium['match_number'];
            }

            $count = 0;
            foreach ($podium['rows'] as $row) {
                $place = (int)$row['place'];
                $userId = (int)$row['user_id'];
                $score = (float)$row['score'];
                if ($userId <= 0 || $place < 1 || $place > 3) {
                    continue;
                }

                $reward = SeasonAwardConfig::getRewardForPlace($selector, $place);
                $awardCode = SeasonAwardConfig::buildAwardCode($selector, $place);

                $this->repository->addSeasonAward([
                    'UF_USER_ID' => $userId,
                    'UF_EVENT_ID' => $eventId,
                    'UF_SELECTOR' => $selector,
                    'UF_PLACE' => $place,
                    'UF_AWARD_CODE' => $awardCode,
                    'UF_STATUS' => SeasonAwardConfig::STATUS_PENDING,
                    'UF_MATCH_NUMBER' => $podium['match_number'],
                    'UF_SCORE' => $score,
                    'UF_REWARD_JSON' => json_encode($reward, JSON_UNESCAPED_UNICODE),
                    'UF_CREATED_AT' => $now,
                    'UF_UPDATED_AT' => $now,
                ]);

                $created++;
                $count++;
            }

            $bySelector[$selector] = $count;
        }

        $result = [
            'event_id' => $eventId,
            'created' => $created,
            'match_number' => $matchNumber,
            'by_selector' => $bySelector,
        ];
        if ($force) {
            $result['reset'] = $reset;
        }
        if ($append) {
            $result['append'] = true;
        }

        return $result;
    }

    /**
     * @return array{
     *   status: string,
     *   event_id: int,
     *   pending_count: int,
     *   nominations: array,
     *   awards: list<array>
     * }
     */
    public function listForUser(int $userId, ?int $eventId = null): array
    {
        $eventId = $eventId && $eventId > 0 ? $eventId : SeasonAwardConfig::DEFAULT_EVENT_ID;
        $rows = $this->repository->listSeasonAwardsForUser($userId, $eventId);
        $awards = [];
        $pendingCount = 0;

        foreach ($rows as $row) {
            $item = $this->mapAwardRow($row);
            if ($item['status'] === SeasonAwardConfig::STATUS_PENDING) {
                $pendingCount++;
            }
            $awards[] = $item;
        }

        return [
            'status' => 'ok',
            'event_id' => $eventId,
            'pending_count' => $pendingCount,
            'nominations' => SeasonAwardConfig::getNominations(),
            'awards' => $awards,
        ];
    }

    /**
     * @param int|string $awardIdOrCode
     * @return array{claimed: array, awards: array}
     */
    public function claim(int $userId, $awardIdOrCode, ?int $eventId = null): array
    {
        if ($userId <= 0) {
            throw new \InvalidArgumentException('Пользователь не указан');
        }

        $row = $this->resolveAwardRow($userId, $awardIdOrCode, $eventId);
        if (!$row) {
            throw new \RuntimeException('Награда не найдена');
        }

        if ((int)($row['UF_USER_ID'] ?? 0) !== $userId) {
            throw new \RuntimeException('Награда принадлежит другому игроку');
        }

        $claimed = $this->claimRow($row);

        return [
            'claimed' => $claimed,
            'awards' => $this->listForUser($userId, (int)($row['UF_EVENT_ID'] ?? 0)),
        ];
    }

    /**
     * @return array{claimed: list<array>, awards: array, count: int}
     */
    public function claimAll(int $userId, ?int $eventId = null): array
    {
        if ($userId <= 0) {
            throw new \InvalidArgumentException('Пользователь не указан');
        }

        $eventId = $eventId && $eventId > 0 ? $eventId : SeasonAwardConfig::DEFAULT_EVENT_ID;
        $rows = $this->repository->listSeasonAwardsForUser($userId, $eventId);
        $claimed = [];

        foreach ($rows as $row) {
            if ((string)($row['UF_STATUS'] ?? '') !== SeasonAwardConfig::STATUS_PENDING) {
                continue;
            }
            $claimed[] = $this->claimRow($row);
        }

        return [
            'claimed' => $claimed,
            'count' => count($claimed),
            'awards' => $this->listForUser($userId, $eventId),
        ];
    }

    /**
     * Один лёгкий расчёт рейтинга → подиумы всех номинаций (top-3 с ничьями 1,1,3).
     *
     * @return array<string, array{match_number: int|null, rows: list<array{user_id:int,place:int,score:float}>}>
     */
    private function buildAllPodiums(int $eventId): array
    {
        $payload = (new FootballRatingCalculator())->calculateLatestCumulativeScores($eventId);
        $matchNumber = isset($payload['match_number']) ? (int)$payload['match_number'] : null;
        if ($matchNumber !== null && $matchNumber <= 0) {
            $matchNumber = null;
        }
        /** @var array<string, array<int, float>> $scoresBySelector */
        $scoresBySelector = is_array($payload['scores'] ?? null) ? $payload['scores'] : [];

        $podiums = [];
        foreach (SeasonAwardConfig::getNominations() as $selector => $meta) {
            $kind = (string)($meta['kind'] ?? 'selector');
            if ($kind === 'playoff') {
                $combined = [];
                foreach (['otime', 'spenalty'] as $part) {
                    foreach (($scoresBySelector[$part] ?? []) as $uid => $score) {
                        $userId = (int)$uid;
                        if ($userId <= 0) {
                            continue;
                        }
                        $combined[$userId] = ($combined[$userId] ?? 0.0) + (float)$score;
                    }
                }
                $podiums[$selector] = [
                    'match_number' => $matchNumber,
                    'rows' => $this->topPlacesFromScores($combined),
                ];
                continue;
            }

            $podiums[$selector] = [
                'match_number' => $matchNumber,
                'rows' => $this->topPlacesFromScores($scoresBySelector[$selector] ?? []),
            ];
        }

        return $podiums;
    }

    /**
     * @param array<int, float> $scores
     * @return list<array{user_id:int,place:int,score:float}>
     */
    private function topPlacesFromScores(array $scores): array
    {
        if ($scores === []) {
            return [];
        }

        arsort($scores, SORT_NUMERIC);

        $place = 1;
        $count = 1;
        $prev = null;
        $rows = [];

        foreach ($scores as $userId => $score) {
            if ($prev !== null && (float)$score !== (float)$prev) {
                $place = $count;
            }
            if ($place > 3) {
                break;
            }
            $rows[] = [
                'user_id' => (int)$userId,
                'place' => $place,
                'score' => (float)$score,
            ];
            $prev = $score;
            $count++;
        }

        return $rows;
    }

    /**
     * @param int|string $awardIdOrCode
     */
    private function resolveAwardRow(int $userId, $awardIdOrCode, ?int $eventId): ?array
    {
        if (is_numeric($awardIdOrCode) && (int)$awardIdOrCode > 0 && !is_string($awardIdOrCode)) {
            return $this->repository->getSeasonAwardById((int)$awardIdOrCode);
        }

        $asString = trim((string)$awardIdOrCode);
        if ($asString !== '' && ctype_digit($asString)) {
            $byId = $this->repository->getSeasonAwardById((int)$asString);
            if ($byId) {
                return $byId;
            }
        }

        if ($asString === '') {
            return null;
        }

        return $this->repository->getSeasonAwardByCodeForUser($userId, $asString, $eventId);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function claimRow(array $row): array
    {
        $id = (int)($row['ID'] ?? 0);
        $userId = (int)($row['UF_USER_ID'] ?? 0);
        $status = (string)($row['UF_STATUS'] ?? '');

        if ($id <= 0 || $userId <= 0) {
            throw new \RuntimeException('Некорректная запись награды');
        }

        if ($status === SeasonAwardConfig::STATUS_CLAIMED) {
            throw new \RuntimeException('Награда уже забрана');
        }

        if ($status !== SeasonAwardConfig::STATUS_PENDING) {
            throw new \RuntimeException('Награду нельзя забрать');
        }

        $reward = json_decode((string)($row['UF_REWARD_JSON'] ?? ''), true);
        if (!is_array($reward)) {
            $reward = [];
        }

        $selector = (string)($row['UF_SELECTOR'] ?? '');
        $place = (int)($row['UF_PLACE'] ?? 1);
        $configReward = SeasonAwardConfig::getRewardForPlace($selector, $place);
        // Сундуки/свиток из снимка freeze; кубок — всегда из актуального конфига (для старых pending).
        $reward = array_merge($configReward, $reward);
        $reward['cup'] = (string)($configReward['cup'] ?? SeasonCupConfig::buildCode($selector, $place));

        $given = [
            'prognobaks' => 0.0,
            'chests' => 0,
            'premium_scroll_days' => 0,
            'cup' => null,
        ];

        $prognobaks = (float)($reward['prognobaks'] ?? 0);
        if ($prognobaks > 0) {
            $already = $this->repository->hasWalletTx(
                $userId,
                'season_award',
                'season_award',
                $id,
                GameEconomyConfig::CURRENCY_PROGNOBAKS
            );
            if (!$already) {
                $this->walletService->credit(
                    $userId,
                    GameEconomyConfig::CURRENCY_PROGNOBAKS,
                    $prognobaks,
                    'season_award',
                    'season_award',
                    $id
                );
            }
            $given['prognobaks'] = $prognobaks;
        }

        $chests = (int)($reward['chests'] ?? 0);
        if ($chests > 0) {
            $awardCode = (string)($row['UF_AWARD_CODE'] ?? ('season_' . $id));
            $granted = $this->treasureService->grantWc26AchievementChests(
                $userId,
                'season_award_' . $awardCode,
                1,
                $chests
            );
            $given['chests'] = $granted ? $chests : 0;
        }

        $premiumDays = (int)($reward['premium_scroll_days'] ?? 0);
        if ($premiumDays > 0) {
            // Уникальный milestone: 900000 + rowId (не пересекается с лавкой казны).
            $milestone = 900000 + $id;
            $granted = $this->treasureService->grantPremiumScroll($userId, $milestone, $premiumDays);
            $given['premium_scroll_days'] = $granted ? $premiumDays : 0;
        }

        $cupCode = trim((string)($reward['cup'] ?? ''));
        if ($cupCode !== '') {
            $granted = $this->treasureService->grantSeasonCup($userId, $cupCode);
            $given['cup'] = $granted ? $cupCode : null;
        }

        $now = new DateTime();
        $this->repository->updateSeasonAward($id, [
            'UF_STATUS' => SeasonAwardConfig::STATUS_CLAIMED,
            'UF_CLAIMED_AT' => $now,
            'UF_UPDATED_AT' => $now,
        ]);

        GameProfileService::invalidateSummaryCache($userId);

        $fresh = $this->repository->getSeasonAwardById($id) ?: $row;
        $mapped = $this->mapAwardRow($fresh);
        $mapped['given'] = $given;

        return $mapped;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function mapAwardRow(array $row): array
    {
        $selector = (string)($row['UF_SELECTOR'] ?? '');
        $place = (int)($row['UF_PLACE'] ?? 0);
        $reward = json_decode((string)($row['UF_REWARD_JSON'] ?? ''), true);
        if (!is_array($reward)) {
            $reward = [];
        }
        $configReward = SeasonAwardConfig::getRewardForPlace($selector, $place);
        $reward = array_merge($configReward, $reward);
        $reward['cup'] = (string)($configReward['cup'] ?? SeasonCupConfig::buildCode($selector, $place));

        $noms = SeasonAwardConfig::getNominations();
        $nom = $noms[$selector] ?? null;
        $cupMeta = SeasonCupConfig::getMeta((string)$reward['cup']);

        return [
            'id' => (int)($row['ID'] ?? 0),
            'event_id' => (int)($row['UF_EVENT_ID'] ?? 0),
            'selector' => $selector,
            'place' => $place,
            'award_code' => (string)($row['UF_AWARD_CODE'] ?? ''),
            'status' => (string)($row['UF_STATUS'] ?? ''),
            'match_number' => isset($row['UF_MATCH_NUMBER']) ? (int)$row['UF_MATCH_NUMBER'] : null,
            'score' => (float)($row['UF_SCORE'] ?? 0),
            'reward' => $reward,
            'cup' => $cupMeta,
            'title' => (string)($nom['title'] ?? $selector),
            'description' => (string)($nom['description'] ?? ''),
            'icon' => (string)($nom['icon'] ?? '🏅'),
            'badge' => SeasonAwardConfig::getBadgeMeta($selector, $place),
            'claimable' => (string)($row['UF_STATUS'] ?? '') === SeasonAwardConfig::STATUS_PENDING,
            'claimed_at' => $this->formatDateTime($row['UF_CLAIMED_AT'] ?? null),
        ];
    }

    /**
     * Backfill кубков для уже claimed наград события.
     *
     * @return array{event_id:int, scanned:int, granted:int, skipped:int}
     */
    public function backfillCupsForEvent(int $eventId): array
    {
        if ($eventId <= 0) {
            throw new \InvalidArgumentException('Некорректный eventId');
        }

        $rows = $this->repository->listSeasonAwardsForEvent($eventId);
        $granted = 0;
        $skipped = 0;
        $scanned = 0;

        foreach ($rows as $row) {
            if ((string)($row['UF_STATUS'] ?? '') !== SeasonAwardConfig::STATUS_CLAIMED) {
                continue;
            }

            $scanned++;
            $userId = (int)($row['UF_USER_ID'] ?? 0);
            $selector = (string)($row['UF_SELECTOR'] ?? '');
            $place = (int)($row['UF_PLACE'] ?? 0);
            $cupCode = SeasonCupConfig::buildCode($selector, $place);
            if ($userId <= 0 || !SeasonCupConfig::isSeasonCupCode($cupCode)) {
                $skipped++;
                continue;
            }

            if ($this->treasureService->grantSeasonCup($userId, $cupCode)) {
                $granted++;
                GameProfileService::invalidateSummaryCache($userId);
            } else {
                $skipped++;
            }
        }

        return [
            'event_id' => $eventId,
            'scanned' => $scanned,
            'granted' => $granted,
            'skipped' => $skipped,
        ];
    }

    /**
     * @param mixed $value
     */
    private function formatDateTime($value): ?string
    {
        if ($value instanceof DateTime) {
            return $value->format('Y-m-d H:i:s');
        }
        if (is_string($value) && $value !== '') {
            return $value;
        }

        return null;
    }
}
