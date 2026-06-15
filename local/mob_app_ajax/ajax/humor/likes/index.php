<?php

require dirname(__DIR__, 2) . '/bootstrap.php';

$data = mob_app_request_data();

if ($data) {
    mob_app_json_response((new Prognos9ysHumorHandler())->setLike($data));
}
