<?php

use Bitrix\Main\Loader;

\Bitrix\Main\Loader::registerAutoLoadClasses(
    null,
    [
        'GenValuesBotFootball' => '/local/classes/GenValuesBotFootball.php',
        'SetBotPrognosis' => '/local/classes/SetBotPrognosis.php',
        'GetPrognosisEvents' => '/local/classes/GetPrognosisEvents.php',
        'GetArrMatchIdForNumber' => '/local/classes/GetArrMatchIdForNumber.php',
        'GetUserIdForToken' => '/local/classes/GetUserIdForToken.php',
        'GetFootballTeams' => '/local/classes/GetFootballTeams.php',
    ]
);

//функция вывода дампа
function dump($var, $die = false, $all = false)
{
    global $USER;
    if (($USER->isAdmin()) || ($all == true)) {
        ?>
        <div style="text-align:left;font-size:14px;color:#000">
            <pre><? var_dump($var) ?></pre>
        </div><br/><?
    }
    if ($die) die();
}

function testAgent()
{

    file_put_contents('test.json',json_encode('test'));
    $res = new ChangeActiveItem();
    return "testAgent();";
}

function AgentChangeActiveItem(){
    CModule::IncludeModule("iblock");
    $arIb = [
        \CIBlock::GetList([], ['CODE' => 'matches'], false)->Fetch()['ID'] ?: 2,
    ];

    $res = new ChangeActiveItem();
    foreach ($arIb as $ib){
        $res->inActiveElement($ib);
    }

    return "AgentChangeActiveItem();";
}

class ChangeActiveItem {
    public function __construct()
    {
        if (!Loader::includeModule('iblock')) {
            ShowError('Модуль Информационных блоков не установлен');
            return;
        }
    }

    public function inActiveElement($iblock_id){
        $now = date(\CDatabase::DateFormatToPHP("DD.MM.YYYY HH:MI:SS"), time());
        $arFilter["IBLOCK_ID"] = $iblock_id;
        $arFilter["<=DATE_ACTIVE_FROM"] = $now;
        $arFilter["ACTIVE"] = 'Y';

        $response = CIBlockElement::GetList(
            [],
            $arFilter,
            false,
            [],
            [
                "ID",
            ]
        );

        while ($res = $response->GetNext()) {
            $obEl = new CIBlockElement();
            // Деактивация элемента
            $boolResult = $obEl->Update($res['ID'],array('ACTIVE' => 'N'));
        }

    }
}