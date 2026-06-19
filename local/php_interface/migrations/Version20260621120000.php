<?php

namespace Sprint\Migration;

/**
 * IEM Cologne Major 2026 — плей-офф (сетка HLTV / cybersport.ru).
 * Зависимости: cs2teams, cs2matches, eventtype cs2.
 */
class Version20260621120000 extends Version
{
    protected $description = 'CS2: IEM Cologne Major 2026 — команды, событие, матчи плей-офф';

    protected $moduleVersion = '4.1.1';

    /** @var array<string, int> */
    private array $teamIds = [];

    public function up()
    {
        $helper = $this->getHelperManager();

        $teamsIb = (int)$helper->Iblock()->getIblockIdIfExists('cs2teams', 'content');
        $matchesIb = (int)$helper->Iblock()->getIblockIdIfExists('cs2matches', 'content');
        $eventsIb = (int)$helper->Iblock()->getIblockIdIfExists('events', 'content');
        $typeIb = (int)($helper->Iblock()->getIblockIdIfExists('eventtype', 'content') ?: 19);

        if ($teamsIb <= 0 || $matchesIb <= 0 || $eventsIb <= 0) {
            $this->outError('Нужны инфоблоки cs2teams, cs2matches, events');

            return false;
        }

        $cs2TypeId = (int)$helper->Iblock()->getElementId($typeIb, ['=CODE' => 'cs2']);
        if ($cs2TypeId <= 0) {
            $this->outError('Сначала запустите Version20260621102000 (тип cs2)');

            return false;
        }

        $this->seedTeams($helper, $teamsIb);
        $eventId = $this->seedEvent($helper, $eventsIb, $cs2TypeId);
        if ($eventId <= 0) {
            return false;
        }

        $this->seedPlayoffMatches($helper, $matchesIb, $eventId);

        $this->outSuccess('IEM Cologne Major 2026: событие #' . $eventId);

        return true;
    }

    public function down()
    {
        $helper = $this->getHelperManager();
        $eventsIb = (int)$helper->Iblock()->getIblockIdIfExists('events', 'content');
        $matchesIb = (int)$helper->Iblock()->getIblockIdIfExists('cs2matches', 'content');
        $teamsIb = (int)$helper->Iblock()->getIblockIdIfExists('cs2teams', 'content');

        $eventId = (int)$helper->Iblock()->getElementId($eventsIb, ['=XML_ID' => 'cs2_iem_cologne_2026']);
        if ($eventId > 0 && $matchesIb > 0) {
            $rs = \CIBlockElement::GetList([], [
                'IBLOCK_ID' => $matchesIb,
                'PROPERTY_EVENTS' => $eventId,
            ], false, false, ['ID']);
            while ($row = $rs->Fetch()) {
                \CIBlockElement::Delete((int)$row['ID']);
            }
            \CIBlockElement::Delete($eventId);
        }

        if ($teamsIb > 0) {
            foreach ($this->teamDefinitions() as $team) {
                $id = (int)$helper->Iblock()->getElementId($teamsIb, ['=XML_ID' => $team['xml_id']]);
                if ($id > 0) {
                    \CIBlockElement::Delete($id);
                }
            }
        }

        return true;
    }

    private function seedTeams($helper, int $iblockId): void
    {
        foreach ($this->teamDefinitions() as $team) {
            $existing = (int)$helper->Iblock()->getElementId($iblockId, ['=XML_ID' => $team['xml_id']]);
            if ($existing > 0) {
                $this->teamIds[$team['code']] = $existing;
                continue;
            }

            $id = $helper->Iblock()->addElement($iblockId, [
                'NAME' => $team['name'],
                'CODE' => $team['code'],
                'XML_ID' => $team['xml_id'],
                'ACTIVE' => 'Y',
                'SORT' => $team['sort'],
            ], [
                'short_tag' => $team['tag'],
                'hltv_slug' => $team['slug'],
                'region' => $team['region'],
            ]);

            $this->teamIds[$team['code']] = (int)$id;
        }
    }

