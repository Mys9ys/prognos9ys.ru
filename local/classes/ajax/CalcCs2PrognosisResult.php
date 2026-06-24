<?php

use Bitrix\Main\Loader;
use Prognos9ys\Main\Model\Repository\Cs2IblockRegistry;
use Prognos9ys\Main\Service\Cs2\Cs2FieldMapper;

class CalcCs2PrognosisResult extends CalcFootballPrognosisResult
{
    protected $arSelect = [
        'ID',
        'ACTIVE',
        'DATE_ACTIVE_FROM',
        'PROPERTY_home',
        'PROPERTY_maps_home',
        'PROPERTY_guest',
        'PROPERTY_maps_guest',
        'PROPERTY_number',
        'PROPERTY_match_id',
        'PROPERTY_result',
        'PROPERTY_diff',
        'PROPERTY_sum',
        'PROPERTY_opening_pct',
        'PROPERTY_pistol_pct',
        'PROPERTY_clutches_home',
        'PROPERTY_clutches_guest',
        'PROPERTY_map_scores',
        'PROPERTY_number',
        'PROPERTY_user_id',
        'PROPERTY_events',
    ];

    public function __construct($data)
    {
        $registry = new Cs2IblockRegistry();
        $ids = $registry->legacyIds();

        $this->arIbs = [
            'matches' => ['code' => Cs2IblockRegistry::IBLOCK_MATCHES, 'id' => $ids['matches']],
            'prognosis' => ['code' => Cs2IblockRegistry::IBLOCK_PROGNOSIS, 'id' => $ids['prognosis']],
            'result' => ['code' => Cs2IblockRegistry::IBLOCK_RESULT, 'id' => $ids['result']],
        ];

        parent::__construct($data);
    }

    protected function calcResultPrognosisUser()
    {
        $matchRes = $this->arMiddleResult['result'];

        foreach ($this->arMiddleResult['prognosis'] as $userId => $prognosis) {
            $result = [];
            $all = 0;

            $result['user'] = $userId;
            $result['match'] = $prognosis['PROPERTY_MATCH_ID_VALUE'];
            $result['number'] = $prognosis['PROPERTY_NUMBER_VALUE'];
            $result['event'] = $prognosis['PROPERTY_EVENTS_VALUE'];

            $arResMaps = [
                'home' => $prognosis['PROPERTY_MAPS_HOME_VALUE'],
                'guest' => $prognosis['PROPERTY_MAPS_GUEST_VALUE'],
            ];
            $arProgMaps = [
                'home' => $matchRes['PROPERTY_MAPS_HOME_VALUE'],
                'guest' => $matchRes['PROPERTY_MAPS_GUEST_VALUE'],
            ];
            $result['score'] = $this->calcGoals($arResMaps, $arProgMaps);
            $result['goals'] = $result['score'];
            $all += $result['score'];

            $result['result'] = $this->calcConstScore(
                $prognosis['PROPERTY_RESULT_VALUE'],
                $matchRes['PROPERTY_RESULT_VALUE']
            );
            $all += $result['result'];

            $result['sum'] = $this->calcConstScore(
                $prognosis['PROPERTY_SUM_VALUE'],
                $matchRes['PROPERTY_SUM_VALUE']
            );
            $all += $result['sum'];

            $result['diff'] = $this->calcConstScore(
                $prognosis['PROPERTY_DIFF_VALUE'],
                $matchRes['PROPERTY_DIFF_VALUE']
            );
            $all += $result['diff'];

            $result['opening_pct'] = $this->calcDomination(
                $prognosis['PROPERTY_OPENING_PCT_VALUE'],
                $matchRes['PROPERTY_OPENING_PCT_VALUE']
            );
            $all += $result['opening_pct'];

            if ($prognosis['PROPERTY_PISTOL_PCT_VALUE'] !== null && $prognosis['PROPERTY_PISTOL_PCT_VALUE'] !== '') {
                $result['pistol_pct'] = $this->calcDomination(
                    $prognosis['PROPERTY_PISTOL_PCT_VALUE'],
                    $matchRes['PROPERTY_PISTOL_PCT_VALUE']
                );
                $all += $result['pistol_pct'];
            } else {
                $result['pistol_pct'] = 0;
            }

            if ($prognosis['PROPERTY_CLUTCHES_HOME_VALUE'] !== null && $prognosis['PROPERTY_CLUTCHES_HOME_VALUE'] !== '') {
                $result['clutches_home'] = $this->calcProgressScala(
                    $prognosis['PROPERTY_CLUTCHES_HOME_VALUE'],
                    $matchRes['PROPERTY_CLUTCHES_HOME_VALUE']
                );
                $all += $result['clutches_home'];
            } else {
                $result['clutches_home'] = 0;
            }

            if ($prognosis['PROPERTY_CLUTCHES_GUEST_VALUE'] !== null && $prognosis['PROPERTY_CLUTCHES_GUEST_VALUE'] !== '') {
                $result['clutches_guest'] = $this->calcProgressScala(
                    $prognosis['PROPERTY_CLUTCHES_GUEST_VALUE'],
                    $matchRes['PROPERTY_CLUTCHES_GUEST_VALUE']
                );
                $all += $result['clutches_guest'];
            } else {
                $result['clutches_guest'] = 0;
            }

            $mapPoints = $this->calcMapScores(
                (string)($prognosis['PROPERTY_MAP_SCORES_VALUE'] ?? ''),
                (string)($matchRes['PROPERTY_MAP_SCORES_VALUE'] ?? '')
            );
            $result['map_scores'] = $mapPoints;
            $all += $mapPoints;

            $result['all'] = $all;
            $this->arResults[$userId] = $result;
        }
    }

