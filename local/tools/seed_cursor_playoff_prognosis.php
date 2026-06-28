<?php
declare(strict_types=1);

/**
 * Прогнозы на 1/16 плей-офф от учётки Cursor (запуск на сервере после git pull).
 *
 *   php7.4 local/tools/seed_cursor_playoff_prognosis.php --dry-run
 *   php7.4 local/tools/seed_cursor_playoff_prognosis.php --mail=Cursor@prognos9ys.ru --pass='...'
 *
 * По умолчанию: матчи 73–88, событие 63849.
 */

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

use Prognos9ys\Main\Service\Championship\PlayoffSlotHelper;
use Prognos9ys\Main\Service\Football\FootballMatchService;
use Prognos9ys\Main\Service\Football\FootballPrognosisService;

$argv = $argv ?? [];
$dryRun = in_array('--dry-run', $argv, true);
$eventId = (int)(parseCliArg($argv, '--event=') ?: 63849);
$from = (int)(parseCliArg($argv, '--from=') ?: 73);
$to = (int)(parseCliArg($argv, '--to=') ?: 88);
$mail = parseCliArg($argv, '--mail=') ?: 'Cursor@prognos9ys.ru';
$pass = parseCliArg($argv, '--pass=') ?: (getenv('CURSOR_PASS') ?: '');

if ($pass === '') {
    fwrite(STDERR, "Укажите --pass=... или переменную CURSOR_PASS\n");
    exit(1);
}

echo ($dryRun ? '[DRY RUN] ' : '[LIVE] ') . "event={$eventId} matches {$from}..{$to} mail={$mail}\n";

$token = loginUser($mail, $pass);
echo "Token OK\n";

$service = new FootballMatchService();
$prognosisService = new FootballPrognosisService();
$ok = 0;
$skip = 0;
$fail = 0;

for ($number = $from; $number <= $to; $number++) {
    $response = $service->getMatch((string)$eventId, (string)$number, $token);
    $match = $response['result'] ?? null;
    $matchId = (int)($match['id'] ?? 0);

    if ($matchId <= 0) {
        echo "SKIP #{$number}: матч не найден\n";
        $skip++;
        continue;
    }

    $stage = (string)($match['stage'] ?? $match['match_result']['stage'] ?? '');
    $isPlayoff = $stage === 'Плей-офф' || PlayoffSlotHelper::isPlayoffMatch([
        'stage' => $stage,
        'stage_detail' => (string)($match['step'] ?? ''),
        'bracket_code' => '',
    ]);

    $fields = buildPlayoffPrognosis($number, $matchId, $eventId, $isPlayoff);
    $score = $fields[15] . ':' . $fields[16];
    $line = "#{$number} id={$matchId} {$score} {$fields[18]}";

    if ($dryRun) {
        echo "DRY {$line}\n";
        $ok++;
        continue;
    }

    $send = $prognosisService->send($token, $fields, false);
    if (($send['status'] ?? '') === 'ok') {
        echo "OK  {$line}\n";
        $ok++;
    } else {
        echo "FAIL {$line}: " . json_encode($send, JSON_UNESCAPED_UNICODE) . "\n";
        $fail++;
    }

    usleep(150000);
}

echo "\nDone: ok={$ok} skip={$skip} fail={$fail}\n";

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

function loginUser(string $mail, string $pass): string
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
 * @return array<int|string, mixed>
 */
function buildPlayoffPrognosis(int $number, int $matchId, int $eventId, bool $isPlayoff): array
{
    $patterns = [
        [2, 1, 'п1'],
        [1, 0, 'п1'],
        [2, 0, 'п1'],
        [0, 1, 'п2'],
        [1, 2, 'п2'],
        [0, 2, 'п2'],
        [3, 1, 'п1'],
        [1, 3, 'п2'],
        [2, 1, 'п1'],
        [3, 2, 'п1'],
        [2, 3, 'п2'],
        [1, 0, 'п1'],
        [2, 0, 'п1'],
        [0, 1, 'п2'],
        [3, 1, 'п1'],
        [2, 1, 'п1'],
    ];

    $idx = ($number - 73) % count($patterns);
    [$hg, $ag, $result] = $patterns[$idx];

    $otime = ($number % 3 === 0) ? 'Будет' : 'Не будет';
    $spenalty = ($number % 5 === 0) ? 'Будет' : 'Не будет';

    return [
        30 => $number,
        17 => $matchId,
        15 => $hg,
        16 => $ag,
        18 => $result,
        19 => $hg - $ag,
        28 => $hg + $ag,
        32 => 45 + ($number % 11),
        21 => 2 + ($number % 4),
        22 => $number % 2,
        20 => 7 + ($number % 5),
        23 => $number % 3,
        52 => $eventId,
        45 => $isPlayoff ? $otime : '',
        46 => $isPlayoff ? $spenalty : '',
        29 => '',
    ];
}
