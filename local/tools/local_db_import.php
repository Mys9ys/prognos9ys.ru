<?php
declare(strict_types=1);

/**
 * Импорт дампа с боя в локальную OSPanel-копию.
 *
 *   php local/tools/local_db_import.php .osp/backup/db/prognos9ys_20260622_120000.sql.gz
 *   php local/tools/local_db_import.php path/to/dump.sql.gz --confirm
 *   php local/tools/local_db_import.php path/to/dump.sql.gz --confirm --sanitize
 *   php local/tools/local_db_import.php path/to/dump.sql.gz --dry-run
 *
 * Настройки домена: .osp/env.ini
 *   DB_SYNC_PROD_HOST=prognos9ys.ru
 *   DB_SYNC_LOCAL_HOST=prognos9ys
 *   DB_SYNC_LOCAL_SCHEME=http
 *   MYSQL_BIN=C:\OSPanel\modules\database\MySQL-8.0-Win10\bin
 */

require_once __DIR__ . '/db_sync_lib.php';

$argv = $argv ?? [];
$dryRun = dbSyncHasCliFlag($argv, '--dry-run');
$confirm = dbSyncHasCliFlag($argv, '--confirm');
$sanitize = dbSyncHasCliFlag($argv, '--sanitize');
$skipRecreate = dbSyncHasCliFlag($argv, '--skip-recreate');

$dumpFile = null;
foreach ($argv as $i => $arg) {
    if ($i === 0 || strpos($arg, '--') === 0) {
        continue;
    }
    $dumpFile = $arg;
    break;
}

if ($dumpFile === null || $dumpFile === '') {
    echo "Импорт дампа БД с боя в локальную копию.\n\n";
    echo "Usage:\n";
    echo "  php local/tools/local_db_import.php <dump.sql.gz> [--dry-run]\n";
    echo "  php local/tools/local_db_import.php <dump.sql.gz> --confirm [--sanitize]\n\n";
    echo "Или через bat:\n";
    echo "  local\\tools\\local_db_import.bat .osp\\backup\\db\\prognos9ys_YYYYMMDD.sql.gz --confirm --sanitize\n";
    exit(1);
}

$docRoot = dbSyncDocRoot();
$dumpPath = $dumpFile;
if (!preg_match('~^([a-zA-Z]:\\\\|/)~', $dumpFile)) {
    $dumpPath = $docRoot . '/' . ltrim(str_replace('\\', '/', $dumpFile), '/');
}

if (!is_file($dumpPath)) {
    fwrite(STDERR, "Файл не найден: {$dumpPath}\n");
    exit(1);
}

$db = dbSyncReadBitrixDbConfig($docRoot);
$env = dbSyncReadEnvConfig($docRoot);
$mysqlBin = $env['mysql_bin'];

echo ($dryRun ? '[DRY RUN] ' : ($confirm ? '[LIVE] ' : '[PREVIEW] ')) . "local DB import\n";
echo "Dump:     {$dumpPath} (" . dbSyncFormatBytes((int)filesize($dumpPath)) . ")\n";
echo "Database: {$db['database']} @ {$db['host']}:{$db['port']}\n";
echo "URL:      {$env['prod_host']} → {$env['local_scheme']}://{$env['local_host']}\n";
if ($mysqlBin) {
    echo "MySQL:    {$mysqlBin}\n";
}
echo "\n";

if (!$confirm && !$dryRun) {
    echo "ВНИМАНИЕ: локальная БД `{$db['database']}` будет пересоздана и перезаписана.\n";
    echo "Продолжить:\n";
    echo "  php local/tools/local_db_import.php \"{$dumpFile}\" --confirm\n";
    if (DIRECTORY_SEPARATOR === '\\') {
        echo "  local\\tools\\local_db_import.bat \"{$dumpFile}\" --confirm --sanitize\n";
    }
    exit(1);
}

try {
    if (!$skipRecreate) {
        dbSyncRecreateDatabase($db, $mysqlBin, $dryRun);
    }

    dbSyncImportSqlFile($db, $dumpPath, $mysqlBin, $dryRun);
    dbSyncReplaceProdUrls($db, $env, $mysqlBin, $dryRun);
    if ($sanitize) {
        dbSyncSanitizeLocal($db, $mysqlBin, $dryRun);
    }
    dbSyncClearBitrixCache($docRoot, $dryRun);
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . "\n");
    exit(1);
}

echo "\nГотово.\n";
if (!$dryRun) {
    echo "Откройте: {$env['local_scheme']}://{$env['local_host']}\n";
    if ($sanitize) {
        echo "Агенты и SMTP отключены (--sanitize).\n";
    }
}
