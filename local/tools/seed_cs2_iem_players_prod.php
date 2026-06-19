<?php
declare(strict_types=1);

/**
 * Регистрация игроков/тренеров IEM Cologne CS2 на БОЮ — как Месси и звёзды ЧМ:
 * POST /mob_app/ajax/register/ (тот же CUser::Add), почта nick@prognos9ys.ru
 *
 *   php local/tools/seed_cs2_iem_players_prod.php --dry-run
 *   php local/tools/seed_cs2_iem_players_prod.php
 *   php local/tools/seed_cs2_iem_players_prod.php --event=12345
 *   php local/tools/seed_cs2_iem_players_prod.php --only=donk,hally
 *   php local/tools/seed_cs2_iem_players_prod.php --skip-prognosis
 *
 * Событие по умолчанию: XML_ID cs2_iem_cologne_2026 в iblock events.
 * Группа 6: автоматически для @prognos9ys.ru (OnAfterUserAdd).
 * Пароли после сида: php local/tools/reset_cs2_seed_passwords.php --confirm  →  {login}26
 */

require_once __DIR__ . '/cs2_iem_roster_data.php';

const CS2_SEED_DEFAULT_AVA = '/upload/main/d8e/d8e464c093083bc55434c13989838971.jpeg';
const CS2_SEED_EVENT_XML_ID = 'cs2_iem_cologne_2026';

$docRoot = dirname(__DIR__, 2);
$_SERVER['DOCUMENT_ROOT'] = $docRoot;
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'prognos9ys.ru';
$_SERVER['SERVER_NAME'] = $_SERVER['SERVER_NAME'] ?? 'prognos9ys.ru';

