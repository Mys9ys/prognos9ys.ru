<?php
declare(strict_types=1);

/**
 * Регистрация болельщиков (М/Ж) и правителей 48 сборных ЧМ-2026 на сервере.
 * Группа 6: автоматически для @prognos9ys.ru (OnAfterUserAdd).
 *
 *   php local/tools/seed_wc_fans_prod.php --dry-run
 *   php local/tools/seed_wc_fans_prod.php
 *   php local/tools/seed_wc_fans_prod.php --only=fanmarg,rulerarg
 *   php local/tools/seed_wc_fans_prod.php --skip-prognosis
 *
 * Пароли после сида: php local/tools/reset_wc_fan_passwords.php --confirm  →  {login}26
 */

require_once __DIR__ . '/wc_fans_roster_data.php';

const WC_FANS_DEFAULT_AVA = '/upload/main/d8e/d8e464c093083bc55434c13989838971.jpeg';
const WC_FANS_EVENT_ID = 63849;
const WC_FANS_MATCH_NUMBER = 22;

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

use Bitrix\Main\UserTable;

$argv = $argv ?? [];
$dryRun = in_array('--dry-run', $argv, true);
$skipProg = in_array('--skip-prognosis', $argv, true);
$onlyRaw = parseCliArg($argv, '--only=');
$only = $onlyRaw !== null && $onlyRaw !== ''
    ? array_map('strtolower', array_map('trim', explode(',', $onlyRaw)))
    : null;

$people = wc_fans_roster_people();
echo ($dryRun ? '[DRY RUN] ' : '[LIVE] ') . 'accounts=' . count($people) . "\n\n";

