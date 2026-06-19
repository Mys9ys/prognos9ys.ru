<?php

namespace Sprint\Migration;

require_once __DIR__ . '/Cs2MigrationIblock.php';

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
            $id = Cs2MigrationIblock::findId($code);
            if ($id) {
                $helper->Iblock()->deleteIblockIfExists($id);
            }
        }

        return true;
    }

    private function createIblock($helper, string $code, string $name): int
    {
        $existing = Cs2MigrationIblock::findId($code);
        if ($existing > 0) {
            return $existing;
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
        $eventsId = Cs2MigrationIblock::findId('events');
        $countriesId = Cs2MigrationIblock::findId('countries');
        $groupId = Cs2MigrationIblock::findId('group') ?: Cs2MigrationIblock::findId('groups');

        if ($eventsId > 0) {
            $this->saveElementLink($helper, $iblockId, 'events', 'Соревнование', $eventsId);
        }
        if ($countriesId > 0) {
            $this->saveElementLink($helper, $iblockId, 'home', 'Команда 1', $countriesId);
            $this->saveElementLink($helper, $iblockId, 'guest', 'Команда 2', $countriesId);
        }
        if ($groupId > 0) {
            $this->saveElementLink($helper, $iblockId, 'group', 'Группа', $groupId);
        }

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
        $eventsId = Cs2MigrationIblock::findId('events');
        if ($eventsId > 0) {
            $this->saveElementLink($helper, $iblockId, 'events', 'Соревнование', $eventsId);
        }
        $this->saveElementLink($helper, $iblockId, 'match_id', 'Матч', $matchesId);
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
        $eventsId = Cs2MigrationIblock::findId('events');
        if ($eventsId > 0) {
            $this->saveElementLink($helper, $iblockId, 'events', 'Соревнование', $eventsId);
        }
        $this->saveElementLink($helper, $iblockId, 'match_id', 'Матч', $matchesId);
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

    private function saveElementLink($helper, int $iblockId, string $code, string $name, int $linkIblockId): void
    {
        $helper->Iblock()->saveProperty($iblockId, [
            'NAME' => $name,
            'CODE' => $code,
            'PROPERTY_TYPE' => 'E',
            'LINK_IBLOCK_ID' => $linkIblockId,
        ]);
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