define('NO_KEEP_STATISTIC', true);
define('NO_AGENT_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

require_once $docRoot . '/bitrix/modules/main/include/prolog_before.php';
\Bitrix\Main\Loader::includeModule('prognos9ys.main');
require_once $docRoot . '/local/classes/main/Prognos9ysAuthClass.php';

$argv = $argv ?? [];
$dryRun = in_array('--dry-run', $argv, true);
$skipProg = in_array('--skip-prognosis', $argv, true);
$eventId = parseCliArg($argv, '--event=') ?? '';
$onlyRaw = parseCliArg($argv, '--only=');
$only = $onlyRaw !== null && $onlyRaw !== ''
    ? array_map('strtolower', array_map('trim', explode(',', $onlyRaw)))
    : null;
$matchNumber = parseCliArg($argv, '--match=') ?? '';

if ($eventId === '') {
    $eventId = (string)resolveCs2EventId();
}
if ($eventId === '' || (int)$eventId <= 0) {
    echo "Укажите --event=ID (элемент events, XML_ID " . CS2_SEED_EVENT_XML_ID . ")\n";
    exit(1);
}

$people = cs2_iem_roster_people();
echo ($dryRun ? '[DRY RUN] ' : '[LIVE] ') . "event={$eventId}, accounts=" . count($people) . "\n\n";

$matchMeta = null;
if (!$dryRun && !$skipProg) {
    $matchMeta = pickActiveCs2Match((int)$eventId, $matchNumber);
    if ($matchMeta) {
        echo "Матч #{$matchMeta['number']} id={$matchMeta['id']} ({$matchMeta['bo']})\n\n";
    } else {
        echo "Активный матч не найден — регистрация без прогнозов\n\n";
    }
}

$outDir = __DIR__ . '/output';
if (!is_dir($outDir)) {
    mkdir($outDir, 0755, true);
}

$credentials = [];
$ok = 0;
$fail = 0;

foreach ($people as $i => $person) {
    if ($only !== null && !in_array(strtolower($person['login']), $only, true)) {
        continue;
    }

    $mail = $person['mail'];
    $pass = genSeedPassword();

    if ($dryRun) {
        echo str_pad($person['team'], 16) . " {$person['role']} " . str_pad($person['nick'], 12) . " <{$mail}>\n";
        continue;
    }

    try {
        $reg = registerCs2Person($person, $pass, $docRoot);
        if (($reg['status'] ?? '') !== 'ok') {
            echo "REG skip {$person['login']}: " . ($reg['mes'] ?? 'exists?') . "\n";
        }

        $token = loginSeedUser($mail, $pass);

        $progOk = true;
        $score = '-';
        if ($matchMeta) {
            $fields = buildCs2Prognosis($matchMeta, (int)$eventId, $i);
            $send = (new \Prognos9ys\Main\Service\Cs2\Cs2PrognosisService())->send(
                $token,
                $fields,
                $fields[29] ?? null,
                false
            );
            $progOk = ($send['status'] ?? '') === 'ok';
            $score = $fields[15] . ':' . $fields[16];
            if (!$progOk) {
                echo "FAIL prognosis {$person['login']}: " . json_encode($send, JSON_UNESCAPED_UNICODE) . "\n";
            }
        }

        if ($progOk) {
            $ok++;
            $credentials[] = [
                'team' => $person['team'],
                'role' => $person['role'],
                'nick' => $person['nick'],
                'mail' => $mail,
                'pass' => $pass,
                'score' => $score,
            ];
            echo 'OK  ' . str_pad($person['team'], 16) . " {$person['role']} " . str_pad($person['nick'], 12)
                . " grp:6 {$score}\n";
        } else {
            $fail++;
        }

        usleep(250000);
    } catch (Throwable $e) {
        $fail++;
        echo "ERR {$person['login']}: {$e->getMessage()}\n";
    }
}

echo "\nDone: {$ok} ok, {$fail} failed\n";
echo "Сброс паролей: php local/tools/reset_cs2_seed_passwords.php --confirm\n";

if ($credentials !== []) {
    $outFile = $outDir . '/cs2_iem_credentials.tsv';
    $header = "team\trole\tnick\tmail\tpass\tscore\n";
    $lines = array_map(
        static fn(array $r): string => implode("\t", [$r['team'], $r['role'], $r['nick'], $r['mail'], $r['pass'], $r['score']]),
        $credentials
    );
    file_put_contents($outFile, $header . implode("\n", $lines) . "\n", FILE_APPEND);
    echo "Credentials: {$outFile}\n";
}

/**
 * @param list<string> $argv
 */
function parseCliArg(array $argv, string $prefix): ?string
{
    foreach ($argv as $arg) {
        if (str_starts_with($arg, $prefix)) {
            return trim(substr($arg, strlen($prefix)));
        }
    }

    return null;
}

function genSeedPassword(): string
{
    $chars = 'abcdefghjkmnpqrstuvwxyz23456789';
    $len = strlen($chars) - 1;
    $s = '';
    for ($i = 0; $i < 8; $i++) {
        $s .= $chars[random_int(0, $len)];
    }

    return $s;
}

function resolveCs2EventId(): int
{
    if (!\Bitrix\Main\Loader::includeModule('iblock')) {
        return 0;
    }

    $iblock = CIBlock::GetList([], ['CODE' => 'events', 'TYPE' => 'content'])->Fetch();
    $iblockId = (int)($iblock['ID'] ?? 0);
    if ($iblockId <= 0) {
        return 0;
    }

    $el = CIBlockElement::GetList(
        [],
        ['IBLOCK_ID' => $iblockId, '=XML_ID' => CS2_SEED_EVENT_XML_ID],
        false,
        ['nTopCount' => 1],
        ['ID']
    )->Fetch();

    return (int)($el['ID'] ?? 0);
}

/**
 * @param array{team:string,tag:string,nick:string,login:string,role:string,mail:string,display:string} $person
 * @return array{status:string,mes?:string}
 */
function registerCs2Person(array $person, string $pass, string $docRoot): array
{
    $avaPath = $docRoot . CS2_SEED_DEFAULT_AVA;
    if (!is_file($avaPath)) {
        throw new RuntimeException('Default avatar not found: ' . CS2_SEED_DEFAULT_AVA);
    }

    $arImage = CFile::MakeFileArray($avaPath);
    $user = new CUser();
    $fields = [
        'NAME' => $person['display'],
        'EMAIL' => $person['mail'],
        'LOGIN' => $person['mail'],
        'LID' => 'ru',
        'ACTIVE' => 'Y',
        'GROUP_ID' => [3, 4],
        'PASSWORD' => $pass,
        'CONFIRM_PASSWORD' => $pass,
        'PERSONAL_PHOTO' => $arImage,
    ];

    $id = (int)$user->Add($fields);
    if ($user->LAST_ERROR) {
        $mes = explode('(', $user->LAST_ERROR)[0] . 'уже существует';

        return ['status' => 'error', 'mes' => $mes];
    }

    if ($id > 0) {
        try {
            \Prognos9ys\Main\Service\Game\RegistrationBonusService::onUserRegistered($id);
        } catch (Throwable $exception) {
            // не блокируем регистрацию
        }
    }

    return ['status' => 'ok'];
}

function loginSeedUser(string $mail, string $pass): string
{
    $auth = new Prognos9ysAuthClass([
        'type' => 'newLogin',
        'mail' => $mail,
        'pass' => $pass,
    ]);
    $res = $auth->result();
    if (($res['status'] ?? '') !== 'ok' || empty($res['info']['UF_TOKEN'])) {
        throw new RuntimeException((string)($res['mes'] ?? 'login failed'));
    }

    return (string)$res['info']['UF_TOKEN'];
}

/**
 * @return array{number:string,id:int,bo:string}|null
 */
function pickActiveCs2Match(int $eventId, string $matchNumber = ''): ?array
{
    $service = new \Prognos9ys\Main\Service\Cs2\Cs2MatchService();
    $wanted = $matchNumber !== '' ? [$matchNumber] : ['5', '6'];

    foreach ($wanted as $num) {
        $response = $service->getMatch((string)$eventId, (string)$num, '');
        $match = $response['result'] ?? null;
        if (!empty($match['id']) && ($match['active'] ?? '') === 'Y') {
            return [
                'number' => (string)$num,
                'id' => (int)$match['id'],
                'bo' => (string)($match['bo_format'] ?? 'bo3'),
            ];
        }
    }

    return null;
}

/**
 * @param array{number:string,id:int,bo:string} $matchMeta
 * @return array<int|string, mixed>
 */
function buildCs2Prognosis(array $matchMeta, int $eventId, int $index): array
{
    $bo = strtolower($matchMeta['bo']);
    $maxWin = $bo === 'bo1' ? 1 : ($bo === 'bo5' ? 3 : 2);
    $home = 1 + ($index % $maxWin);
    $guest = $index % 2;
    $diff = $home - $guest;
    $result = $diff > 0 ? 'п1' : ($diff < 0 ? 'п2' : '');

    $mapCount = $bo === 'bo1' ? 1 : ($bo === 'bo5' ? 3 : 2);
    $maps = [];
    for ($i = 0; $i < $mapCount; $i++) {
        $maps[] = [
            'slot' => $i + 1,
            'map_code' => '',
            'rounds_home' => 13 + ($i % 2),
            'rounds_guest' => 8 + ($i % 3),
        ];
    }
    $mapJson = json_encode($maps, JSON_UNESCAPED_UNICODE);

    return [
        30 => (int)$matchMeta['number'],
        17 => (int)$matchMeta['id'],
        15 => $home,
        16 => $guest,
        18 => $result,
        19 => $diff,
        28 => $home + $guest,
        32 => 45 + ($index % 11),
        20 => 50,
        21 => 1 + ($index % 3),
        22 => $index % 2,
        52 => $eventId,
        29 => $mapJson,
        'map_scores_json' => $mapJson,
    ];
}
