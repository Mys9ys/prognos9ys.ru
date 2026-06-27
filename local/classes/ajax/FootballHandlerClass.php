<?php

require_once __DIR__ . '/PlayoffSlotHelper.php';

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Loader;

class FootballHandlerClass
{

    protected $arIbs = [
        'events' => ['code' => 'events', 'id' => 1],
        'matches' => ['code' => 'matches', 'id' => 2],
        'group' => ['code' => 'group', 'id' => 5],
        'prognosis' => ['code' => 'prognosis', 'id' => 6],
        'result' => ['code' => 'result', 'id' => 7],
    ];

    protected $data;

    protected $arTeams = [];

    protected $arError = [];

    protected $arFill;

    protected $arNumbertoMatchId = [];

    protected $arUserPrognosis = [];
    protected $arUserResults = [];
    protected $ratioStatsByMatch = [];
    protected $betStatsByMatch = [];

    protected $arResult = [
        'status' => 'ok'
    ];

    protected $arPeriod = [
        'past' => ['period' => 'past', 'title' => 'Прошедшие', 'visible' => false, 'count' => 0],
        'recent' => ['period' => 'recent', 'title' => 'Недавние', 'visible' => false, 'count' => 0],
        'yesterday' => ['period' => 'yesterday', 'title' => 'Вчера', 'visible' => false, 'count' => 0],
        'today' => ['period' => 'today', 'title' => 'Сегодня', 'visible' => true, 'count' => 0],
        'tomorrow' => ['period' => 'tomorrow', 'title' => 'Завтра', 'visible' => false, 'count' => 0],
        'nearest' => ['period' => 'nearest', 'title' => 'Ближайшие', 'visible' => false, 'count' => 0],
        'future' => ['period' => 'future', 'title' => 'Будущие', 'visible' => false, 'count' => 0],
    ];

    public function __construct($data)
    {
        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        }

        $this->data = $data;

        if ($this->data['userToken']) {
            $this->data['userId'] = (new GetUserIdForToken($data['userToken']))->getId();
        }

        $this->getUserPrognos();
        $this->getUserResult();

        $this->arTeams = (new GetFootballTeams())->result();

        $this->prefetchRatioStatsForEvent();
        $this->getMatchOfData();

        $this->reverseArrayOldMatches();