    protected function setOneResult($arr)
    {
        $registry = new Cs2IblockRegistry();
        $mapper = new Cs2FieldMapper($registry);

        $prop = $mapper->resultScoresToBitrix([
            'score' => $arr['score'] ?? $arr['goals'] ?? 0,
            'result' => $arr['result'] ?? 0,
            'diff' => $arr['diff'] ?? 0,
            'sum' => $arr['sum'] ?? 0,
            'opening_pct' => $arr['opening_pct'] ?? 0,
            'pistol_pct' => $arr['pistol_pct'] ?? 0,
            'clutches_home' => $arr['clutches_home'] ?? 0,
            'clutches_guest' => $arr['clutches_guest'] ?? 0,
            'map_scores' => $arr['map_scores'] ?? 0,
            'all' => $arr['all'] ?? 0,
            'match_id' => $arr['match'] ?? 0,
            'user_id' => $arr['user'] ?? 0,
            'number' => $arr['number'] ?? 0,
            'events' => $arr['event'] ?? 0,
        ]);

        $ib = new CIBlockElement;
        $userId = (int)($arr['user'] ?? 0);
        $name = 'Участник: ' . $userId . ' Результаты прогноза CS2 на матч: ' . ($arr['number'] ?? '');
        $existingId = $this->existingResultByUser[$userId] ?? null;

        if ($existingId) {
            $success = $ib->Update($existingId, [
                'NAME' => $name,
                'PROPERTY_VALUES' => $prop,
            ]);
        } else {
            $now = date(\CDatabase::DateFormatToPHP('DD.MM.YYYY HH:MI:SS'), time());
            $newId = $ib->Add([
                'NAME' => $name,
                'IBLOCK_ID' => $this->arIbs['result']['id'],
                'DATE_ACTIVE_FROM' => $now,
                'PROPERTY_VALUES' => $prop,
            ]);
            $success = (bool)$newId;
            if ($success) {
                $this->existingResultByUser[$userId] = (int)$newId;
            }
        }

        if ($success) {
            if (Loader::includeModule('prognos9ys.main')) {
                try {
                    $eventId = (int)($arr['event'] ?? 0);
                    $matchId = (int)($arr['match'] ?? 0);
                    $number = (int)($arr['number'] ?? 0);
                    (new \Prognos9ys\Main\Service\Game\TreasureService())->upsertFromScore(
                        $userId,
                        $matchId,
                        $eventId,
                        $number,
                        (float)($arr['all'] ?? 0)
                    );
                } catch (\Throwable $exception) {
                }
            }
            $this->setResult('ok', '');
        }
    }