    private function seedEvent($helper, int $iblockId, int $cs2TypeId): int
    {
        $existing = (int)$helper->Iblock()->getElementId($iblockId, ['=XML_ID' => 'cs2_iem_cologne_2026']);
        if ($existing > 0) {
            return $existing;
        }

        return (int)$helper->Iblock()->addElement($iblockId, [
            'NAME' => 'IEM Cologne Major 2026',
            'CODE' => 'iem-cologne-major-2026',
            'XML_ID' => 'cs2_iem_cologne_2026',
            'ACTIVE' => 'Y',
            'DATE_ACTIVE_FROM' => '11.06.2026 00:00:00',
            'DATE_ACTIVE_TO' => '21.06.2026 23:59:59',
            'PREVIEW_TEXT' => 'Intel Extreme Masters Cologne Major 2026, LANXESS Arena',
            'SORT' => '100',
        ], [
            'e_type' => $cs2TypeId,
            'table' => 'Нет',
        ]);
    }

    private function seedPlayoffMatches($helper, int $iblockId, int $eventId): void
    {
        foreach ($this->matchDefinitions() as $match) {
            $xmlId = 'cs2_iem26_m' . $match['number'];
            $existing = (int)$helper->Iblock()->getElementId($iblockId, ['=XML_ID' => $xmlId]);
            if ($existing > 0) {
                continue;
            }

            $homeId = $this->teamIds[$match['home']] ?? 0;
            $guestId = $this->teamIds[$match['guest']] ?? 0;
            if ($homeId <= 0 || $guestId <= 0) {
                $this->outWarning('Пропуск матча #' . $match['number'] . ': команда не найдена');

                continue;
            }

            $props = [
                'events' => $eventId,
                'home' => $homeId,
                'guest' => $guestId,
                'number' => $match['number'],
                'round' => $match['round'],
                'step' => $match['step'],
                'stage' => $match['stage'],
                'bo_format' => $match['bo_format'],
            ];

            if (!empty($match['result'])) {
                $props = array_merge($props, $match['result']);
            }

            $helper->Iblock()->addElement($iblockId, [
                'NAME' => sprintf(
                    'IEM Cologne 2026 #%d: %s — %s',
                    $match['number'],
                    $match['home_name'],
                    $match['guest_name']
                ),
                'CODE' => 'iem26-' . $match['number'],
                'XML_ID' => $xmlId,
                'ACTIVE' => $match['active'] ?? 'Y',
                'DATE_ACTIVE_FROM' => $match['date'],
                'SORT' => $match['number'] * 10,
            ], $props);
        }
    }

    /** @return list<array<string, mixed>> */
    private function teamDefinitions(): array
    {
        return [
            ['code' => 'spirit', 'xml_id' => 'cs2team_spirit', 'name' => 'Team Spirit', 'tag' => 'TS', 'slug' => 'spirit', 'region' => 'CIS', 'sort' => 100],
            ['code' => 'furia', 'xml_id' => 'cs2team_furia', 'name' => 'FURIA', 'tag' => 'FURIA', 'slug' => 'furia', 'region' => 'BR', 'sort' => 110],
            ['code' => 'aurora', 'xml_id' => 'cs2team_aurora', 'name' => 'Aurora', 'tag' => 'AUR', 'slug' => 'aurora', 'region' => 'EU', 'sort' => 120],
            ['code' => 'vitality', 'xml_id' => 'cs2team_vitality', 'name' => 'Team Vitality', 'tag' => 'VIT', 'slug' => 'vitality', 'region' => 'FR', 'sort' => 130],
            ['code' => 'falcons', 'xml_id' => 'cs2team_falcons', 'name' => 'Team Falcons', 'tag' => 'FLC', 'slug' => 'falcons', 'region' => 'EU', 'sort' => 140],
            ['code' => 'betboom', 'xml_id' => 'cs2team_betboom', 'name' => 'BetBoom Team', 'tag' => 'BB', 'slug' => 'betboom', 'region' => 'CIS', 'sort' => 150],
            ['code' => '9z', 'xml_id' => 'cs2team_9z', 'name' => '9z Team', 'tag' => '9z', 'slug' => '9z', 'region' => 'SA', 'sort' => 160],
            ['code' => 'g2', 'xml_id' => 'cs2team_g2', 'name' => 'G2 Esports', 'tag' => 'G2', 'slug' => 'g2', 'region' => 'EU', 'sort' => 170],
        ];
    }