$match = null;
if (!$dryRun) {
    $ava = resolveWcSeedAvatar($docRoot);
    echo 'Avatar: ' . $ava['source'] . "\n";
}
if (!$dryRun && !$skipProg) {
    $match = loadWcFanMatch(WC_FANS_EVENT_ID, WC_FANS_MATCH_NUMBER);
    if ($match) {
        echo "Матч #{$match['number']} id={$match['id']}\n\n";
    } else {
        echo "Матч не найден — регистрация без прогнозов\n\n";
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
    $pass = genWcFanPassword();
    $nick = $person['name'] . ' (' . $person['role'] . ')';

    if ($dryRun) {
        echo str_pad($person['team'], 22) . ' ' . str_pad($person['role'], 5) . ' '
            . str_pad($person['name'], 16) . " <{$mail}>\n";
        continue;
    }

    try {
        $reg = registerWcFanPerson($nick, $mail, $pass, $docRoot);
        if (($reg['status'] ?? '') !== 'ok') {
            echo "REG skip {$person['login']}: " . ($reg['mes'] ?? 'exists?') . "\n";
        }

        $token = loginWcFanUser($mail, $pass);

        $progOk = true;
        $score = '-';
        if ($match) {
            $fields = buildWcFanPrognosis($match, WC_FANS_EVENT_ID, $i);
            $send = (new \Prognos9ys\Main\Service\Football\FootballPrognosisService())->send(
                $token,
                $fields,
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
                'name' => $person['name'],
                'mail' => $mail,
                'pass' => $pass,
                'score' => $score,
            ];
            echo 'OK  ' . str_pad($person['team'], 22) . ' ' . str_pad($person['role'], 5) . ' '
                . str_pad($person['name'], 16) . " grp:6 {$score}\n";
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
echo "Сброс паролей: php local/tools/reset_wc_fan_passwords.php --confirm\n";

if ($credentials !== []) {
    $outFile = $outDir . '/wc_fans_credentials.tsv';
    $header = "team\trole\tname\tmail\tpass\tscore\n";
    $lines = array_map(
        static fn(array $r): string => implode("\t", [$r['team'], $r['role'], $r['name'], $r['mail'], $r['pass'], $r['score']]),
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
        if (strpos($arg, $prefix) === 0) {
            return trim(substr($arg, strlen($prefix)));
        }
    }

    return null;
}

function genWcFanPassword(): string
{
    $chars = 'abcdefghjkmnpqrstuvwxyz23456789';
    $len = strlen($chars) - 1;
    $s = '';
    for ($i = 0; $i < 8; $i++) {
        $s .= $chars[random_int(0, $len)];
    }

    return $s;
}

/**
 * @return array{number:int,id:int}|null
 */
function loadWcFanMatch(int $eventId, int $matchNumber): ?array
{
    $service = new \Prognos9ys\Main\Service\Football\FootballMatchService();
    $response = $service->getMatch((string)$eventId, (string)$matchNumber, '');
    $match = $response['result'] ?? null;

    if (empty($match['id'])) {
        return null;
    }

    return [
        'number' => $matchNumber,
        'id' => (int)$match['id'],
    ];
}

/**
 * @return array{status:string,mes?:string}
 */
function registerWcFanPerson(string $nick, string $mail, string $pass, string $docRoot): array
{
    $avatar = resolveWcSeedAvatar($docRoot);

    $user = new CUser();
    $fields = [
        'NAME' => $nick,
        'EMAIL' => $mail,
        'LOGIN' => $mail,
        'LID' => 'ru',
        'ACTIVE' => 'Y',
        'GROUP_ID' => [3, 4],
        'PASSWORD' => $pass,
        'CONFIRM_PASSWORD' => $pass,
    ];
    if ($avatar['file']) {
        $fields['PERSONAL_PHOTO'] = $avatar['file'];
    }

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
        try {
            \Prognos9ys\Main\Service\Game\SeedUserGroupService::onUserRegistered($id);
        } catch (Throwable $exception) {
            // не блокируем регистрацию
        }
    }

    return ['status' => 'ok'];
}

/**
 * Дефолтная аватарка из upload может отсутствовать на бою — берём запасные варианты.
 *
 * @return array{file: ?array, source: string}
 */
function resolveWcSeedAvatar(string $docRoot): array
{
    static $resolved = null;
    if ($resolved !== null) {
        return $resolved;
    }

    $candidates = [
        WC_FANS_DEFAULT_AVA,
        '/local/components/prognos9ys/header.block/templates/.default/assets/img/ava.jpg',
    ];

    foreach ($candidates as $rel) {
        $path = $docRoot . $rel;
        if (is_file($path)) {
            $resolved = ['file' => CFile::MakeFileArray($path), 'source' => $rel];

            return $resolved;
        }
    }

    $row = UserTable::getList([
        'select' => ['PERSONAL_PHOTO'],
        'filter' => [
            '=ACTIVE' => 'Y',
            '>PERSONAL_PHOTO' => 0,
            '%EMAIL' => '@prognos9ys.ru',
        ],
        'order' => ['ID' => 'DESC'],
        'limit' => 1,
    ])->fetch();

    if (!empty($row['PERSONAL_PHOTO'])) {
        $file = CFile::GetFileArray((int)$row['PERSONAL_PHOTO']);
        if ($file && !empty($file['SRC'])) {
            $path = $docRoot . $file['SRC'];
            if (is_file($path)) {
                $resolved = [
                    'file' => CFile::MakeFileArray($path),
                    'source' => 'borrowed ' . $file['SRC'],
                ];

                return $resolved;
            }
        }
    }

    $resolved = ['file' => null, 'source' => 'none (без аватарки)'];

    return $resolved;
}

function loginWcFanUser(string $mail, string $pass): string
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
 * @param array{number:int,id:int} $match
 * @return array<int|string, mixed>
 */
function buildWcFanPrognosis(array $match, int $eventId, int $index): array
{
    $hg = 1 + ($index % 3);
    $ag = ($index + 1) % 4;
    $diff = $hg - $ag;
    $result = 'н';
    if ($diff > 0) {
        $result = 'п1';
    }
    if ($diff < 0) {
        $result = 'п2';
    }

    return [
        30 => $match['number'],
        17 => $match['id'],
        15 => $hg,
        16 => $ag,
        18 => $result,
        19 => $diff,
        28 => $hg + $ag,
        32 => 40 + ($index % 15),
        21 => 1 + ($index % 3),
        22 => $index % 2,
        20 => 4 + ($index % 4),
        23 => 0,
        52 => $eventId,
        45 => '',
        46 => '',
        29 => '',
    ];
}
