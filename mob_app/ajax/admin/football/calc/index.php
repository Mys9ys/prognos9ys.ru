<?php

require dirname(__DIR__, 3) . '/bootstrap.php';

@set_time_limit(300);

$data = mob_app_request_data();

if ($data) {
    mob_app_json_response((new CalcFootballPrognosisResult($data))->result());
}
