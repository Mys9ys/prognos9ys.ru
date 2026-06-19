<?php

namespace Sprint\Migration;

class Version20260620120000 extends Version
{
    protected $description = 'CS2: инфоблоки cs2matches, prognoscs2, resultcs2';

    protected $moduleVersion = '4.1.1';

    public function up()
    {
        $helper = $this->getHelperManager();

        $matchesId = $this->createIblock($helper, 'cs2matches', 'CS2 — матчи');
        $prognosisId = $this->createIblock($helper, 'prognoscs2', 'CS2 — прогнозы');
        $resultId = $this->createIblock($helper, 'resultcs2', 'CS2 — результаты прогнозов');

        if (!$matchesId || !$prognosisId || !$resultId) {
            $this->outError('Не удалось создать инфоблоки CS2');
            return false;
        }

        $this->saveMatchProperties($helper, $matchesId);
        $this->savePrognosisProperties($helper, $prognosisId, $matchesId);
        $this->saveResultProperties($helper, $resultId, $matchesId);

        return true;
    }

    public function down()
    {
        $helper = $this->getHelperManager();
        foreach (['resultcs2', 'prognoscs2', 'cs2matches'] as $code) {
            $id = $helper->Iblock()->getIblockIdIfExists($code, 'content');
            if ($id) {
                $helper->Iblock()->deleteIblockIfExists($id);
            }
        }

        return true;
    }

    private function createIblock($helper, string $code, string $name): int
    {
        $existing = $helper->Iblock()->getIblockIdIfExists($code, 'content');
        if ($existing) {
            return (int)$existing;
        }

        return (int)$helper->Iblock()->saveIblock([
            'IBLOCK_TYPE_ID' => 'content',
            'LID' => ['s1'],
            'CODE' => $code,
            'API_CODE' => $code,
            'NAME' => $name,
            'ACTIVE' => 'Y',
            'SORT' => '500',
            'VERSION' => '1',
            'INDEX_ELEMENT' => 'Y',
        ]);
    }

    private function saveMatchProperties($helper, int $iblockId): void
    {
        $this->saveElementLink($helper, $iblockId, 'events', 'Соревнование', 'content:events');
        $teamsId = (int)$helper->Iblock()->getIblockIdIfExists('cs2teams', 'content');
        $teamLink = $teamsId > 0 ? $teamsId : 'content:countries';
        $this->saveElementLink($helper, $iblockId, 'home', 'Команда 1', is_int($teamLink) ? 'content:cs2teams' : $teamLink, is_int($teamLink) ? $teamLink : null);
        $this->saveElementLink($helper, $iblockId, 'guest', 'Команда 2', is_int($teamLink) ? 'content:cs2teams' : $teamLink, is_int($teamLink) ? $teamLink : null);
        $this->saveElementLink($helper, $iblockId, 'group', 'Группа', 'content:group');

        $this->saveNumber($helper, $iblockId, 'number', 'Номер матча');
        $this->saveNumber($helper, $iblockId, 'round', 'Тур');
        $this->saveNumber($helper, $iblockId, 'step', 'Шаг');
        $this->saveString($helper, $iblockId, 'stage', 'Стадия');

        $helper->Iblock()->saveProperty($iblockId, [
            'NAME' => 'Формат серии',
            'CODE' => 'bo_format',
            'PROPERTY_TYPE' => 'L',
            'LIST_TYPE' => 'L',
            'VALUES' => [
                ['VALUE' => 'Bo1', 'XML_ID' => 'bo1', 'SORT' => 100],
                ['VALUE' => 'Bo3', 'XML_ID' => 'bo3', 'SORT' => 200, 'DEF' => 'Y'],
                ['VALUE' => 'Bo5', 'XML_ID' => 'bo5', 'SORT' => 300],
            ],
        ]);

        $this->saveNumber($helper, $iblockId, 'maps_home', 'Карт у команды 1');
        $this->saveNumber($helper, $iblockId, 'maps_guest', 'Карт у команды 2');
        $this->saveString($helper, $iblockId, 'result', 'Исход серии');
        $this->saveNumber($helper, $iblockId, 'diff', 'Разница карт');
        $this->saveNumber($helper, $iblockId, 'sum', 'Сумма карт');
        $this->saveNumber($helper, $iblockId, 'opening_pct', 'Опены, % команды 1');
        $this->saveNumber($helper, $iblockId, 'pistol_pct', 'Пистолетки, % команды 1');
        $this->saveNumber($helper, $iblockId, 'clutches_home', 'Клатчи команды 1');
        $this->saveNumber($helper, $iblockId, 'clutches_guest', 'Клатчи команды 2');
        $this->saveString($helper, $iblockId, 'map_scores', 'Счёт по картам (JSON)', 10);
    }

