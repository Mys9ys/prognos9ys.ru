<?php

require dirname(__DIR__, 2) . '/bootstrap.php';

\Bitrix\Main\Loader::includeModule('prognos9ys.main');

$data = mob_app_request_data();

if (!empty($data['event'])) {
    mob_app_json_response(
        (new \Prognos9ys\Main\Service\Football\FootballRatingService())->getByEvent($data['event'])
    );
}
