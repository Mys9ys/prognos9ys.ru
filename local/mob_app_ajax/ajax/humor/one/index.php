<?php

require dirname(__DIR__, 2) . '/bootstrap.php';

mob_app_json_response((new Prognos9ysHumorHandler())->result());
