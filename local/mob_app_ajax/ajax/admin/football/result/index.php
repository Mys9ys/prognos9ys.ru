<?php

require dirname(__DIR__, 3) . '/bootstrap.php';

$data = mob_app_request_data();

if ($data) {
    mob_app_json_response((new FootballSetResult($data))->result());
}
