<?php
declare(strict_types=1);

/**
 * Сброс паролей для болельщиков/правителей ЧМ (fanm*/fanf*/ruler*) на prognos9ys.ru
 * Пароль: {login}26  например fanmarg26
 *
 *   php local/tools/reset_wc_fan_passwords.php --dry-run
 *   php local/tools/reset_wc_fan_passwords.php --confirm
 */

$docRoot = dirname(__DIR__, 2);
$_SERVER['DOCUMENT_ROOT'] = $docRoot;
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require_once $docRoot . '/bitrix/modules/main/include/prolog_before.php';

$dryRun = in_array('--dry-run', $argv ?? [], true);
$confirm = in_array('--confirm', $argv ?? [], true);

if (!$dryRun && !$confirm) {
    echo "Usage: php reset_wc_fan_passwords.php --dry-run|--confirm\n";
    exit(1);
}

$rs = CUser::GetList($by = 'id', $order = 'asc', [
    'ACTIVE' => 'Y',
    '%EMAIL' => '@prognos9ys.ru',
]);

$updated = 0;
$skipped = 0;

while ($row = $rs->Fetch()) {
    $login = (string)($row['LOGIN'] ?? '');
    $email = (string)($row['EMAIL'] ?? '');
    $id = (int)($row['ID'] ?? 0);

    if ($id <= 0) {
        continue;
    }

    $local = strtolower(strstr($email, '@', true) ?: $login);
    $isFan = (bool)preg_match('/^(fanm|fanf|ruler)/i', $local);
    if (!$isFan) {
        $skipped++;
        continue;
    }

    $pass = $local . '26';

    if ($dryRun) {
        echo "#{$id}\t{$email}\t{$pass}\n";
        $updated++;
        continue;
    }

    $user = new CUser();
    if ($user->Update($id, [
        'PASSWORD' => $pass,
        'CONFIRM_PASSWORD' => $pass,
    ])) {
        echo "OK #{$id} {$email} => {$pass}\n";
        $updated++;
    } else {
        echo "FAIL #{$id} {$email}: {$user->LAST_ERROR}\n";
    }
}

echo "\n" . ($dryRun ? 'Would update' : 'Updated') . ": {$updated}, skipped: {$skipped}\n";
