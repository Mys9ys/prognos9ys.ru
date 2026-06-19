<?php
declare(strict_types=1);

/**
 * Проверка: созданы ли CS2 seed-аккаунты на сервере.
 *
 *   php local/tools/check_cs2_seed_users.php
 */

require_once __DIR__ . '/cs2_iem_roster_data.php';

$docRoot = dirname(__DIR__, 2);
$_SERVER['DOCUMENT_ROOT'] = $docRoot;
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
require_once $docRoot . '/bitrix/modules/main/include/prolog_before.php';

$people = cs2_iem_roster_people();
$found = 0;
$missing = [];

foreach ($people as $person) {
    $mail = strtolower($person['mail']);
    $row = CUser::GetList($by = 'id', $order = 'asc', ['=EMAIL' => $mail])->Fetch();
    if (!empty($row['ID'])) {
        $found++;
        echo "OK  #{$row['ID']}\t{$mail}\t{$row['NAME']}\n";
    } else {
        $missing[] = $mail;
        echo "MISS\t{$mail}\n";
    }
}

echo "\nИтого: {$found}/" . count($people) . " зарегистрировано\n";

if ($missing !== []) {
    echo "\nЗапустите регистрацию:\n";
    echo "  php local/tools/seed_cs2_iem_players_prod.php --skip-prognosis\n";
    exit(1);
}

exit(0);
