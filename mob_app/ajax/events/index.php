<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header("Access-Control-Allow-Headers: X-Requested-With");

header('Content-Type: text/html; charset=utf-8');

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Loader;

$_REQUEST['date'] = date(\CDatabase::DateFormatToPHP("DD.MM.YYYY HH:MI:SS"), time());

file_put_contents('../../_logs/events.log', json_encode($_REQUEST) . PHP_EOL, FILE_APPEND);

if ($_REQUEST) {

    $res = new Prognos9ysGetEventsInfo($_REQUEST);

    echo json_encode($res->result());

}


class Prognos9ysGetEventsInfo
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

        $arEv = new GetPrognosisEvents();
        $this->arEvents = $arEv->result()['events'];

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
            $res['status'] = ($res["ACTIVE"] === 'Y') ? 'active' : 'old';
            $this->fillResult[$res["code"]]['events'][$id] = $res;

            $res['code'] = $this->arCode[$res["code"]]["code"];

            $this->arAll[$id] = $res;
        }

    }

    protected function setResult($status, $mes, $arr)
    {
        $this->arResult['status'] = $status;
        $this->arResult['mes'] = $mes;
        $this->arResult['info'] = $arr;
    }

    public function result()
    {
        return $this->arResult;
    }
}