    private function savePrognosisProperties($helper, int $iblockId, int $matchesId): void
    {
        $this->saveElementLink($helper, $iblockId, 'events', 'Соревнование', 'content:events');
        $this->saveElementLink($helper, $iblockId, 'match_id', 'Матч', 'content:cs2matches', $matchesId);
        $this->saveNumber($helper, $iblockId, 'user_id', 'Пользователь');
        $this->saveNumber($helper, $iblockId, 'number', 'Номер матча');

        $this->saveNumber($helper, $iblockId, 'maps_home', 'Карт у команды 1');
        $this->saveNumber($helper, $iblockId, 'maps_guest', 'Карт у команды 2');
        $this->saveString($helper, $iblockId, 'result', 'Исход серии');
        $this->saveNumber($helper, $iblockId, 'diff', 'Разница карт');
        $this->saveNumber($helper, $iblockId, 'sum', 'Сумма карт');
        $this->saveNumber($helper, $iblockId, 'opening_pct', 'Опены, % команды 1');
        $this->saveNumber($helper, $iblockId, 'pistol_pct', 'Пистолетки, % команды 1');
        $this->saveNumber($helper, $iblockId, 'clutches_home', 'Клатчи команды 1');
        $this->saveNumber($helper, $iblockId, 'clutches_guest', 'Клатчи команды 2');
        $this->saveString($helper, $iblockId, 'map_scores', 'Счёт по картам (JSON)', 10);
    }

    private function saveResultProperties($helper, int $iblockId, int $matchesId): void
    {
        $this->saveElementLink($helper, $iblockId, 'events', 'Соревнование', 'content:events');
        $this->saveElementLink($helper, $iblockId, 'match_id', 'Матч', 'content:cs2matches', $matchesId);
        $this->saveNumber($helper, $iblockId, 'user_id', 'Пользователь');
        $this->saveNumber($helper, $iblockId, 'number', 'Номер матча');

        $this->saveNumber($helper, $iblockId, 'score', 'Баллы за счёт серии');
        $this->saveNumber($helper, $iblockId, 'result', 'Баллы за исход');
        $this->saveNumber($helper, $iblockId, 'diff', 'Баллы за разницу');
        $this->saveNumber($helper, $iblockId, 'sum', 'Баллы за сумму');
        $this->saveNumber($helper, $iblockId, 'opening_pct', 'Баллы за опены');
        $this->saveNumber($helper, $iblockId, 'pistol_pct', 'Баллы за пистолетки');
        $this->saveNumber($helper, $iblockId, 'clutches_home', 'Баллы за клатчи 1');
        $this->saveNumber($helper, $iblockId, 'clutches_guest', 'Баллы за клатчи 2');
        $this->saveNumber($helper, $iblockId, 'map_scores', 'Баллы за карты');
        $this->saveNumber($helper, $iblockId, 'all', 'Сумма баллов');
    }

    private function saveElementLink($helper, int $iblockId, string $code, string $name, string $link, ?int $linkId = null): void
    {
        $property = [
            'NAME' => $name,
            'CODE' => $code,
            'PROPERTY_TYPE' => 'E',
            'LINK_IBLOCK_ID' => $link,
        ];

        if ($linkId) {
            $property['LINK_IBLOCK_ID'] = $linkId;
        }

        $helper->Iblock()->saveProperty($iblockId, $property);
    }

    private function saveNumber($helper, int $iblockId, string $code, string $name): void
    {
        $helper->Iblock()->saveProperty($iblockId, [
            'NAME' => $name,
            'CODE' => $code,
            'PROPERTY_TYPE' => 'N',
        ]);
    }

    private function saveString($helper, int $iblockId, string $code, string $name, int $rows = 1): void
    {
        $helper->Iblock()->saveProperty($iblockId, [
            'NAME' => $name,
            'CODE' => $code,
            'PROPERTY_TYPE' => 'S',
            'ROW_COUNT' => (string)$rows,
        ]);
    }
}
