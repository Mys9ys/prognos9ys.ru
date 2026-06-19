<?php

use Bitrix\Main\Loader;
use Prognos9ys\Main\Model\Repository\Cs2IblockRegistry;

class Cs2HandlerClass extends FootballHandlerClass
{
    public function __construct($data)
    {
        $registry = new Cs2IblockRegistry();
        $ids = $registry->legacyIds();

        $this->arIbs = [
            'events' => ['code' => 'events', 'id' => 1],
            'group' => ['code' => 'group', 'id' => 5],
            'matches' => ['code' => Cs2IblockRegistry::IBLOCK_MATCHES, 'id' => $ids['matches']],
            'prognosis' => ['code' => Cs2IblockRegistry::IBLOCK_PROGNOSIS, 'id' => $ids['prognosis']],
            'result' => ['code' => Cs2IblockRegistry::IBLOCK_RESULT, 'id' => $ids['result']],
        ];

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
        $this->arTeams = (new GetCs2Teams())->result();
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
            'PROPERTY_EVENTS' => $this->data['events'],
        ];

        $response = CIBlockElement::GetList(
            ['DATE_ACTIVE_FROM' => 'ASC', 'created' => 'ASC'],
            $arFilter,
            false,
            [],
            [
                'ID',
                'ACTIVE',
                'DATE_ACTIVE_FROM',
                'PROPERTY_home',
                'PROPERTY_maps_home',
                'PROPERTY_guest',
                'PROPERTY_maps_guest',
                'PROPERTY_group',
                'PROPERTY_stage',
                'PROPERTY_number',
                'PROPERTY_events',
                'PROPERTY_bo_format',
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
            $this->arNumberToMatchId[$res['PROPERTY_NUMBER_VALUE']] = $res['ID'];
            $el = [];

            $date = explode('+', ConvertDateTime($res['DATE_ACTIVE_FROM'], 'DD.MM+HH:Mi'));

            $el['date'] = $date[0];
            $el['time'] = $date[1];
            $el['active'] = $res['ACTIVE'];
            $el['id'] = (int)$res['ID'];
            $el['number'] = $res['PROPERTY_NUMBER_VALUE'];
            $el['event'] = $res['PROPERTY_EVENTS_VALUE'];
            $el['sport'] = 'cs2';
            $el['link_prefix'] = 'cs2';
            $el['bo_format'] = $res['PROPERTY_BO_FORMAT_VALUE'] ?? 'bo3';

            $el['teams']['home'] = $this->getTeamData(
                $this->arTeams[$res['PROPERTY_HOME_VALUE']],
                $res['PROPERTY_MAPS_HOME_VALUE']
            );
            $el['teams']['guest'] = $this->getTeamData(
                $this->arTeams[$res['PROPERTY_GUEST_VALUE']],
                $res['PROPERTY_MAPS_GUEST_VALUE']
            );

            $el['send_info']['send_time'] = $this->arUserPrognosis[$res['ID']] ?? '';
            $el['send_info']['score_result'] = $this->arUserResults[$res['ID']] ?? '';
            $el['bet_reward'] = ['status' => '', 'payout' => 0.0];

            $matchId = (int)$res['ID'];
            $el['ratio'] = $this->buildRatioOdds($matchId);
            $betRatio = $this->buildBetRatio($matchId);
            $el['bet_ratio'] = $betRatio['odds'];
            $el['bet_ratio_meta'] = $betRatio['meta'];

            $period = $this->fillSectionArray($res['DATE_ACTIVE_FROM']);
            $this->arFill[$period['period']]['items'][$el['date']][$el['number']] = $el;
            $this->arFill[$period['period']]['info'] = $period;
        }

        foreach ($this->arFill as $section => $arr) {
            $this->checkVisible();
            $this->arFill[$section]['info'] = $this->arPeriod[$section];
        }
    }
}
