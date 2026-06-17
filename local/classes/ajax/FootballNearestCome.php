<?php

use Bitrix\Main\Loader;
use Prognos9ys\Main\Service\Game\MatchBetRewardEnricher;
use Prognos9ys\Main\Service\Game\MatchTreasureEnricher;
use Prognos9ys\Main\Service\Game\MatchXpEnricher;

class FootballNearestCome extends PrognosisGiveInfo
{
    protected $data;

    protected $arTeams;

    protected $arResult;

    /** @var array<int, string> */
    protected $userPrognosisByMatch = [];

    /** @var array<int, int|string> */
    protected $userResultsByMatch = [];

    /** @var array<int, array{plus:int,equal:int,minus:int,count:int}> */
    protected $ratioStatsByMatch = [];

    protected $arIBs = [
        'matches' => ['code' => 'matches', 'id' => 2],
        'prognosis' => ['code' => 'prognosis', 'id' => 6],
        'result' => ['code' => 'result', 'id' => 7],
    ];

    public function __construct($data)
    {
        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        }

        $this->data = $data;

        $this->arTeams = (new GetFootballTeams())->result();

        $this->data['userId'] = (new GetUserIdForToken($this->data['userToken']))->getID();

        $this->getMatch();

        if ((int)$this->data['userId'] > 0 && !empty($this->arResult) && is_array($this->arResult)) {
            (new MatchXpEnricher())->enrichEventMatches((int)$this->data['userId'], $this->arResult);
            (new MatchBetRewardEnricher())->enrichEventMatches((int)$this->data['userId'], $this->arResult);
            (new MatchTreasureEnricher())->enrichEventMatches((int)$this->data['userId'], $this->arResult);
        }

        foreach ($this->arResult as $period=>$ar){
            $this->arResult[$period]['info'] = $this->arPeriod[$period];
        }

        $this->setResult('ok', '', $this->arResult);

    }

    protected function getMatch(){
        $arFilter = [
            "IBLOCK_ID" => $this->arIBs['matches']['id'],
        ];
        $arFilter[">=DATE_ACTIVE_FROM"] = (new DateTime())->modify('-2 day')->format('d.m.Y H:i:s');
        $arFilter["<=DATE_ACTIVE_FROM"] = (new DateTime())->modify('+2 day')->format('d.m.Y H:i:s');

        $response = CIBlockElement::GetList(
            [],
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
            ]
        );

        $rows = [];
        while ($res = $response->GetNext()) {
            $rows[] = $res;
        }

        $matchIds = array_map(static fn(array $row): int => (int)$row['ID'], $rows);
        $this->prefetchUserData($matchIds);
        $this->prefetchRatioStats($matchIds);

        foreach ($rows as $res) {

            $el = [];

            $convert = $this->convertData($res["DATE_ACTIVE_FROM"]);

            $el["date"] = $convert['date'];
            $el["time"] = $convert['time'];
            $el["id"] = (int)$res["ID"];

            $el["active"] = $res["ACTIVE"];
            $el["number"] = $res["PROPERTY_NUMBER_VALUE"];
            $el["event"] = $res["PROPERTY_EVENTS_VALUE"];

            $el["teams"]["home"] = $this->arTeams[$res["PROPERTY_HOME_VALUE"]];
            $el["teams"]["guest"] = $this->arTeams[$res["PROPERTY_GUEST_VALUE"]];

            $el["teams"]["home"]["goals"] = $res["PROPERTY_GOAL_HOME_VALUE"] ?? 0;
            $el["teams"]["guest"]["goals"] = $res["PROPERTY_GOAL_GUEST_VALUE"] ?? 0;

            $matchId = (int)$res['ID'];
            $el["send_info"]["send_time"] = $this->userPrognosisByMatch[$matchId] ?? null;
            $set = $el["send_info"]["send_time"] ? 1 : 0;

            $el["send_info"]["score_result"] = $this->userResultsByMatch[$matchId] ?? 0;

            $el["ratio"] = $this->buildRatioOdds($matchId);

            $arDataSort = $this->fillSectionArray($res["DATE_ACTIVE_FROM"], $set);

            $this->arResult[$arDataSort['period']]['items']['football'][$res["ID"]] = $el;

        }
    }

    /**
     * @param int[] $matchIds
     */
    protected function prefetchUserData(array $matchIds): void
    {
        $userId = (int)($this->data['userId'] ?? 0);
        if ($userId <= 0 || !$matchIds) {
            return;
        }

        $prognosisResponse = CIBlockElement::GetList(
            [],
            [
                'IBLOCK_ID' => $this->arIBs['prognosis']['id'],
                'PROPERTY_user_id' => $userId,
                'PROPERTY_match_id' => $matchIds,
            ],
            false,
            false,
            [
                'TIMESTAMP_X',
                'PROPERTY_match_id',
            ]
        );

        while ($res = $prognosisResponse->GetNext()) {
            $matchId = (int)$res['PROPERTY_MATCH_ID_VALUE'];
            if ($matchId <= 0) {
                continue;
            }

            $convert = $this->convertData($res['TIMESTAMP_X']);
            $this->userPrognosisByMatch[$matchId] = $convert['date'] . ' ' . $convert['time'];
        }

        $resultResponse = CIBlockElement::GetList(
            [],
            [
                'IBLOCK_ID' => $this->arIBs['result']['id'],
                'PROPERTY_user_id' => $userId,
                'PROPERTY_match_id' => $matchIds,
            ],
            false,
            false,
            [
                'PROPERTY_match_id',
                'PROPERTY_all',
            ]
        );

        while ($res = $resultResponse->GetNext()) {
            $matchId = (int)$res['PROPERTY_MATCH_ID_VALUE'];
            if ($matchId <= 0) {
                continue;
            }

            $this->userResultsByMatch[$matchId] = $res['PROPERTY_ALL_VALUE'] ?? 0;
        }
    }

    /**
     * @param int[] $matchIds
     */
    protected function prefetchRatioStats(array $matchIds): void
    {
        if (!$matchIds) {
            return;
        }

        $response = CIBlockElement::GetList(
            [],
            [
                'IBLOCK_ID' => $this->arIBs['prognosis']['id'],
                'PROPERTY_match_id' => $matchIds,
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

    protected function getPrognosis($id)
    {
        $arFilter = [
            "IBLOCK_ID" => $this->arIBs['prognosis']['id'],
            'PROPERTY_user_id' => $this->data['userId'],
            'PROPERTY_match_id' => $id,
        ];

        $response = CIBlockElement::GetList(
            [],
            $arFilter,
            false,
            [],
            [
                'TIMESTAMP_X',
            ]
        )->GetNext();

        if($response){
            $convert = $this->convertData($response['TIMESTAMP_X']);

            return $convert['date'] . ' ' . $convert['time'];
        } else {
            return null;
        }



    }

    protected function getUserResult($id)
    {
        $arFilter = [
            "IBLOCK_ID" => $this->arIBs['result']['id'],
            'PROPERTY_match_id' => $id,
            'PROPERTY_user_id' => $this->data['userId']
        ];

        $res = CIBlockElement::GetList(
            [],
            $arFilter,
            false,
            [],
            [
                'PROPERTY_all',
            ]
        )->GetNext();

        return $res['PROPERTY_ALL_VALUE'] ?? 0;
    }




}