    protected function syncGamePendingXp(): void
    {
        if (empty($this->data['matchId'])) {
            return;
        }

        if (!Loader::includeModule('prognos9ys.main')) {
            return;
        }

        $matchId = (int)$this->data['matchId'];

        try {
            (new \Prognos9ys\Main\Service\Game\ExperienceService())->syncPendingForMatch($matchId);
        } catch (\Throwable $exception) {
            $this->logGameEconomyError('syncPendingForMatch', $matchId, $exception);
        }

        try {
            $betService = new \Prognos9ys\Main\Service\Game\BetService();
            $deleted = $betService->resetMatchBetsForRecalc($matchId);
            $backfill = $betService->backfillBetsFromPrognosis($matchId);
            $participation = $betService->collectMatchParticipationStats($matchId);
            $settle = $betService->settleMatch($matchId);
            (new \Prognos9ys\Main\Service\Game\BankSettlementService())->onMatchSettled($matchId);
            $this->applySettlementLogToResult($matchId, $deleted, $backfill, $participation, $settle);
            error_log(sprintf(
                'CalcCs2PrognosisResult [betSettlement] match=%d %s',
                $matchId,
                json_encode([
                    'deleted' => $deleted,
                    'backfill' => $backfill,
                    'settle' => $settle,
                ], JSON_UNESCAPED_UNICODE)
            ));
        } catch (\Throwable $exception) {
            $this->logGameEconomyError('betSettlement', $matchId, $exception);
        }
    }

    protected function calcMapScores(string $prognosisJson, string $resultJson): int
    {
        $prognosisMaps = $this->decodeMapScores($prognosisJson);
        $resultMaps = $this->decodeMapScores($resultJson);

        if (!$resultMaps) {
            return 0;
        }

        $points = 0;
        $count = min(count($prognosisMaps), count($resultMaps));

        for ($i = 0; $i < $count; $i++) {
            $p = $prognosisMaps[$i] ?? [];
            $r = $resultMaps[$i] ?? [];

            $pHome = (int)($p['rounds_home'] ?? $p['home'] ?? 0);
            $pGuest = (int)($p['rounds_guest'] ?? $p['guest'] ?? 0);
            $rHome = (int)($r['rounds_home'] ?? $r['home'] ?? 0);
            $rGuest = (int)($r['rounds_guest'] ?? $r['guest'] ?? 0);

            if ($pHome === $rHome && $pGuest === $rGuest) {
                $points += 5;
                continue;
            }

            $pWinner = $pHome === $pGuest ? 'draw' : ($pHome > $pGuest ? 'home' : 'guest');
            $rWinner = $rHome === $rGuest ? 'draw' : ($rHome > $rGuest ? 'home' : 'guest');

            if ($pWinner === $rWinner && $rWinner !== 'draw') {
                $points += 2;
            }

            $roundDiff = abs(($pHome - $pGuest) - ($rHome - $rGuest));
            if ($roundDiff <= 2) {
                $points += 1;
            }
        }

        return $points;
    }

    protected function decodeMapScores(string $raw): array
    {
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function logGameEconomyError(string $stage, int $matchId, \Throwable $exception): void
    {
        error_log(sprintf(
            'CalcCs2PrognosisResult [%s] match=%d: %s',
            $stage,
            $matchId,
            $exception->getMessage()
        ));
    }
}