        if ($this->arFill) {
            $this->setResult('ok', '', $this->arFill);
        } else {
            $this->setResult('error', 'Ошибка запроса');
        }

    }

    protected function getMatchOfData()
    {
        $arFilter = [
            'IBLOCK_ID' => $this->arIbs['matches']['id'],
            'PROPERTY_EVENTS' => $this->data['events']
        ];

        $response = CIBlockElement::GetList(
            ["DATE_ACTIVE_FROM" => "ASC", "created" => "ASC"],
            $arFilter,
            false,
            [],
            [
                "ID",
                "ACTIVE",
                "DATE_ACTIVE_FROM",
                "PROPERTY_home",
                "PROPERTY_goal_home",
                "PROPERTY_guest",
                "PROPERTY_goal_guest",
                "PROPERTY_group",
                "PROPERTY_stage",
                "PROPERTY_number",
                "PROPERTY_events",
                "PROPERTY_home_label",
                "PROPERTY_guest_label",
            ]
        );

        $rows = [];
        while ($res = $response->GetNext()) {
            $rows[] = $res;
        }

        $matchMeta = [];
        foreach ($rows as $res) {
            $matchMeta[(int)$res['ID']] = ['active' => (string)$res['ACTIVE']];
        }

        if ($matchMeta && Loader::includeModule('prognos9ys.main')) {
            try {
                $this->betStatsByMatch = (new \Prognos9ys\Main\Service\Game\BetService())
                    ->getMatchBetCountsForMatches($matchMeta);
            } catch (\Throwable $exception) {
                $this->betStatsByMatch = [];
            }
        }

        foreach ($rows as $res) {
            $this->arNumberToMatchId[$res["PROPERTY_NUMBER_VALUE"]] = $res['ID'];
            $el = [];

            $date = explode("+", ConvertDateTime($res["ACTIVE_FROM"], "DD.MM+HH:Mi"));

            $el["date"] = $date[0];
            $el["time"] = $date[1];

            $el["active"] = $res["ACTIVE"];
            $el["id"] = (int)$res["ID"];
            $el["number"] = $res["PROPERTY_NUMBER_VALUE"];
            $el["event"] = $res["PROPERTY_EVENTS_VALUE"];

            $el["teams"]["home"] = $this->getTeamData(
                $this->arTeams[$res["PROPERTY_HOME_VALUE"]] ?? null,
                $res["PROPERTY_GOAL_HOME_VALUE"],
                (string)($res["PROPERTY_HOME_LABEL_VALUE"] ?? ''),
                (int)$res["PROPERTY_HOME_VALUE"]
            );
            $el["teams"]["guest"] = $this->getTeamData(
                $this->arTeams[$res["PROPERTY_GUEST_VALUE"]] ?? null,
                $res["PROPERTY_GOAL_GUEST_VALUE"],
                (string)($res["PROPERTY_GUEST_LABEL_VALUE"] ?? ''),
                (int)$res["PROPERTY_GUEST_VALUE"]
            );

            $el["send_info"]["send_time"] = $this->arUserPrognosis[$res["ID"]] ?? '';
            $el["send_info"]["score_result"] = $this->arUserResults[$res["ID"]] ?? '';
            $el["bet_reward"] = [
                'status' => '',
                'payout' => 0.0,
            ];

            $matchId = (int)$res['ID'];
            $el["ratio"] = $this->buildRatioOdds($matchId);
            $betRatio = $this->buildBetRatio($matchId);
            $el["bet_ratio"] = $betRatio['odds'];
            $el["bet_ratio_meta"] = $betRatio['meta'];

            $period = $this->fillSectionArray($res["DATE_ACTIVE_FROM"]);

            $this->arFill[$period['period']]['items'][$el["date"]][$el["number"]] = $el;
            $this->arFill[$period['period']]['info'] = $period;

        }

        foreach ($this->arFill as $section => $arr) {
            if ($section === 'nearest') {
//                krsort($this->arFill[$section]['items']);
            }
            if ($section === 'past') {
//                $this->arFill[$section]['items'] = array_reverse($this->arFill[$section]['items']);
            }

            $this->checkVisible();

            $this->arFill[$section]['info'] = $this->arPeriod[$section];

        }

    }

    protected function prefetchRatioStatsForEvent(): void
    {
        if (empty($this->data['events'])) {
            return;
        }

        $response = CIBlockElement::GetList(
            [],
            [
                'IBLOCK_ID' => $this->arIbs['prognosis']['id'],
                'PROPERTY_EVENTS' => $this->data['events'],
            ],
            false,
            false,
            [
                'PROPERTY_match_id',
                'PROPERTY_diff',
            ]
        );

        while ($res = $response->GetNext()) {
            $matchId = (int)$res['PROPERTY_MATCH_ID_VALUE'];
            if ($matchId <= 0) {
                continue;
            }

            if (!isset($this->ratioStatsByMatch[$matchId])) {
                $this->ratioStatsByMatch[$matchId] = [
                    'plus' => 0,
                    'equal' => 0,
                    'minus' => 0,
                    'count' => 0,
                ];
            }

            $diff = (int)$res['PROPERTY_DIFF_VALUE'];
            if ($diff > 0) {
                $this->ratioStatsByMatch[$matchId]['plus'] += 1;
            } elseif ($diff === 0) {
                $this->ratioStatsByMatch[$matchId]['equal'] += 1;
            } else {
                $this->ratioStatsByMatch[$matchId]['minus'] += 1;
            }

            $this->ratioStatsByMatch[$matchId]['count'] += 1;
        }
    }

    /**
     * @return array<int, array{name:string,count:string|int}>
     */
    protected function buildRatioOdds(int $matchId): array
    {
        $arRatio = $this->ratioStatsByMatch[$matchId] ?? [
            'plus' => 0,
            'equal' => 0,
            'minus' => 0,
            'count' => 0,
        ];

        return [
            0 => ['name' => 'п1', 'count' => number_format(($arRatio['count'] + 1) / ($arRatio['plus'] + 1), 2)],
            1 => ['name' => 'н', 'count' => number_format(($arRatio['count'] + 1) / ($arRatio['equal'] + 1), 2)],
            2 => ['name' => 'п2', 'count' => number_format(($arRatio['count'] + 1) / ($arRatio['minus'] + 1), 2)],
            3 => ['name' => 'Σ', 'count' => $arRatio['count']],
        ];
    }

    protected function buildBetRatio(int $matchId): array
    {
        if ($matchId <= 0 || !Loader::includeModule('prognos9ys.main')) {
            return [
                'odds' => [],
                'meta' => [
                    'mode' => 'financial',
                    'financial_count' => 0,
                ],
            ];
        }

        try {
            $bet = $this->betStatsByMatch[$matchId] ?? [
                'plus' => 0.0,
                'equal' => 0.0,
                'minus' => 0.0,
                'count' => 0,
            ];

            $mode = 'financial';
            $plus = (float)$bet['plus'];
            $equal = (float)$bet['equal'];
            $minus = (float)$bet['minus'];

            $hybridMinFinancial = 10;
            if (($bet['count'] ?? 0) < $hybridMinFinancial) {
                $classic = $this->ratioStatsByMatch[$matchId] ?? null;
                if ($classic && (int)$classic['count'] > 0) {
                    $mode = 'hybrid';
                    $fallbackWeight = 0.3;
                    $plus += (float)$classic['plus'] * $fallbackWeight;
                    $equal += (float)$classic['equal'] * $fallbackWeight;
                    $minus += (float)$classic['minus'] * $fallbackWeight;
                }
            }

            $count = $plus + $equal + $minus;
            $odds = [
                0 => ['name' => 'п1', 'count' => number_format(($count + 1) / ($plus + 1), 2)],
                1 => ['name' => 'н', 'count' => number_format(($count + 1) / ($equal + 1), 2)],
                2 => ['name' => 'п2', 'count' => number_format(($count + 1) / ($minus + 1), 2)],
                3 => ['name' => 'Σ', 'count' => (int)round($count)],
            ];

            return [
                'odds' => $odds,
                'meta' => [
                    'mode' => $mode,
                    'financial_count' => (int)$bet['count'],
                ],
            ];
        } catch (\Throwable $exception) {
            return [
                'odds' => [],
                'meta' => [
                    'mode' => 'financial',
                    'financial_count' => 0,
                ],
            ];
        }
    }

    protected function fillSectionArray($date)
    {

        $now = date(\CDatabase::DateFormatToPHP("DD.MM.YYYY"), time());
        $now = date_create($now);

        $dateMatch = date_create(explode(' ', $date)[0]);

        $interval = date_diff($now, $dateMatch);

        $intervalDay = +$interval->format('%R%a');

        if ($intervalDay === 0) {
            $this->arPeriod['today']['count'] += 1;
            $arr = $this->arPeriod['today'];
        }

        if ($intervalDay === 1) {
            $this->arPeriod['tomorrow']['count'] += 1;
            $arr = $this->arPeriod['tomorrow'];
        }

        if ($intervalDay === -1) {
            $this->arPeriod['yesterday']['count'] += 1;
            $arr = $this->arPeriod['yesterday'];
        }

        if ($intervalDay > 1 && $intervalDay < 6) {
            $this->arPeriod['nearest']['count'] += 1;
            $arr = $this->arPeriod['nearest'];
        }

        if ($intervalDay > 5) {
            $this->arPeriod['future']['count'] += 1;
            $arr = $this->arPeriod['future'];
        }

        if ($intervalDay < -1 && $intervalDay > -6 ) {
            $this->arPeriod['recent']['count'] += 1;
            $arr = $this->arPeriod['recent'];
        }

        if ($intervalDay < -5) {
            $this->arPeriod['past']['count'] += 1;
            $arr = $this->arPeriod['past'];
        }

        $this->checkVisible();

        return $arr;

    }

    protected function checkVisible(){
        $this->visibleReset();

        if($this->arPeriod['today']['count']> 0) {
            $this->arPeriod['today']['visible'] = true;
        } elseif($this->arPeriod['tomorrow']['count']> 0) {
            $this->arPeriod['tomorrow']['visible'] = true;
        } elseif($this->arPeriod['yesterday']['count']> 0) {
            $this->arPeriod['yesterday']['visible'] = true;
        } elseif($this->arPeriod['nearest']['count']> 0) {
            $this->arPeriod['nearest']['visible'] = true;
        }

    }

    protected function visibleReset(){
        foreach ($this->arPeriod as $status=>$period){
            $this->arPeriod[$status]['visible'] = false;
        }
    }

    protected function reverseArrayOldMatches()
    {
//        if (count($this->arResult['res']['recent']['matches']))
//            $this->arResult['res']['recent']['matches'] = array_reverse($this->arResult['res']['recent']['matches'], true);
//        if (count($this->arResult['res']['past']['matches']))
//            $this->arResult['res']['past']['matches'] = array_reverse($this->arResult['res']['past']['matches'], true);
    }

    protected function getUserPrognos()
    {

        $arFilter = [
            'IBLOCK_ID' => $this->arIbs['prognosis']['id'],
            'PROPERTY_EVENTS' => $this->data['events'],
            'PROPERTY_USER_ID' => $this->data['userId']
        ];

        $response = CIBlockElement::GetList(
            [],
            $arFilter,
            false,
            [],
            [
                "PROPERTY_match_id",
                "DATE_ACTIVE_FROM",
            ]
        );

        while ($res = $response->GetNext()) {
            $this->arUserPrognosis[$res["PROPERTY_MATCH_ID_VALUE"]] = ConvertDateTime($res["DATE_ACTIVE_FROM"], "DD.MM HH:Mi");
        }

    }

    protected function getUserResult()
    {
        $arFilter = [
            'IBLOCK_ID' => $this->arIbs['result']['id'],
            'PROPERTY_EVENTS' => $this->data['events'],
            'PROPERTY_USER_ID' => $this->data['userId']
        ];

        $response = CIBlockElement::GetList(
            [],
            $arFilter,
            false,
            [],
            [
                "PROPERTY_all",
                "PROPERTY_match_id",

            ]
        );

        while ($res = $response->GetNext()) {
            $this->arUserResults[$res['PROPERTY_MATCH_ID_VALUE']] = $res["PROPERTY_ALL_VALUE"];
        }
    }

    protected function getTeamData($data, $goals, string $slotLabel = '', int $teamId = 0): array
    {
        if ($teamId <= 0 && is_array($data) && !empty($data['ID'])) {
            $teamId = (int)$data['ID'];
        }

        return PlayoffSlotHelper::teamPayload($teamId, is_array($data) ? $data : null, $goals, $slotLabel);
    }

    protected function setResult($status, $mes, $info = '')
    {
        $this->arResult['status'] = $status;
        $this->arResult['mes'] = $mes;
        if ($info) $this->arResult['info'] = $info;
    }

    public function result()
    {
        return $this->arResult;
    }

    public function getNearest()
    {
        foreach ($this->arPeriod as $status=>$el){
            if($el['visible'] === true) {
                return $this->arResult['info'][$status];
            }
        }
    }
}