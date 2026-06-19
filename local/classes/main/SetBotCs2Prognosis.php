<?php

use Bitrix\Main\Loader;
use Prognos9ys\Main\Model\Repository\Cs2IblockRegistry;

class SetBotCs2Prognosis
{
    protected int $matchesIb = 0;
    protected int $prognosisIb = 0;

    protected $arBots = [];
    protected $arActiveMatches = [];
    protected $arEmptyPrognosis = [];

    public function __construct()
    {
        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        }

        $registry = new Cs2IblockRegistry();
        $ids = $registry->legacyIds();
        $this->matchesIb = (int)$ids['matches'];
        $this->prognosisIb = (int)$ids['prognosis'];

        if ($this->matchesIb <= 0 || $this->prognosisIb <= 0) {
            return;
        }

        $this->getBotArray();
        $this->getActiveMatches();

        if ($this->arBots && $this->arActiveMatches) {
            $this->fillEmptyBotPrognosis();
        }
    }

    protected function getBotArray(): void
    {
        $this->arBots = CGroup::GetGroupUser(6);
    }

    protected function getActiveMatches(): void
    {
        $now = new DateTime();
        $from = (clone $now)->modify('-1 day')->format('d.m.Y H:i:s');
        $to = (clone $now)->modify('+2 day')->format('d.m.Y H:i:s');

        $response = CIBlockElement::GetList(
            ['DATE_ACTIVE_FROM' => 'ASC', 'created' => 'ASC'],
            [
                'IBLOCK_ID' => $this->matchesIb,
                'ACTIVE' => 'Y',
                '>=DATE_ACTIVE_FROM' => $from,
                '<=DATE_ACTIVE_FROM' => $to,
            ],
            false,
            [],
            [
                'ID',
                'NAME',
                'PROPERTY_number',
                'PROPERTY_events',
                'PROPERTY_bo_format',
            ]
        );

        while ($res = $response->GetNext()) {
            $this->arActiveMatches[] = $res;
        }
    }

    protected function fillEmptyBotPrognosis(): void
    {
        foreach ($this->arBots as $botId) {
            $botId = (int)$botId;
            if ($botId <= 0) {
                continue;
            }

            foreach ($this->arActiveMatches as $match) {
                $matchId = (int)$match['ID'];
                $exists = CIBlockElement::GetList(
                    [],
                    [
                        'IBLOCK_ID' => $this->prognosisIb,
                        'PROPERTY_MATCH_ID' => $matchId,
                        'PROPERTY_USER_ID' => $botId,
                    ],
                    false,
                    [],
                    ['ID']
                )->GetNext();

                if ($exists) {
                    continue;
                }

                $payload = [
                    17 => $matchId,
                    30 => $match['PROPERTY_NUMBER_VALUE'],
                    31 => $botId,
                    52 => $match['PROPERTY_EVENTS_VALUE'],
                    'bo_format' => $match['PROPERTY_BO_FORMAT_VALUE'] ?? 'bo3',
                ];

                $this->saveBotPrognosis($payload);
            }
        }
    }

    protected function saveBotPrognosis(array $arr): void
    {
        $boFormat = (string)($arr['bo_format'] ?? 'bo3');
        unset($arr['bo_format']);

        $generator = new GenValuesBotCs2($boFormat);
        $props = array_replace($generator->getArFields(), $arr);

        $now = date(\CDatabase::DateFormatToPHP('DD.MM.YYYY HH:MI:SS'), time());
        $ib = new CIBlockElement;
        $ib->Add([
            'NAME' => 'Участник: ' . $props[31] . ' Прогноз CS2 на матч: ' . $props[17] . ' номер ' . $props[30],
            'IBLOCK_ID' => $this->prognosisIb,
            'DATE_ACTIVE_FROM' => $now,
            'PROPERTY_VALUES' => $props,
        ]);
    }
}
