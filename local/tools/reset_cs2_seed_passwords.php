<?php
declare(strict_types=1);

/**
 * Сброс паролей seed-аккаунтов CS2 IEM на prognos9ys.ru
 * Пароль: {login}26  например donk26, hally26
 *
 *   php local/tools/reset_cs2_seed_passwords.php --dry-run
 *   php local/tools/reset_cs2_seed_passwords.php --confirm
 */

require_once __DIR__ . '/cs2_iem_roster_data.php';

$docRoot = dirname(__DIR__, 2);
$_SERVER['DOCUMENT_ROOT'] = $docRoot;
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require_once $docRoot . '/bitrix/modules/main/include/prolog_before.php';

$dryRun = in_array('--dry-run', $argv ?? [], true);
$confirm = in_array('--confirm', $argv ?? [], true);

if (!$dryRun && !$confirm) {
    echo "Usage: php reset_cs2_seed_passwords.php --dry-run|--confirm\n";
    exit(1);
}

$rs = CUser::GetList($by = 'id', $order = 'asc', [
    'ACTIVE' => 'Y',
    '%EMAIL' => '@prognos9ys.ru',
]);

$seedMails = cs2_iem_seed_mail_map();
$updated = 0;
$skipped = 0;

while ($row = $rs->Fetch()) {
    $login = (string)($row['LOGIN'] ?? '');
    $email = strtolower((string)($row['EMAIL'] ?? ''));
    $id = (int)($row['ID'] ?? 0);

    if ($id <= 0) {
        continue;
    }

    if (!isset($seedMails[$email])) {
        $skipped++;
        continue;
    }

    $local = strtolower(strstr($email, '@', true) ?: $login);

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
