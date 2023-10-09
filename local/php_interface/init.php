<?php

$arClassAgent = [
    'DeactivateEventElementForDate',
    'DeactivateEventElement',
    'TelegramBotHandler',
];

$arClassAjax = [
    'Prognos9ysMainPageInfo',
    'FootballHandlerClass',
    'FootballSendPrognosis',
    'ChampionshipFootballTable',
    'ChampionshipRaceTable',
    'CalcRacePrognosisResult',
    'CalcFootballPrognosisResult',
    'Prognos9ysHumorHandler',
    'RaceRatingsHandler',
    'FootballMatchLoadInfo',
    'NewsHandlerClass',
    'CatalogEvents',
    'FootballSetResult',
    'ProfileHandlerClass',
    'RaceManyHandler',
    'RaceOneHandler',
    'RaceSendHandler',
    'RaceSetResult',
    'RaceNearestCome',
    'FootballNearestCome',
];

$arClassMain = [
    'GetF1RacersClass',
    'GetF1TeamsClass',
    'GetFootballTeams',
    'GetUserRole',
    'GetBotsClass',
    'GenRacePrognosis',
    'SetRacersScore',
    'SetBotRacePrognosis',
    'GetFootballTeamStatistic',
    'GenValuesBotFootball',
    'SetBotPrognosis',
    'SetMatchReminder',
    'GetUserRank',
    'GetPrognosisEvents',
    'GetArrMatchIdForNumber',
    'SendMatchReminderMessage',
    'GetUserIdForToken',
];

$arTemplate = [
    'PrognosisGiveInfo'
];

$arClassInclude = [];

foreach ($arClassAgent as $class) {
    $arClassInclude[$class] = '/local/classes/agent/' . $class . '.php';
}
foreach ($arClassAjax as $class) {
    $arClassInclude[$class] = '/local/classes/ajax/' . $class . '.php';
}
foreach ($arClassMain as $class) {
    $arClassInclude[$class] = '/local/classes/main/' . $class . '.php';
}
foreach ($arTemplate as $class) {
    $arClassInclude[$class] = '/local/classes/template/' . $class . '.php';
}

\Bitrix\Main\Loader::registerAutoLoadClasses(
    null,
    $arClassInclude
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


function AgentChangeActiveItem()
{
    CModule::IncludeModule("iblock");

    $res = new DeactivateEventElementForDate();

    return "AgentChangeActiveItem();";
}

function AgentFootballBotSetPrognosis()
{
    CModule::IncludeModule("iblock");

    $res = new SetBotPrognosis();

    return "AgentFootballBotSetPrognosis();";
}

function AgentRaceBotSetPrognosis()
{
    CModule::IncludeModule("iblock");

    $res = new SetBotRacePrognosis();

    return "AgentRaceBotSetPrognosis();";
}

function AgentSaveReminderMessages()
{
    CModule::IncludeModule("iblock");

    $res = new SetMatchReminder();

    return "AgentSaveReminderMessages();";
}

function AgentSendReminderMessageToTelegramm()
{
    CModule::IncludeModule("iblock");

    $res = new SendMatchReminderMessage();

    return "AgentSendReminderMessageToTelegramm();";
}

