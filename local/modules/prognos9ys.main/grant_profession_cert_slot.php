<?php

declare(strict_types=1);

/**
 * Компенсация: +1 слот профессии без списания сертификата.
 * Использовать, если активация съела cert_profession, но UF_PROFESSION_CERT_SLOTS не сохранился.
 *
 * php7.4 local/modules/prognos9ys.main/grant_profession_cert_slot.php USER_ID
 */

use Bitrix\Main\Loader;
use Prognos9ys\Main\Model\Repository\GameEconomyRepository;

$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../../..');
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

if (!Loader::includeModule('prognos9ys.main')) {
    fwrite(STDERR, "Module prognos9ys.main not loaded\n");
    exit(1);
}

$userId = (int)($argv[1] ?? 0);
if ($userId <= 0) {
    fwrite(STDERR, "Usage: php7.4 grant_profession_cert_slot.php USER_ID\n");
    exit(1);
}

try {
    $repository = new GameEconomyRepository();
    $before = $repository->getProfessionCertSlots($userId);
    $after = $repository->incrementProfessionCertSlots($userId);
    echo 'User #' . $userId . ': profession cert slots ' . $before . ' -> ' . $after . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
