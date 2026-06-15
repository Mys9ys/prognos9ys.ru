<?php

require dirname(__DIR__) . '/bootstrap.php';

$data = mob_app_request_data();

if ($data) {
  if (!empty($data['token'])) {
    $data['userToken'] = $data['token'];
  }

  mob_app_json_response((new ProfileHandlerClass($data))->result());
}