    /**
     * Сетка плей-офф (топ-8), источник: HLTV / Wikipedia IEM Cologne 2026.
     *
     * @return list<array<string, mixed>>
     */
    private function matchDefinitions(): array
    {
        return [
            [
                'number' => 1,
                'home' => 'aurora',
                'guest' => 'betboom',
                'home_name' => 'Aurora',
                'guest_name' => 'BetBoom',
                'stage' => 'Четвертьфинал',
                'round' => 1,
                'step' => 1,
                'bo_format' => 'bo3',
                'date' => '18.06.2026 15:45:00',
                'active' => 'N',
                'result' => [
                    'maps_home' => 2,
                    'maps_guest' => 0,
                    'result' => 'п1',
                    'diff' => 2,
                    'sum' => 2,
                    'opening_pct' => 50,
                    'pistol_pct' => 50,
                    'clutches_home' => 0,
                    'clutches_guest' => 0,
                ],
            ],
            [
                'number' => 2,
                'home' => '9z',
                'guest' => 'furia',
                'home_name' => '9z',
                'guest_name' => 'FURIA',
                'stage' => 'Четвертьфинал',
                'round' => 1,
                'step' => 2,
                'bo_format' => 'bo3',
                'date' => '18.06.2026 19:00:00',
                'active' => 'N',
                'result' => [
                    'maps_home' => 1,
                    'maps_guest' => 2,
                    'result' => 'п2',
                    'diff' => -1,
                    'sum' => 3,
                    'opening_pct' => 50,
                    'pistol_pct' => 50,
                    'clutches_home' => 0,
                    'clutches_guest' => 0,
                ],
            ],
            [
                'number' => 3,
                'home' => 'g2',
                'guest' => 'spirit',
                'home_name' => 'G2',
                'guest_name' => 'Spirit',
                'stage' => 'Четвертьфинал',
                'round' => 1,
                'step' => 3,
                'bo_format' => 'bo3',
                'date' => '19.06.2026 15:45:00',
                'active' => 'N',
                'result' => [
                    'maps_home' => 0,
                    'maps_guest' => 2,
                    'result' => 'п2',
                    'diff' => -2,
                    'sum' => 2,
                    'opening_pct' => 50,
                    'pistol_pct' => 50,
                    'clutches_home' => 0,
                    'clutches_guest' => 0,
                ],
            ],
            [
                'number' => 4,
                'home' => 'falcons',
                'guest' => 'vitality',
                'home_name' => 'Falcons',
                'guest_name' => 'Vitality',
                'stage' => 'Четвертьфинал',
                'round' => 1,
                'step' => 4,
                'bo_format' => 'bo3',
                'date' => '19.06.2026 19:00:00',
                'active' => 'N',
                'result' => [
                    'maps_home' => 1,
                    'maps_guest' => 2,
                    'result' => 'п2',
                    'diff' => -1,
                    'sum' => 3,
                    'opening_pct' => 50,
                    'pistol_pct' => 50,
                    'clutches_home' => 0,
                    'clutches_guest' => 0,
                ],
            ],
            [
                'number' => 5,
                'home' => 'aurora',
                'guest' => 'furia',
                'home_name' => 'Aurora',
                'guest_name' => 'FURIA',
                'stage' => 'Полуфинал',
                'round' => 2,
                'step' => 1,
                'bo_format' => 'bo3',
                'date' => '20.06.2026 15:45:00',
                'active' => 'Y',
            ],
            [
                'number' => 6,
                'home' => 'spirit',
                'guest' => 'vitality',
                'home_name' => 'Spirit',
                'guest_name' => 'Vitality',
                'stage' => 'Полуфинал',
                'round' => 2,
                'step' => 2,
                'bo_format' => 'bo3',
                'date' => '20.06.2026 19:00:00',
                'active' => 'Y',
            ],
        ];
    }
}
