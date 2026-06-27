<?php

\Bitrix\Main\Loader::includeModule('prognos9ys.main');

require_once __DIR__ . '/include/session_release.php';

AddEventHandler('main', 'OnProlog', static function (): void {
    prognos9ys_release_php_session_if_needed();
});

use Prognos9ys\Main\Service\Game\RegistrationBonusService;
use Prognos9ys\Main\Service\Game\SeedUserGroupService;

AddEventHandler('main', 'OnAfterUserAdd', static function (array &$arFields): void {
    if (!empty($arFields['ID'])) {
        $userId = (int)$arFields['ID'];
        RegistrationBonusService::onUserRegistered($userId);
        SeedUserGroupService::onUserRegistered($userId);
    }
});

$arClassAgent = [
    'DeactivateEventElementForDate',
    'DeactivateEventElement',
    'TelegramBotHandler',
];

$arClassAjax = [
    'Prognos9ysMainPageInfo',
    'FootballHandlerClass',
    'Cs2HandlerClass',
    'FootballSendPrognosis',
    'Cs2SendPrognosis',
    'ChampionshipFootballTable',
    'PlayoffSlotHelper',
    'ChampionshipRaceTable',
    'CalcRacePrognosisResult',
    'CalcFootballPrognosisResult',
    'CalcCs2PrognosisResult',
    'Prognos9ysHumorHandler',
    'CreateFootballRatings',
    'RaceRatingsHandler',
    'FootballMatchLoadInfo',
    'Cs2MatchLoadInfo',
    'NewsHandlerClass',
    'CatalogEvents',
    'FootballSetResult',
    'Cs2SetResult',
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
    'GetCs2Teams',
    'GetUserRole',
    'GetBotsClass',
    'GenRacePrognosis',
    'SetRacersScore',
    'SetBotRacePrognosis',
    'GetFootballTeamStatistic',
    'GenValuesBotFootball',
    'GenValuesBotCs2',
    'SetBotPrognosis',
    'SetBotCs2Prognosis',
    'SetMatchReminder',
    'GetUserRank',
    'GetPrognosisEvents',
    'GetArrMatchIdForNumber',
    'SendMatchReminderMessage',
    'GetUserIdForToken',
    'Prognos9ysAuthClass',
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

/**
 * Примеры вызова:
 * - Без указания глубины:
 *      - aldump($var);
 *      - aldump($var1, $var2, $var3);
 *      - aldump(...$vars);
 * - C указанием глубины:
 *      - aldump($var, deep: 6000);
 *      - aldump($var1, $var2, $var3, deep:6000);
 *      - aldump(...$vars, deep: 6000);
 * - С отрисовкой до упора
 *      - aldump($var, deep: -1);
 *      - aldump($var1, $var2, $var3, deep:-1);
 *      - aldump(...$vars, deep: -1);
 * - С указанием только вернуть результат в виде HTML
 *      - aldump($var, needReturn: true);
 *      - aldump($var1, $var2, $var3, needReturn: true);
 *      - aldump(...$vars, needReturn: true);
 * - С указанием требования экранировать вывод
 *      - aldump($var, needReturn: true, needEscapeReturn: true);
 *      - aldump($var1, $var2, $var3, needReturn: true, needEscapeReturn: true);
 *      - aldump(...$vars, needReturn: true, needEscapeReturn: true);
 * @param ...$vars (Переменные, которые необходимо вывести, задаются через запятую)
 * @param int $deep
 * @param bool $needReturn
 * @param bool $needEscapeReturn
 * @param array|int $pushChannel
 * @return array|void
 */
function aldump(...$vars): mixed
{
    $needReturn = (key_exists('needReturn', $vars) && $vars['needReturn']) ? true : null;
    $needEscapeReturn = (key_exists('needEscapeReturn', $vars) && $vars['needEscapeReturn']) ? true : null;
    $deep = (key_exists('deep', $vars) && is_int($vars['deep'])) ? $vars['deep'] : 2500;
    $pushChannel = (key_exists('pushChannel', $vars) ? $vars['pushChannel'] : null);

    // Обнуляем служебные ключи
    $keys = ['deep', 'needReturn', 'needEscapeReturn', 'pushChannel'];
    foreach ($keys as $key) {
        if (key_exists($key, $vars)) {
            unset($vars[$key]);
        }
    }

    $result = [];
    foreach ($vars as $var) {
        $dampStr = _aldump_render($var, $deep);
        $result[] = $dampStr;
    }

    // Push & Pull
    if ($pushChannel !== null) {
        if (!is_array($pushChannel)) {
            $pushChannel = [$pushChannel];
        }
        foreach ($result as $data) {
            foreach ($pushChannel as $channel) {
                CPullStack::AddByUser($channel, [
                    'module_id' => 'alfarma',
                    'command' => 'aldump',
                    'params' => ['data' => $data],
                ]);
            }
        }
    }

    // Вывод или возврат
    if ($needReturn) {
        if ($needEscapeReturn) {
            return array_map('htmlspecialcharsbx', $result);
        }
        return $result;
    }

    $output = '<div style="background:#1e1e1e;color:#d4d4d4;font-family:Consolas,Monaco,monospace;font-size:13px;padding:12px 16px;border-radius:6px;line-height:1.5;overflow:auto;max-height:80vh;">'
        . implode("\n", $result)
        . '</div>';
    echo $output;
    return null;
}

/**
 * Рекурсивный обход переменной и формирование HTML-строки.
 * Без зависимостей от Composer / symfony/var-dumper.
 *
 * @param mixed $var
 * @param int $deep Максимальная глубина. -1 = без ограничений.
 * @param int $level Текущий уровень вложенности (внутреннее использование).
 * @return string
 */
function _aldump_render($var, int $deep = 2500, int $level = 0): string
{
    // Проверка глубины
    if ($deep !== -1 && $level >= $deep) {
        return "\n<span style=\"color:#888;font-style:italic;\">… (max depth: {$deep})</span>\n";
    }

    if (is_null($var)) {
        return "\n<span style=\"color:#569cd6;\">null</span>\n";
    }

    if (is_bool($var)) {
        return "\n<span style=\"color:#c586c0;\">" . ($var ? 'true' : 'false') . "</span>\n";
    }

    if (is_int($var) || is_float($var)) {
        return "\n<span style=\"color:#b5cea8;\">" . $var . "</span>\n";
    }

    if (is_string($var)) {
        $display = htmlspecialcharsbx($var);
        return "\n<span style=\"color:#ce9178;\">'" . $display . "'</span> <span style=\"color:#6a9955;font-size:90%;\">(length=" . strlen($var) . ")</span>\n";
    }

    if (is_array($var)) {
        $count = count($var);
        $html = "\n<span style=\"color:#569cd6;font-weight:bold;\">array</span> <span style=\"color:#858585;font-size:90%;\">(size={$count})</span>\n";
        $html .= "<div style=\"padding-left:20px;border-left:2px solid #404040;margin:2px 0;\">\n";
        foreach ($var as $key => $value) {
            $keyStr = is_string($key)
                ? "'" . htmlspecialcharsbx($key) . "'"
                : (is_int($key) ? (string)$key : htmlspecialcharsbx((string)$key));
            $html .= "<span style=\"color:#d4d4d4;\">{$keyStr}</span> => ";
            $html .= _aldump_render($value, $deep, $level + 1);
            $html .= "\n";
        }
        $html .= "</div>\n";
        return $html;
    }

    if (is_object($var)) {
        $class = get_class($var);
        $html = "\n<span style=\"color:#4ec9b0;font-weight:bold;\">object({$class})</span>\n";
        $ref = new ReflectionObject($var);
        $props = $ref->getProperties();
        $html .= "<div style=\"padding-left:20px;border-left:2px solid #2b5a5a;margin:2px 0;\">\n";
        foreach ($props as $prop) {
            $prop->setAccessible(true);
            $propName = $prop->getName();
            $modifiers = Reflection::getModifierNames($prop->getModifiers());
            $modStr = implode(' ', $modifiers);
            $html .= "<span style=\"color:#4ec9b0;\">{$modStr}</span> <span style=\"color:#dcdcaa;\">\${$propName}</span> => ";
            $propValue = $prop->getValue($var);
            $html .= _aldump_render($propValue, $deep, $level + 1);
            $html .= "\n";
        }
        $html .= "</div>\n";
        return $html;
    }

    if (is_resource($var)) {
        return "\n<span style=\"color:#dcdcaa;\">Resource</span>\n";
    }

    return "\n<span style=\"color:#858585;\">" . gettype($var) . "</span>\n";
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

function AgentCs2BotSetPrognosis()
{
    CModule::IncludeModule("iblock");

    new SetBotCs2Prognosis();

    return "AgentCs2BotSetPrognosis();";
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
