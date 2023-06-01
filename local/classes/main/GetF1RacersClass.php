<?php

use Bitrix\Main\Loader;

class GetF1RacersClass
{
    protected $arResult = [];
    protected $arTeams = [];
    protected $arCountry = [];

    protected $Ib = ['code' => 'racers', 'id' => 8];

    public function __construct()
    {
        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        }

        $this->arTeams = (new GetF1TeamsClass())->result();

        $this->arCountry = (new GetFootballTeams())->result();

        $this->getResult();
    }

    protected function getResult()
    {

        $arFilter = [
            "IBLOCK_ID" => $this->Ib['id'],
            "ACTIVE" => 'Y'
        ];

        $response = CIBlockElement::GetList(
            ["PROPERTY_74_VALUE" => "DESC","DATE_ACTIVE_FROM" => "ASC", "created" => "ASC"],
            $arFilter,
            false,
            [],
            [
                'ID', 'NAME', 'PREVIEW_PICTURE',
                'PROPERTY_country',
                'PROPERTY_team',
                'PROPERTY_score'
            ]
        );

        while ($res = $response->GetNext()) {
            $res["img"] = CFile::GetPath($res["PREVIEW_PICTURE"]);
            $res["country"] = $this->arCountry[$res["PROPERTY_COUNTRY_VALUE"]];
            $res["team"] = $this->arTeams[$res["PROPERTY_TEAM_VALUE"]];

            $res["score"] = $res["PROPERTY_SCORE_VALUE"] ?? 0;

            $arName = explode(' ',$res["NAME"]);

            $arName[0] = mb_substr($arName[0], 0, 1) . '.';

            $res["short_name"] = implode(' ', $arName);

            $this->arResult[$res["ID"]] = $res;
        }

    }

    public function result()
    {
        return $this->arResult;
    }
}