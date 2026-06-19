<?php

use Bitrix\Main\Loader;
use Prognos9ys\Main\Model\Repository\Cs2IblockRegistry;

class Cs2MatchLoadInfo extends PrognosisGiveInfo
{
    protected $userId;
    protected $eventId;
    protected $number;

    protected $arTeams = [];
    protected $arResult;

    /** @var array<string, array{code: string, id: int}> */
    protected $arIbs = [];

    public function __construct($data)
    {
        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        }

        $registry = new Cs2IblockRegistry();
        $ids = $registry->legacyIds();

        $this->arIbs = [
            'events' => ['code' => 'events', 'id' => 1],
            'matches' => ['code' => Cs2IblockRegistry::IBLOCK_MATCHES, 'id' => $ids['matches']],
            'prognosis' => ['code' => Cs2IblockRegistry::IBLOCK_PROGNOSIS, 'id' => $ids['prognosis']],
            'result' => ['code' => Cs2IblockRegistry::IBLOCK_RESULT, 'id' => $ids['result']],
        ];

        if ($data['eventId']) {
            $this->eventId = $data['eventId'];
        }

        if ($data['userToken']) {
            $this->userId = (new GetUserIdForToken($data['userToken']))->getId();
        }

        $this->number = $data['number'] ?? '';
        $this->arTeams = (new GetCs2Teams())->result();
        $this->getMatchStaticData();

