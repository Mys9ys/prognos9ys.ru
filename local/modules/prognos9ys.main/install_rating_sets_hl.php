<?php

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../../..');
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Prognos9ys\Main\Service\Rating\RatingSetHlInstaller;

try {
    $result = (new RatingSetHlInstaller())->install();
    echo 'HL blocks installed:' . PHP_EOL;
    foreach ($result as $key => $value) {
        echo '  ' . $key . ': ' . $value . PHP_EOL;
    }
} catch (\Throwable $e) {
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
