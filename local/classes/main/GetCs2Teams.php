<?php

use Bitrix\Main\Loader;

class GetCs2Teams
{
    protected $arTeams = [];

    protected $teamsIb = 0;

    public function __construct()
    {
        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        }

        $this->teamsIb = (int)(\CIBlock::GetList([], ['CODE' => 'cs2teams'], false)->Fetch()['ID'] ?? 0);

        if ($this->teamsIb > 0) {
            $this->loadFromIblock($this->teamsIb);
        } else {
            $this->arTeams = (new GetFootballTeams())->result();
        }
    }

    protected function loadFromIblock(int $iblockId): void
    {
        $response = \Bitrix\Iblock\ElementTable::getList([
            'select' => ['ID', 'NAME', 'PREVIEW_PICTURE', 'CODE'],
            'filter' => [
                'IBLOCK_ID' => $iblockId,
            ],
            'order' => ['SORT' => 'ASC', 'NAME' => 'ASC'],
        ]);

        while ($res = $response->fetch()) {
            $res['flag'] = CFile::GetPath($res['PREVIEW_PICTURE']);
            $res['name'] = $res['NAME'];
            $this->arTeams[$res['ID']] = $res;
        }
    }

    public function result(): array
    {
        return $this->arTeams;
    }
}
