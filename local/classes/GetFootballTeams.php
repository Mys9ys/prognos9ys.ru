<?php

use Bitrix\Main\Loader;

class GetFootballTeams
{
    protected $arTeams = [];

    protected $teamsIb;

    public function __construct()
    {
        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        }

        $this->teamsIb = \CIBlock::GetList([], ['CODE' => 'countries'], false)->Fetch()['ID'] ?: 3; //команды/страны

        $this->getTeamInIb();
    }

    protected function getTeamInIb(){

        $response = \Bitrix\Iblock\ElementTable::getList(
            [
                'select' => ['ID', 'NAME', 'PREVIEW_PICTURE'],
                'filter' => [
                    "IBLOCK_ID" => $this->teamsIb,
                ]
            ]
        );

        while ($res = $response->fetch()) {
            $res["flag"] = CFile::GetPath($res["PREVIEW_PICTURE"]);
            $this->arTeams[$res["ID"]] = $res;
        }
    }

    public function result(){
        return $this->arTeams;
    }
}