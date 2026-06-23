<?php
declare(strict_types=1);

/**
 * Дамп MySQL с боя (crown) для переноса на локалку.
 *
 *   php7.4 local/tools/prod_db_dump.php
 *   php7.4 local/tools/prod_db_dump.php --dry-run
 *   php7.4 local/tools/prod_db_dump.php --output=/tmp/prognos9ys.sql.gz
 *
 * Результат: .osp/backup/db/prognos9ys_YYYYMMDD_HHMMSS.sql.gz
 * Скачать на Windows: см. local\tools\download_prod_db.bat
 */

require_once __DIR__ . '/db_sync_lib.php';

$argv = $argv ?? [];
$dryRun = dbSyncHasCliFlag($argv, '--dry-run');
$outputArg = dbSyncCliArg($argv, '--output=');

$docRoot = dbSyncDocRoot();
$db = dbSyncReadBitrixDbConfig($docRoot);
$env = dbSyncReadEnvConfig($docRoot);
$backupDir = dbSyncBackupDir($docRoot);

$timestamp = date('Ymd_His');
$output = $outputArg !== null && $outputArg !== ''
    ? $outputArg
    : $backupDir . '/prognos9ys_' . $timestamp . '.sql.gz';

if (!preg_match('/\.gz$/i', $output)) {
    $output .= '.gz';
}

echo ($dryRun ? '[DRY RUN] ' : '[LIVE] ') . "MySQL dump\n";
echo "Database: {$db['database']} @ {$db['host']}:{$db['port']}\n";
echo "Output:   {$output}\n\n";

if (!$dryRun) {
    dbSyncEnsureDir(dirname($output));
}

$dumpCmd = dbSyncBuildMysqldumpCmd($db);
$command = $dumpCmd . ' | gzip -c > ' . dbSyncEscapeShellArg($output);

echo dbSyncMaskPasswordInCommand($command) . "\n\n";

if ($dryRun) {
    echo "Готово (dry-run).\n";
    exit(0);
}

passthru($command, $exitCode);
if ($exitCode !== 0) {
    fwrite(STDERR, "mysqldump failed, exit={$exitCode}\n");
    exit(1);
}

if (!is_file($output)) {
    fwrite(STDERR, "Файл не создан: {$output}\n");
    exit(1);
}

$size = filesize($output);
echo "OK: " . dbSyncFormatBytes((int)$size) . "\n";
$remoteRel = '.osp/backup/db/' . basename($output);
echo "Скачать на локалку:\n";
echo '  ' . dbSyncBuildScpDownloadHint($env, $remoteRel, '.osp\\backup\\db\\' . basename($output)) . "\n";
echo "Или: local\\tools\\download_prod_db.bat\n";
echo "Импорт:\n";
echo '  local\\tools\\local_db_import.bat .osp\\backup\\db\\' . basename($output) . " --confirm --sanitize\n";
