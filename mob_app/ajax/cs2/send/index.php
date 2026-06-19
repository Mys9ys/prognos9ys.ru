<?php

require dirname(__DIR__, 2) . '/bootstrap.php';

$data = mob_app_request_data();

if ($data) {
    $token = (string)($data['userToken'] ?? '');
    $fields = $data['fields'] ?? [];

    if (!empty($data['map_scores_json'])) {
        $fields[29] = $data['map_scores_json'];
    }

    $withBet = $data['withBet'] ?? null;
    if ($withBet !== null && $withBet !== '') {
        $withBet = filter_var($withBet, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    } else {
        $withBet = null;
    }

    $service = new \Prognos9ys\Main\Service\Cs2\Cs2PrognosisService();
    mob_app_json_response($service->send($token, $fields, $withBet));
}
