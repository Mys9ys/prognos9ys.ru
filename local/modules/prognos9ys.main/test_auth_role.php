<?php

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../../..');
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

$userId = (int)($argv[1] ?? 1);

$row = \Bitrix\Main\UserTable::getRow([
    'filter' => ['=ID' => $userId],
    'select' => ['ID', 'LOGIN', 'UF_TOKEN', 'NAME'],
]);

if (!$row) {
    echo 'user not found' . PHP_EOL;
    exit(1);
}

echo 'user #' . $row['ID'] . ' ' . $row['NAME'] . PHP_EOL;
echo 'groups: ' . implode(',', CUser::GetUserGroup($userId)) . PHP_EOL;
echo 'role by token: ' . (new GetUserRole($row['UF_TOKEN']))->result() . PHP_EOL;

$auth = new Prognos9ysAuthClass([
    'type' => 'tokenLogin',
    'token' => $row['UF_TOKEN'],
]);
$result = $auth->result();
echo 'auth status: ' . ($result['status'] ?? '') . PHP_EOL;
echo 'auth role: ' . ($result['info']['role'] ?? 'missing') . PHP_EOL;
