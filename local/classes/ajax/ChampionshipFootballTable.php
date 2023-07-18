<?php

use Bitrix\Main\Loader;

class ChampionshipFootballTable extends PrognosisGiveInfo
{
    protected $data;
    protected $arIbs = [
        'matches' => ['code' => 'matches', 'id' => 2],
    ];

    protected $teamsIds;

    protected $arTable;

    public function __construct($data)
    {

        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        }

        $this->data = $data;

        $this->getTeamsOneTurids();

        $arEvents = (new GetPrognosisEvents())->result()['events'];

        if(count($this->teamsIds)) $this->getTeamsInfo();

        if(count($this->arTable)) $this->setResult('ok', '', ['teams' => $this->arTable, 'info' => $arEvents[$this->data['events']]]);

    }

    protected function getTeamsOneTurIds(){
        $arFilter = [
            'IBLOCK_ID' => $this->arIbs['matches']['id'],
            'PROPERTY_events' => $this->data['events'],
            'PROPERTY_round' => 1
        ];

        $response = CIBlockElement::GetList(
            [],
            $arFilter,
            false,
            [],
            [
                'PROPERTY_home', 'PROPERTY_guest'
            ]
        );

        while ($res=$response->GetNext()){
            $this->teamsIds[] = $res['PROPERTY_HOME_VALUE'];
            $this->teamsIds[] = $res['PROPERTY_GUEST_VALUE'];
        }
    }

    protected function getTeamsInfo(){
        $arFilter = [
            'ID' => $this->teamsIds
        ];

        $response = CIBlockElement::GetList(
            ['NAME' => 'ASC'],
            $arFilter,
            false,
            [],
            ['NAME', 'ID', 'PREVIEW_PICTURE']
        );
        while ($res=$response->GetNext()){
            $res['img'] = CFile::GetPath($res['PREVIEW_PICTURE']);
            $this->arTable[] = $res;
        }
    }
}