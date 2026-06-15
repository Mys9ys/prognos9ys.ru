<?php

require dirname(__DIR__) . '/bootstrap.php';

$data = mob_app_request_data();

if ($data) {
    mob_app_json_response((new CatalogEvents($data))->result());
}
