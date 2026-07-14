<?php

/**
 * CLI: освободить участок усадьбы.
 *
 * Пример:
 *   php7.4 local/modules/prognos9ys.main/release_estate_plot.php cpv 15
 *   php7.4 local/modules/prognos9ys.main/release_estate_plot.php cpv 15 --no-refund
 */

use Prognos9ys\Main\Service\Game\EstatePlotService;

$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../../../..') ?: ($_SERVER['DOCUMENT_ROOT'] ?? '');
$documentRoot = rtrim((string)$_SERVER['DOCUMENT_ROOT'], '/\\');

require_once $documentRoot . '/bitrix/modules/main/include/prolog_before.php';

\Bitrix\Main\Loader::includeModule('prognos9ys.main');

$citySlug = strtolower(trim((string)($argv[1] ?? '')));
$plotNumber = (int)($argv[2] ?? 0);
$refundCert = !in_array('--no-refund', $argv, true);

if ($citySlug === '' || $plotNumber <= 0) {
    fwrite(STDERR, "Usage: php release_estate_plot.php <citySlug> <plotNumber> [--no-refund]\n");
    fwrite(STDERR, "Example: php release_estate_plot.php cpv 15\n");
    exit(1);
}

try {
    $result = (new EstatePlotService())->releasePlot(
        1,
        $citySlug,
        $plotNumber,
        true,
        $refundCert
    );

    echo json_encode([
        'status' => 'ok',
        'result' => $result,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
} catch (\Throwable $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
