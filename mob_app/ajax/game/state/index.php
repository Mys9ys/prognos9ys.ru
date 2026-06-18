<?php

require dirname(__DIR__, 2) . '/bootstrap.php';

use Bitrix\Main\Loader;

$data = mob_app_request_data();
$token = trim((string)($data['userToken'] ?? $data['token'] ?? ''));

if ($token === '') {
    mob_app_json_response(['status' => 'error', 'mes' => 'Токен не передан']);
    return;
}

$userId = (int)((new GetUserIdForToken($token))->getId() ?? 0);

if ($userId <= 0) {
    mob_app_json_response(['status' => 'error', 'mes' => 'Пользователь не найден']);
    return;
}

if (!Loader::includeModule('prognos9ys.main')) {
    mob_app_json_response(['status' => 'error', 'mes' => 'Игровой модуль недоступен']);
    return;
}

$game = (new \Prognos9ys\Main\Service\Game\GameProfileService())->getSummary($userId);

mob_app_json_response([
    'status' => 'ok',
    'game' => $game,
]);
