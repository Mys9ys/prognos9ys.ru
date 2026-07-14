<?php

require dirname(__DIR__, 2) . '/bootstrap.php';

\Bitrix\Main\Loader::includeModule('prognos9ys.main');

$data = mob_app_request_data();

if (!empty($data['event'])) {
    $service = new \Prognos9ys\Main\Service\Football\FootballRatingService();
    $viewerUserId = $service->resolveViewerUserId($data['userToken'] ?? $data['token'] ?? null);

    mob_app_json_response(
        $service->getByEvent(
            $data['event'],
            isset($data['setId']) ? (int)$data['setId'] : null,
            $viewerUserId,
            $data['selector'] ?? null,
            isset($data['limit']) ? (int)$data['limit'] : 50,
            isset($data['matchNumber']) ? (int)$data['matchNumber'] : null
        )
    );
}
