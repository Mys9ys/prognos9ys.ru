<?php

use Bitrix\Main\Loader;

class CalcFootballPrognosisResult
{
    protected $data;

    protected $arIbs = [
        'matches' => ['code' => 'matches', 'id' => 2],
        'prognosis' => ['code' => 'prognosis', 'id' => 6],
        'result' => ['code' => 'result', 'id' => 7]
    ];

    protected $arResult;
    protected $arResults;

    protected $arMiddleResult;

    /** @var array<int|string, int> userId => elementId */
    protected $existingResultByUser = [];

    protected $arProps = [
        33 => "goals",
        34 => "result",
        35 => "diff",
        36 => "sum",
        37 => "domination",
        38 => "yellow",
        39 => "red",
        40 => "corner",
        41 => "penalty",
        42 => "all",
        43 => "match",
        44 => "user",
        49 => "otime",
        50 => "spenalty",
        51 => "number",
        53 => "event",
    ];

    protected $arSelect = [
        "ID",
        "ACTIVE",
        "DATE_ACTIVE_FROM",
        "PROPERTY_home",
        "PROPERTY_goal_home",
        "PROPERTY_guest",
        "PROPERTY_goal_guest",
        "PROPERTY_number",
        "PROPERTY_match_id",
        "PROPERTY_result",
        "PROPERTY_diff",
        "PROPERTY_corner",
        "PROPERTY_yellow",
        "PROPERTY_red",
        "PROPERTY_penalty",
        "PROPERTY_sum",
        "PROPERTY_offside",
        "PROPERTY_number",
        "PROPERTY_user_id",
        "PROPERTY_domination",
        "PROPERTY_otime",
        "PROPERTY_spenalty",
        "PROPERTY_events",
    ];

    public function __construct($data)
    {

        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        }

        $this->data = $data;

       $this->getEventResult();
       $this->getPrognosisArray();

        if ($this->arMiddleResult) $this->calcResultPrognosisUser();

        if($this->arResults) $this->setManyResult();