        if ($this->arResult) {
            $this->setResult('ok', '', $this->arResult);
        }
    }

    protected function getMatchStaticData(): void
    {
        if ((int)($this->arIbs['matches']['id'] ?? 0) <= 0) {
            return;
        }

        $arFilter = [
            'IBLOCK_ID' => $this->arIbs['matches']['id'],
            'PROPERTY_EVENTS' => $this->eventId,
            'PROPERTY_NUMBER' => $this->number,
        ];

        $res = CIBlockElement::GetList(
            ['DATE_ACTIVE_FROM' => 'ASC', 'created' => 'ASC'],
            $arFilter,
            false,
            [],
            [
                'ID',
                'ACTIVE',
                'DATE_ACTIVE_FROM',
                'PROPERTY_home',
                'PROPERTY_guest',
                'PROPERTY_group',
                'PROPERTY_stage',
                'PROPERTY_number',
                'PROPERTY_events',
                'PROPERTY_step',
                'PROPERTY_round',
                'PROPERTY_bo_format',
            ]
        )->GetNext();

        if (!$res) {
            return;
        }

        $el = [];
        $date = explode('+', ConvertDateTime($res['DATE_ACTIVE_FROM'], 'DD.MM+HH:Mi'));

        $el['date'] = $date[0];
        $el['time'] = $date[1];
        $el['active'] = $res['ACTIVE'];
        $el['number'] = $res['PROPERTY_NUMBER_VALUE'];
        $el['step'] = $res['PROPERTY_STEP_VALUE'];
        $el['tur'] = $res['PROPERTY_ROUND_VALUE'];
        $el['event'] = $this->eventId;
        $el['id'] = $res['ID'];
        $el['stage'] = $res['PROPERTY_STAGE_VALUE'];
        $el['bo_format'] = $this->normalizeBoFormat($res['PROPERTY_BO_FORMAT_VALUE'] ?? 'bo3');
        $el['sport'] = 'cs2';

        $homeTeamId = (int)$res['PROPERTY_HOME_VALUE'];
        $guestTeamId = (int)$res['PROPERTY_GUEST_VALUE'];
        $matchNumber = (int)$res['PROPERTY_NUMBER_VALUE'];

        $el['home'] = $this->getTeamData($this->arTeams[$homeTeamId] ?? []);
        $el['guest'] = $this->getTeamData($this->arTeams[$guestTeamId] ?? []);

        if (Loader::includeModule('prognos9ys.main')) {
            $forms = (new \Prognos9ys\Main\Service\Football\TeamFormService())->getTeamForms(
                (int)$this->eventId,
                $homeTeamId,
                $guestTeamId,
                $matchNumber
            );
            $el['home']['form'] = $forms['home'];
            $el['guest']['form'] = $forms['guest'];
        }

        $el['prognosis'] = $this->getRecordData($this->arIbs['prognosis']['id'], (int)$el['id']);
        $el['match_result'] = $this->getRecordData($this->arIbs['matches']['id'], (int)$el['id']);
        $el['prog_result'] = $this->getRecordData($this->arIbs['result']['id'], (int)$el['id']);
        $el['bet_reward'] = $this->getUserBetReward((int)$el['id']);
        $el['max'] = $this->getCountMatches();

        $this->arResult = $el;
    }

    protected function getTeamData($data): array
    {
        return [
            'flag' => $data['flag'] ?? '',
            'name' => $data['NAME'] ?? '',
        ];
    }

    protected function getCountMatches(): int
    {
        $arCount = [];
        $arFilter = [
            'IBLOCK_ID' => $this->arIbs['matches']['id'],
            'PROPERTY_EVENTS' => $this->eventId,
        ];

        $recourse = CIBlockElement::GetList(
            ['DATE_ACTIVE_FROM' => 'ASC', 'created' => 'ASC'],
            $arFilter,
            false,
            [],
            ['ID']
        );

        while ($res = $recourse->GetNext()) {
            $arCount[] = $res['ID'];
        }

        return count($arCount);
    }

    protected function getRecordData(int $ib, int $matchId): ?array
    {
        $matchesIb = (int)$this->arIbs['matches']['id'];
        $prognosisIb = (int)$this->arIbs['prognosis']['id'];
        $resultIb = (int)$this->arIbs['result']['id'];

        $arFilter = ['IBLOCK_ID' => $ib];
        $arSelect = [
            'ID',
            'TIMESTAMP_X',
            'PROPERTY_maps_home',
            'PROPERTY_maps_guest',
            'PROPERTY_result',
            'PROPERTY_diff',
            'PROPERTY_sum',
            'PROPERTY_opening_pct',
            'PROPERTY_pistol_pct',
            'PROPERTY_clutches_home',
            'PROPERTY_clutches_guest',
            'PROPERTY_map_scores',
            'PROPERTY_number',
            'PROPERTY_match_id',
        ];

        if ($ib === $matchesIb) {
            $arFilter['ID'] = $matchId;
            $arSelect[] = 'PROPERTY_stage';
        } elseif ($ib === $prognosisIb) {
            $arFilter['PROPERTY_MATCH_ID'] = $matchId;
            $arFilter['PROPERTY_USER_ID'] = $this->userId;
            $arSelect[] = 'TIMESTAMP_X';
        } elseif ($ib === $resultIb) {
            $arFilter['PROPERTY_MATCH_ID'] = $matchId;
            $arFilter['PROPERTY_USER_ID'] = $this->userId;
            $arSelect[] = 'PROPERTY_all';
            $arSelect[] = 'PROPERTY_score';
        } else {
            return null;
        }

        $res = CIBlockElement::GetList([], $arFilter, false, [], $arSelect)->GetNext();

        if (!$res) {
            return null;
        }

        return $this->formatRecord($res, $ib, $matchesIb, $resultIb);
    }

    protected function formatRecord(array $res, int $ib, int $matchesIb, int $resultIb): array
    {
        $mapsHome = $res['PROPERTY_MAPS_HOME_VALUE'];
        $mapsGuest = $res['PROPERTY_MAPS_GUEST_VALUE'];
        $openingPct = $res['PROPERTY_OPENING_PCT_VALUE'];

        $arr = [
            'id' => $res['ID'],
            'maps_home' => $mapsHome,
            'maps_guest' => $mapsGuest,
            'goal_home' => $mapsHome,
            'goal_guest' => $mapsGuest,
            'goal_score' => $ib !== $resultIb
                ? $mapsHome . ' - ' . $mapsGuest
                : ($res['PROPERTY_SCORE_VALUE'] ?? null),
            'maps_score' => $mapsHome !== null && $mapsHome !== ''
                ? $mapsHome . ' - ' . $mapsGuest
                : null,
            'result' => $res['PROPERTY_RESULT_VALUE'],
            'sum' => $res['PROPERTY_SUM_VALUE'],
            'diff' => $res['PROPERTY_DIFF_VALUE'],
            'opening_pct' => $openingPct,
            'domination' => $openingPct,
            'opening_pct_guest' => $openingPct !== null && $openingPct !== ''
                ? 100 - (int)$openingPct
                : 50,
            'domination2' => $openingPct !== null && $openingPct !== ''
                ? $openingPct . ' - ' . (100 - (int)$openingPct)
                : null,
            'pistol_pct' => $res['PROPERTY_PISTOL_PCT_VALUE'],
            'corner' => $res['PROPERTY_PISTOL_PCT_VALUE'],
            'pistol_pct_guest' => $res['PROPERTY_PISTOL_PCT_VALUE'] !== null && $res['PROPERTY_PISTOL_PCT_VALUE'] !== ''
                ? 100 - (int)$res['PROPERTY_PISTOL_PCT_VALUE']
                : 50,
            'clutches_home' => $res['PROPERTY_CLUTCHES_HOME_VALUE'],
            'clutches_guest' => $res['PROPERTY_CLUTCHES_GUEST_VALUE'],
            'yellow' => $res['PROPERTY_CLUTCHES_HOME_VALUE'],
            'red' => $res['PROPERTY_CLUTCHES_GUEST_VALUE'],
            'map_scores' => $this->decodeMapScores($res['PROPERTY_MAP_SCORES_VALUE'] ?? ''),
            'offside' => $res['PROPERTY_MAP_SCORES_VALUE'] ?? '',
            'stage' => $res['PROPERTY_STAGE_VALUE'] ?? null,
            'number' => $res['PROPERTY_NUMBER_VALUE'] ?? null,
            'match_id' => $res['PROPERTY_MATCH_ID_VALUE'] ?? null,
            'all' => $res['PROPERTY_ALL_VALUE'] ?? null,
            'score' => $res['PROPERTY_SCORE_VALUE'] ?? null,
        ];

        if ($ib === (int)$this->arIbs['prognosis']['id']) {
            $arr['time_send'] = $res['TIMESTAMP_X'] ?? '';
        }

        return $arr;
    }

    protected function getUserBetReward(int $matchId): array
    {
        $default = [
            'status' => '',
            'payout' => 0.0,
        ];

        if ($matchId <= 0 || (int)$this->userId <= 0 || !Loader::includeModule('prognos9ys.main')) {
            return $default;
        }

        try {
            $repository = new \Prognos9ys\Main\Model\Repository\GameEconomyRepository();
            $bet = $repository->getMatchBet((int)$this->userId, $matchId);

            if (!$bet) {
                return $default;
            }

            return [
                'status' => (string)($bet['UF_STATUS'] ?? ''),
                'payout' => round((float)($bet['UF_PAYOUT'] ?? 0), 1),
            ];
        } catch (\Throwable $exception) {
            return $default;
        }
    }

    protected function decodeMapScores($raw): array
    {
        if (!$raw) {
            return [];
        }

        if (is_array($raw)) {
            return $raw;
        }

        $decoded = json_decode((string)$raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    protected function normalizeBoFormat($value): string
    {
        $value = strtolower(trim((string)$value));

        if (in_array($value, ['bo1', 'bo3', 'bo5'], true)) {
            return $value;
        }

        if (strpos($value, '1') !== false) {
            return 'bo1';
        }

        if (strpos($value, '5') !== false) {
            return 'bo5';
        }

        return 'bo3';
    }
}
