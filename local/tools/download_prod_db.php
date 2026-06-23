<?php
declare(strict_types=1);

/**
 * Печатает команду scp для download_prod_db.bat (только stdout, одна строка).
 */

require_once __DIR__ . '/db_sync_lib.php';

$argv = $argv ?? [];
$fileArg = null;
foreach ($argv as $i => $arg) {
    if ($i === 0 || strpos($arg, '--') === 0) {
        continue;
    }
    $fileArg = $arg;
    break;
}

$docRoot = dbSyncDocRoot();
$env = dbSyncReadEnvConfig($docRoot);
$localDir = $docRoot . '/.osp/backup/db';
if (!is_dir($localDir)) {
    mkdir($localDir, 0755, true);
}

if ($fileArg !== null && $fileArg !== '') {
    $fileName = basename(str_replace('\\', '/', $fileArg));
    $remoteRel = '.osp/backup/db/' . $fileName;
    $localTarget = '.osp\\backup\\db\\' . $fileName;
} else {
    $remoteRel = '.osp/backup/db/prognos9ys_*.sql.gz';
    $localTarget = '.osp\\backup\\db\\';
}

echo dbSyncBuildScpDownloadHint($env, $remoteRel, $localTarget);