        $this->syncGamePendingXp();

    }

    protected function getEventResult()
    {
        $arFilter = [
            'IBLOCK_ID' => $this->arIbs['matches']['id'],
            'ID' => $this->data['matchId'],
        ];

        $res = CIBlockElement::GetList(
            [],
            $arFilter,
            false,
            [],
            $this->arSelect
        )->GetNext();

        $this->arMiddleResult['result'] = $res;
    }

    protected function getPrognosisArray()
    {
        $arFilter = [
            'IBLOCK_ID' => $this->arIbs['prognosis']['id'],
            'PROPERTY_match_id' => $this->data['matchId'],
        ];

        $response = CIBlockElement::GetList(
            [],
            $arFilter,
            false,
            [],
            $this->arSelect
        );

        while ($res = $response->GetNext()) {
            $this->arMiddleResult['prognosis'][$res['PROPERTY_USER_ID_VALUE']] = $res;
        }
    }

    protected function calcResultPrognosisUser()
    {
        $matchRes = $this->arMiddleResult['result'];
        foreach ($this->arMiddleResult['prognosis'] as $userId=>$prognosis) {
            $result = [];

            $all = 0;

            $result['user'] = $userId;
            $result['match'] = $prognosis["PROPERTY_MATCH_ID_VALUE"];
            $result['number'] = $prognosis["PROPERTY_NUMBER_VALUE"];

            // счет матча
            $arResGoals = ["home" => $prognosis["PROPERTY_GOAL_HOME_VALUE"],
                "guest" => $prognosis["PROPERTY_GOAL_GUEST_VALUE"],
            ];
            $arProgGoals = ["home" => $matchRes["PROPERTY_GOAL_HOME_VALUE"],
                "guest" => $matchRes["PROPERTY_GOAL_GUEST_VALUE"],
            ];
            $result['goals'] = $this->calcGoals($arResGoals, $arProgGoals);

            $all += $result['goals'];

            // исход матча
            $result['result'] = $this->calcConstScore($prognosis["PROPERTY_RESULT_VALUE"], $matchRes["PROPERTY_RESULT_VALUE"]);
            $all += $result['result'];

            // сумма голов
            $result['sum'] = $this->calcConstScore($prognosis["PROPERTY_SUM_VALUE"], $matchRes["PROPERTY_SUM_VALUE"]);
            $all += $result['sum'];

            // разница голов
            $result['diff'] = $this->calcConstScore($prognosis["PROPERTY_DIFF_VALUE"], $matchRes["PROPERTY_DIFF_VALUE"]);
            $all += $result['diff'];

            // % владения
            $result['domination'] = $this->calcDomination($prognosis["PROPERTY_DOMINATION_VALUE"], $matchRes["PROPERTY_DOMINATION_VALUE"]);
            $all += $result['domination'];

            // количество желтых карточек
            if ($prognosis["PROPERTY_YELLOW_VALUE"] || $prognosis["PROPERTY_YELLOW_VALUE"] !== null) {
                $result['yellow'] = $this->calcProgressScala($prognosis["PROPERTY_YELLOW_VALUE"], $matchRes["PROPERTY_YELLOW_VALUE"]);
                $all += $result['yellow'];
            } else { $result['yellow'] = 0;}

            // количество угловых
            if ($prognosis["PROPERTY_CORNER_VALUE"] || $prognosis["PROPERTY_CORNER_VALUE"] !== null) {
                $result['corner'] = $this->calcProgressScala($prognosis["PROPERTY_CORNER_VALUE"], $matchRes["PROPERTY_CORNER_VALUE"]);
                $all += $result['corner'];
            } else { $result['corner'] = 0;}

            // количество красных
            if ($prognosis["PROPERTY_RED_VALUE"] || $prognosis["PROPERTY_RED_VALUE"] !== null) {
                $result['red'] = $this->calcRedCard($prognosis["PROPERTY_RED_VALUE"], $matchRes["PROPERTY_RED_VALUE"]);
                $all += $result['red'];
            } else { $result['red'] = 0;}

            // количество пенальти
            if ($prognosis["PROPERTY_PENALTY_VALUE"] || $prognosis["PROPERTY_PENALTY_VALUE"] !== null) {
                $result['penalty'] = $this->calcRedCard($prognosis["PROPERTY_PENALTY_VALUE"], $matchRes["PROPERTY_PENALTY_VALUE"]);
                $all += $result['penalty'];
            } else { $result['penalty'] = 0;}


            // дополнительное время
            if ($prognosis["PROPERTY_OTIME_VALUE"] || $prognosis["PROPERTY_OTIME_VALUE"] !== null) {
                $result['otime'] = $this->calcPlayOff($prognosis["PROPERTY_OTIME_VALUE"], $matchRes["PROPERTY_OTIME_VALUE"]);
                $all += $result['otime'];
            } else { $result['otime'] = 0;}

            // серия пенальти
            if ($prognosis["PROPERTY_SPENALTY_VALUE"] || $prognosis["PROPERTY_SPENALTY_VALUE"] !== null) {
                $result['spenalty'] = $this->calcPlayOff($prognosis["PROPERTY_SPENALTY_VALUE"], $matchRes["PROPERTY_SPENALTY_VALUE"]);
                $all += $result['spenalty'];
            } else { $result['spenalty'] = 0;}

            $result["all"] = $all;

            $result["event"] = $prognosis["PROPERTY_EVENTS_VALUE"];

            $this->arResults[$result['user']] = $result;

        }
    }

    protected function calcGoals($arPrognos, $arRes)
    {
        if ($arPrognos["home"] === $arRes["home"] && $arPrognos["guest"] === $arRes["guest"]) {
            return 10;
        } else {
            return 0;
        }
    }

    protected function calcConstScore($prognos, $res)
    {

        if ($prognos === $res) {
            return 5;
        } else {
            return 0;
        }
    }

    protected function calcDomination($prognos, $res)
    {

        $diff = abs(+$prognos - +$res);

        if ($diff === 0) {
            return 5;
        } elseif ($diff < 6) {
            return 3;
        } elseif ($diff < 11) {
            return 1;
        } else {
            return 0;
        }

    }

    protected function calcProgressScala($prognos, $res)
    {
        $diff = abs(+$prognos - +$res);

        if ($diff === 0) {
            return 5;
        } elseif ($diff < 2) {
            return 3;
        } elseif ($diff < 3) {
            return 1;
        } else {
            return 0;
        }
    }

    protected function calcRedCard($prognos, $res)
    {

        if($prognos !== ''){
            if(+$prognos >9) return 0;
            if (+$prognos === 0 && +$res === 0) return 0.5;
            if ($prognos === $res && +$res > 0) return 5 +(($res-1)*2);
            if (+$prognos>0 && +$res > 0) return 0.5;
        }

        return 0;
    }

    protected function calcPlayOff($prognos, $res){
        if($prognos !== ''){
            if ($prognos === 'Не будет' && $res === 'Не будет') return 0.5;
            if ($prognos === 'Будет' && $res === 'Будет') return 5;
            return 0;
        }
        return 0;

    }

    protected function setManyResult()
    {
        $this->loadExistingResultIds();

        foreach ($this->arResults as $res) {
            $this->setOneResult($res);
        }
    }

    protected function loadExistingResultIds(): void
    {
        if (empty($this->data['matchId'])) {
            return;
        }

        $response = CIBlockElement::GetList(
            [],
            [
                'IBLOCK_ID' => $this->arIbs['result']['id'],
                'PROPERTY_match_id' => $this->data['matchId'],
            ],
            false,
            false,
            ['ID', 'PROPERTY_user_id']
        );

        while ($res = $response->GetNext()) {
            $this->existingResultByUser[$res['PROPERTY_USER_ID_VALUE']] = (int)$res['ID'];
        }
    }

    protected function setOneResult($arr){

        $prop = [
            33 => $arr["goals"],
            34 => $arr["result"],
            35 => $arr["diff"],
            36 => $arr["sum"],
            37 => $arr["domination"],
            38 => $arr["yellow"],
            39 => $arr["red"],
            40 => $arr["corner"],
            41 => $arr["penalty"],
            42 => $arr["all"],
            43 => $arr["match"],
            44 => $arr["user"],
            49 => $arr["otime"],
            50 => $arr["spenalty"],
            51 => $arr["number"],
            53 => $arr["event"],
        ];

        $ib = new CIBlockElement;
        $name = 'Участник: ' . $prop[44] . ' Результаты прогноза на матч: ' . $arr['number'];
        $userId = $prop[44];
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
            // Treasure chests: 30+ => 1, 40+ => 2 (closed, not openable yet)
            if (\Bitrix\Main\Loader::includeModule('prognos9ys.main')) {
                try {
                    (new \Prognos9ys\Main\Service\Game\TreasureService())->upsertFromScore(
                        (int)$prop[44],
                        (int)$prop[43],
                        (int)$prop[53],
                        (int)$prop[51],
                        (float)$prop[42]
                    );
                } catch (\Throwable $exception) {
                    // не блокируем пересчёт результатов
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

        if (!\Bitrix\Main\Loader::includeModule('prognos9ys.main')) {
            return;
        }

        $matchId = (int)$this->data['matchId'];
        $treasuryShopProvision = [
            'is_milestone' => false,
            'log_text' => '',
        ];

        // Ставки — в первую очередь (админ ждёт лог; ачивки проверяются при открытии профиля).
        try {
            $betService = new \Prognos9ys\Main\Service\Game\BetService();
            $deleted = $betService->resetMatchBetsForRecalc($matchId);
            $backfill = $betService->backfillBetsFromPrognosis($matchId);
            $participation = $betService->collectMatchParticipationStats($matchId);
            $settle = $betService->settleMatch($matchId);
            (new \Prognos9ys\Main\Service\Game\BankSettlementService())->onMatchSettled($matchId);
            try {
                (new \Prognos9ys\Main\Service\Game\MatchEconomySettlementService())->markFromCalc($matchId);
            } catch (\Throwable $exception) {
                $this->logGameEconomyError('markMatchEconomySettled', $matchId, $exception);
            }
            try {
                $treasuryShopProvision = (new \Prognos9ys\Main\Service\Game\TreasuryShopService())
                    ->provisionWavesForSettledMatch($matchId);
            } catch (\Throwable $exception) {
                $this->logGameEconomyError('treasuryShopProvision', $matchId, $exception);
            }
            $this->applySettlementLogToResult($matchId, $deleted, $backfill, $participation, $settle, $treasuryShopProvision);
            error_log(sprintf(
                'CalcFootballPrognosisResult [betSettlement] match=%d %s',
                $matchId,
                json_encode([
                    'deleted' => $deleted,
                    'backfill' => $backfill,
                    'participation' => $participation,
                    'settle' => $settle,
                ], JSON_UNESCAPED_UNICODE)
            ));
        } catch (\Throwable $exception) {
            $this->logGameEconomyError('betSettlement', $matchId, $exception);
        }

        try {
            (new \Prognos9ys\Main\Service\Game\ExperienceService())->syncPendingForMatch($matchId);
        } catch (\Throwable $exception) {
            $this->logGameEconomyError('syncPendingForMatch', $matchId, $exception);
        }

        // syncAfterMatch убран: ~500× collectStats давало 504 на локалке/бою при большом туре.

        $eventId = 0;
        if (Loader::includeModule('iblock')) {
            $matchRow = \CIBlockElement::GetList(
                [],
                ['IBLOCK_ID' => 2, 'ID' => $matchId],
                false,
                false,
                ['PROPERTY_events']
            )->GetNext();
            $eventId = (int)($matchRow['PROPERTY_EVENTS_VALUE'] ?? 0);
        }
        if ($eventId > 0) {
            try {
                \Prognos9ys\Main\Service\Football\FootballRatingService::clearEventCache($eventId);
            } catch (\Throwable $exception) {
                $this->logGameEconomyError('clearEventCache', $matchId, $exception);
            }
        }
    }

    /**
     * @param array<string, mixed> $backfill
     * @param array<string, mixed> $participation
     * @param array<string, mixed> $settle
     */
    protected function applySettlementLogToResult(
        int $matchId,
        int $reset,
        array $backfill,
        array $participation,
        array $settle,
        array $treasuryShopProvision = []
    ): void
    {
        $prognosisCount = is_array($this->arResults) ? count($this->arResults) : 0;
        if ($prognosisCount === 0 && !empty($this->arMiddleResult['prognosis']) && is_array($this->arMiddleResult['prognosis'])) {
            $prognosisCount = count($this->arMiddleResult['prognosis']);
        }

        $matchNumber = (int)($this->arMiddleResult['result']['PROPERTY_NUMBER_VALUE'] ?? 0);
        $label = $matchNumber > 0 ? 'матч №' . $matchNumber : 'матч ID ' . $matchId;

        $lines = [];
        $lines[] = ['text' => 'Пересчёт: ' . $label, 'status' => 'ok'];
        $lines[] = ['text' => 'Прогнозов обработано: ' . $prognosisCount, 'status' => 'ok'];

        if ($reset > 0) {
            $lines[] = ['text' => 'Сброшено рассчитанных ставок: ' . $reset, 'status' => 'skip'];
        }

        $optOut = (int)($participation['opt_out'] ?? 0);
        $lines[] = ['text' => 'Отказ от денежной ставки: ' . $optOut, 'status' => $optOut > 0 ? 'skip' : 'ok'];

        $skippedAfford = (int)($participation['skipped_afford'] ?? 0);
        $lines[] = [
            'text' => 'Не хватило 🪙 на ставку: ' . $skippedAfford,
            'status' => $skippedAfford > 0 ? 'skip' : 'ok',
        ];

        $bfCreated = (int)($backfill['created'] ?? 0);
        if ($bfCreated > 0) {
            $lines[] = ['text' => 'Добавлено ставок (боты/legacy): ' . $bfCreated, 'status' => 'ok'];
        }

        $bfExists = (int)($backfill['skipped_exists'] ?? 0);
        if ($bfExists > 0) {
            $lines[] = ['text' => 'Ставка уже была (backfill): ' . $bfExists, 'status' => 'skip'];
        }

        $bfOutcome = (int)($backfill['skipped_outcome'] ?? 0);
        if ($bfOutcome > 0) {
            $lines[] = ['text' => 'Без исхода в прогнозе: ' . $bfOutcome, 'status' => 'skip'];
        }

        $official = (string)($settle['official_outcome'] ?? '');
        if ($official !== '') {
            $lines[] = ['text' => 'Исход матча: ' . $official, 'status' => 'ok'];
        }

        $accepted = (int)($participation['accepted'] ?? $settle['pending'] ?? 0);
        $winners = (int)($settle['winners'] ?? 0);
        $losers = (int)($settle['losers'] ?? 0);
        $pool = round((float)($settle['pool'] ?? 0), 1);
        $totalPayout = round((float)($settle['total_payout'] ?? 0), 1);
        $maxPayout = round((float)($settle['max_winner_payout'] ?? 0), 1);
        $leftover = round((float)($settle['parimutuel_leftover'] ?? 0), 1);

        $lines[] = ['text' => 'Ставки приняты: ' . $accepted, 'status' => $accepted > 0 ? 'ok' : 'skip'];

        if ($accepted > 0) {
            $lines[] = ['text' => 'Призовой фонд: ' . $pool . ' 🪙', 'status' => 'ok'];
            $lines[] = ['text' => 'Проигравших: ' . $losers, 'status' => 'ok'];
            $lines[] = ['text' => 'Победителей: ' . $winners, 'status' => $winners > 0 ? 'ok' : 'skip'];
            $lines[] = ['text' => 'Сумма выигрышей: ' . $totalPayout . ' 🪙', 'status' => $winners > 0 ? 'ok' : 'skip'];
            if ($winners > 0) {
                $lines[] = ['text' => 'Макс. выплата одному: ' . $maxPayout . ' 🪙', 'status' => 'ok'];
            }
            if ($leftover > 0) {
                $lines[] = ['text' => 'Остаток в пул parimutuel: ' . $leftover . ' 🪙', 'status' => 'skip'];
            }
        }

        $treasuryLog = trim((string)($treasuryShopProvision['log_text'] ?? ''));
        if ($treasuryLog !== '') {
            $eligible = (int)($treasuryShopProvision['eligible'] ?? 0);
            $created = (int)($treasuryShopProvision['waves_created'] ?? 0);
            $lines[] = [
                'text' => $treasuryLog,
                'status' => ($eligible > 0 && $created >= 0) ? 'ok' : 'skip',
            ];
        }

        $lines[] = ['text' => 'Готово', 'status' => 'ok'];

        if (!is_array($this->arResult)) {
            $this->arResult = [];
        }

        if (($this->arResult['status'] ?? '') === '') {
            $this->arResult['status'] = 'ok';
        }

        $this->arResult['settlement_log'] = [
            'lines' => $lines,
            'summary' => [
                'prognosis_count' => $prognosisCount,
                'opt_out' => $optOut,
                'skipped_afford' => $skippedAfford,
                'accepted' => $accepted,
                'pool' => $pool,
                'total_payout' => $totalPayout,
                'max_winner_payout' => $maxPayout,
                'winners' => $winners,
                'losers' => $losers,
                'official_outcome' => $official,
            ],
        ];
    }

    protected function setResult($status, $mes, $info = '')
    {
        $this->arResult['status'] = $status;
        $this->arResult['mes'] = $mes;
    }

    private function logGameEconomyError(string $stage, int $matchId, \Throwable $exception): void
    {
        error_log(sprintf(
            'CalcFootballPrognosisResult [%s] match=%d: %s',
            $stage,
            $matchId,
            $exception->getMessage()
        ));
    }

    public function result()
    {
        return $this->arResult;
    }

}