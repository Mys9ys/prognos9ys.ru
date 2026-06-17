<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

define('NO_KEEP_STATISTIC', true);
define('NO_AGENT_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

require_once $_SERVER['DOCUMENT_ROOT'] . '/local/tools/add_seed_users_to_group_6.php';

$key = (string)($_GET['key'] ?? $_POST['key'] ?? '');
$confirm = (string)($_GET['confirm'] ?? $_POST['confirm'] ?? '');

if ($key !== SEED_GROUP_HTTP_KEY) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($confirm !== '1') {
    http_response_code(400);
    echo json_encode(['error' => 'pass confirm=1'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $result = add_seed_users_to_group_6(SEED_GROUP_ID);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
