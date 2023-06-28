<?php

use Bitrix\Main\Loader;

class CatalogEvents extends PrognosisGiveInfo
{
    protected $eventsTypeIb = 19;

    protected $arEvents = [];

    protected $fillResult = [];

    protected $arAll = [];

    protected $arCode = [];

    protected $arResult = [];

    protected $ar = [];

    public function __construct($data)
    {
        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        }

        $this->arEvents = (new GetPrognosisEvents())->result()['events'];

        $this->getEventsType();
        $this->fillResult();

        if ($data['type'] === 'all') {
            $this->setResult('ok', '', $this->arAll);
        } else {
            $this->setResult('ok', '', $this->fillResult);
        }

    }

    protected function getEventsType()
    {
        $arFilter = [
            'IBLOCK_ID' => $this->eventsTypeIb,
        ];

        $response = CIBlockElement::GetList(
            [],
            $arFilter,
            false,
            [],
            ["ID", "NAME", "CODE"]
        );
        while ($res = $response->GetNext()) {
            $this->fillResult[$res["ID"]]["info"] = $res;
            $this->arCode[$res["ID"]]['code'] = $res['CODE'];
        }

    }

    protected function fillResult()
    {

        foreach ($this->arEvents as $id => $res) {
            $res['status'] = ($res["ACTIVE"] === 'Y') ? 'now' : 'old';
            $this->fillResult[$res["code"]]['events'][$res['status']][$id] = $res;

            $res['code'] = $this->arCode[$res["code"]]["code"];

            $this->arAll[$res['status']][$id] = $res;
        }

    }

}