<?php
declare(strict_types=1);

/**
 * Общие функции для синхронизации БД бой ↔ локалка.
 */

/**
 * @return array{host:string,port:int,database:string,login:string,password:string}
 */
function dbSyncReadBitrixDbConfig(string $docRoot): array
{
    $path = rtrim($docRoot, '/\\') . '/bitrix/.settings.php';
    if (!is_file($path)) {
        throw new RuntimeException('Не найден bitrix/.settings.php: ' . $path);
    }

    /** @var array<string, mixed> $settings */
    $settings = include $path;
    $connections = $settings['connections']['value'] ?? $settings['connections'] ?? null;
    if (!is_array($connections)) {
        throw new RuntimeException('В .settings.php нет секции connections');
    }

    $default = $connections['default'] ?? null;
    if (!is_array($default)) {
        throw new RuntimeException('В .settings.php нет connections.default');
    }

    $host = (string)($default['host'] ?? 'localhost');
    $port = 3306;
    if (strpos($host, ':') !== false) {
        [$host, $portRaw] = explode(':', $host, 2);
        $port = (int)$portRaw;
    }

    return [
        'host' => $host,
        'port' => $port,
        'database' => (string)($default['database'] ?? ''),
        'login' => (string)($default['login'] ?? ''),
        'password' => (string)($default['password'] ?? ''),
    ];
}

function dbSyncDocRoot(): string
{
    return dirname(__DIR__, 2);
}

function dbSyncBackupDir(string $docRoot): string
{
    return rtrim($docRoot, '/\\') . '/.osp/backup/db';
}

/**
 * @return array{
 *   prod_host:string,
 *   local_host:string,
 *   local_scheme:string,
 *   mysql_bin:?string,
 *   prod_ssh_user:string,
 *   prod_ssh_host:string
 * }
 */
function dbSyncReadEnvConfig(string $docRoot): array
{
    $defaults = [
        'prod_host' => 'prognos9ys.ru',
        'local_host' => 'prognos9ys',
        'local_scheme' => 'http',
        'mysql_bin' => dbSyncDiscoverOspanelMysqlBin(),
        'prod_ssh_user' => 'mys9ys9ka',
        'prod_ssh_host' => 'mys9ys9ka.beget.tech',
    ];

    $iniPath = rtrim($docRoot, '/\\') . '/.osp/env.ini';
    if (!is_file($iniPath)) {
        return $defaults;
    }

    $ini = parse_ini_file($iniPath, true, INI_SCANNER_TYPED);
    if (!is_array($ini)) {
        return $defaults;
    }

    $section = $ini['prognos9ys'] ?? $ini['db_sync'] ?? $ini;
    if (!is_array($section)) {
        return $defaults;
    }

    return [
        'prod_host' => (string)($section['DB_SYNC_PROD_HOST'] ?? $defaults['prod_host']),
        'local_host' => (string)($section['DB_SYNC_LOCAL_HOST'] ?? $defaults['local_host']),
        'local_scheme' => (string)($section['DB_SYNC_LOCAL_SCHEME'] ?? $defaults['local_scheme']),
        'mysql_bin' => isset($section['MYSQL_BIN']) ? (string)$section['MYSQL_BIN'] : $defaults['mysql_bin'],
        'prod_ssh_user' => (string)($section['PROD_SSH_USER'] ?? $defaults['prod_ssh_user']),
        'prod_ssh_host' => (string)($section['PROD_SSH_HOST'] ?? $defaults['prod_ssh_host']),
    ];
}

function dbSyncDiscoverOspanelMysqlBin(): ?string
{
    if (DIRECTORY_SEPARATOR !== '\\') {
        return null;
    }

    $patterns = [
        'C:/OSPanel/modules/database/MySQL-*/bin',
        'D:/OSPanel/modules/database/MySQL-*/bin',
    ];

    $found = [];
    foreach ($patterns as $pattern) {
        $dirs = glob($pattern, GLOB_ONLYDIR) ?: [];
        foreach ($dirs as $dir) {
            $found[] = $dir;
        }
    }

    if ($found === []) {
        return null;
    }

    rsort($found);

    return $found[0];
}

function dbSyncHasCliFlag(array $argv, string $flag): bool
{
    return in_array($flag, $argv, true);
}

function dbSyncCliArg(array $argv, string $prefix): ?string
{
    foreach ($argv as $arg) {
        if (strpos($arg, $prefix) === 0) {
            return trim(substr($arg, strlen($prefix)));
        }
    }

    return null;
}

function dbSyncEscapeShellArg(string $value): string
{
    if (DIRECTORY_SEPARATOR === '\\') {
        return '"' . str_replace('"', '""', $value) . '"';
    }

    return escapeshellarg($value);
}

/**
 * @param array{host:string,port:int,database:string,login:string,password:string} $db
 */
function dbSyncBuildMysqlBaseCmd(array $db, ?string $mysqlBinDir = null): string
{
    $bin = dbSyncFindMysqlBinary('mysql', $mysqlBinDir);

    return $bin
        . ' --host=' . dbSyncEscapeShellArg($db['host'])
        . ' --port=' . (int)$db['port']
        . ' --user=' . dbSyncEscapeShellArg($db['login'])
        . ($db['password'] !== '' ? ' --password=' . dbSyncEscapeShellArg($db['password']) : '');
}

/**
 * @param array{host:string,port:int,database:string,login:string,password:string} $db
 */
function dbSyncBuildMysqldumpCmd(array $db, ?string $mysqlBinDir = null): string
{
    $bin = dbSyncFindMysqlBinary('mysqldump', $mysqlBinDir);

    return $bin
        . ' --host=' . dbSyncEscapeShellArg($db['host'])
        . ' --port=' . (int)$db['port']
        . ' --user=' . dbSyncEscapeShellArg($db['login'])
        . ($db['password'] !== '' ? ' --password=' . dbSyncEscapeShellArg($db['password']) : '')
        . ' --default-character-set=utf8mb4'
        . ' --single-transaction'
        . ' --quick'
        . ' --routines'
        . ' --triggers'
        . ' --hex-blob'
        . ' ' . dbSyncEscapeShellArg($db['database']);
}

function dbSyncFindMysqlBinary(string $name, ?string $mysqlBinDir = null): string
{
    $path = dbSyncResolveMysqlBinaryPath($name, $mysqlBinDir);
    if ($path !== null) {
        return dbSyncEscapeShellArg($path);
    }

    return $name . (DIRECTORY_SEPARATOR === '\\' ? '.exe' : '');
}

function dbSyncResolveMysqlBinaryPath(string $name, ?string $mysqlBinDir = null): ?string
{
    if ($mysqlBinDir !== null && $mysqlBinDir !== '') {
        $candidate = rtrim($mysqlBinDir, '/\\') . DIRECTORY_SEPARATOR . $name;
        if (DIRECTORY_SEPARATOR === '\\') {
            $candidate .= '.exe';
        }
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    $discovered = dbSyncDiscoverOspanelMysqlBin();
    if ($discovered !== null) {
        $candidate = rtrim($discovered, '/\\') . DIRECTORY_SEPARATOR . $name;
        if (DIRECTORY_SEPARATOR === '\\') {
            $candidate .= '.exe';
        }
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    return null;
}

function dbSyncRunCommand(string $command, bool $dryRun = false): int
{
    if ($dryRun) {
        echo "[DRY RUN] {$command}\n";

        return 0;
    }

    passthru($command, $exitCode);

    return (int)$exitCode;
}

function dbSyncEnsureDir(string $dir): void
{
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Не удалось создать каталог: ' . $dir);
    }
}

function dbSyncMaskPasswordInCommand(string $command): string
{
    return (string)preg_replace('/--password=(["\']?)([^"\'\s]+)\1/', '--password=***', $command);
}

function dbSyncFormatBytes(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1024 * 1024) {
        return round($bytes / 1024, 1) . ' KB';
    }
    if ($bytes < 1024 * 1024 * 1024) {
        return round($bytes / (1024 * 1024), 1) . ' MB';
    }

    return round($bytes / (1024 * 1024 * 1024), 2) . ' GB';
}

/**
 * @param array{host:string,port:int,database:string,login:string,password:string} $db
 */
function dbSyncRecreateDatabase(array $db, ?string $mysqlBinDir, bool $dryRun): void
{
    $sql = 'DROP DATABASE IF EXISTS `' . str_replace('`', '``', $db['database']) . '`;'
        . ' CREATE DATABASE `' . str_replace('`', '``', $db['database']) . '`'
        . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;";

    $command = dbSyncBuildMysqlBaseCmd($db, $mysqlBinDir)
        . ' --execute=' . dbSyncEscapeShellArg($sql);

    echo "Пересоздание БД {$db['database']}...\n";
    $exitCode = dbSyncRunCommand($command, $dryRun);
    if ($exitCode !== 0) {
        throw new RuntimeException('Не удалось пересоздать БД, код ' . $exitCode);
    }
}

/**
 * @param array{host:string,port:int,database:string,login:string,password:string} $db
 */
function dbSyncImportSqlFile(array $db, string $sqlFile, ?string $mysqlBinDir, bool $dryRun): void
{
    echo 'Импорт ' . basename($sqlFile) . "...\n";
    if ($dryRun) {
        echo '[DRY RUN] stream → mysql ' . $db['database'] . "\n";

        return;
    }

    $mysqlPath = dbSyncResolveMysqlBinaryPath('mysql', $mysqlBinDir);
    if ($mysqlPath === null) {
        throw new RuntimeException('mysql client not found');
    }

    $command = escapeshellarg($mysqlPath)
        . ' --host=' . escapeshellarg($db['host'])
        . ' --port=' . (int)$db['port']
        . ' --user=' . escapeshellarg($db['login']);
    if ($db['password'] !== '') {
        $command .= ' --password=' . escapeshellarg($db['password']);
    }
    $command .= ' ' . escapeshellarg($db['database']);

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open($command, $descriptors, $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Не удалось запустить mysql client');
    }

    $isGzip = preg_match('/\.gz$/i', $sqlFile) === 1;
    if ($isGzip) {
        $handle = gzopen($sqlFile, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Не удалось открыть gzip: ' . $sqlFile);
        }
        while (!gzeof($handle)) {
            $chunk = gzread($handle, 1024 * 1024);
            if ($chunk === false) {
                break;
            }
            $written = fwrite($pipes[0], $chunk);
            if ($written === false) {
                break;
            }
        }
        gzclose($handle);
    } else {
        $handle = fopen($sqlFile, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Не удалось открыть SQL: ' . $sqlFile);
        }
        while (!feof($handle)) {
            $chunk = fread($handle, 1024 * 1024);
            if ($chunk === false) {
                break;
            }
            fwrite($pipes[0], $chunk);
        }
        fclose($handle);
    }

    fclose($pipes[0]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    $exitCode = proc_close($process);
    if ($exitCode !== 0) {
        $message = trim($stderr) !== '' ? $stderr : 'код ' . $exitCode;
        throw new RuntimeException('Импорт завершился с ошибкой: ' . $message);
    }
}

/**
 * @param array{host:string,port:int,database:string,login:string,password:string} $db
 * @param array{prod_host:string,local_host:string,local_scheme:string,mysql_bin:?string} $env
 */
function dbSyncReplaceProdUrls(array $db, array $env, ?string $mysqlBinDir, bool $dryRun): void
{
    $prodHost = $env['prod_host'];
    $localHost = $env['local_host'];
    $localBase = rtrim($env['local_scheme'], ':/') . '://' . $localHost;

    $pairs = [
        'https://' . $prodHost => $localBase,
        'http://' . $prodHost => $localBase,
        'https://www.' . $prodHost => $localBase,
        'http://www.' . $prodHost => $localBase,
        $prodHost => $localHost,
        'www.' . $prodHost => $localHost,
    ];

    echo "Замена URL {$prodHost} → {$localBase}\n";

    foreach ($pairs as $from => $to) {
        if ($from === $to) {
            continue;
        }

        $sql = "UPDATE b_option SET VALUE = REPLACE(VALUE, '"
            . str_replace("'", "''", $from)
            . "', '"
            . str_replace("'", "''", $to)
            . "') WHERE VALUE LIKE '%"
            . str_replace("'", "''", $from)
            . "%';";

        $command = dbSyncBuildMysqlBaseCmd($db, $mysqlBinDir)
            . ' ' . dbSyncEscapeShellArg($db['database'])
            . ' --execute=' . dbSyncEscapeShellArg($sql);

        $exitCode = dbSyncRunCommand($command, $dryRun);
        if ($exitCode !== 0) {
            throw new RuntimeException('Ошибка замены URL: ' . $from);
        }
    }
}

/**
 * @param array{host:string,port:int,database:string,login:string,password:string} $db
 */
function dbSyncSanitizeLocal(array $db, ?string $mysqlBinDir, bool $dryRun): void
{
    echo "Санитизация локальной копии...\n";

    $queries = [
        "UPDATE b_agent SET ACTIVE='N' WHERE ACTIVE='Y';",
        "UPDATE b_option SET VALUE='N' WHERE NAME='event_log_file_dump';",
        "UPDATE b_option SET VALUE='' WHERE NAME IN ('smtp_server', 'smtp_login', 'smtp_password');",
    ];

    foreach ($queries as $sql) {
        $command = dbSyncBuildMysqlBaseCmd($db, $mysqlBinDir)
            . ' ' . dbSyncEscapeShellArg($db['database'])
            . ' --execute=' . dbSyncEscapeShellArg($sql);
        $exitCode = dbSyncRunCommand($command, $dryRun);
        if ($exitCode !== 0) {
            throw new RuntimeException('Ошибка санитизации: ' . $sql);
        }
    }
}

function dbSyncClearBitrixCache(string $docRoot, bool $dryRun): void
{
    $dirs = [
        $docRoot . '/bitrix/cache',
        $docRoot . '/bitrix/managed_cache',
        $docRoot . '/bitrix/stack_cache',
    ];

    echo "Очистка кэша Bitrix...\n";
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            continue;
        }
        if ($dryRun) {
            echo "[DRY RUN] clear {$dir}\n";
            continue;
        }
        dbSyncDeleteDirContents($dir);
    }
}

function dbSyncProdRemotePath(string $relativePath): string
{
    return '~/prognos9ys.ru/public_html/' . ltrim(str_replace('\\', '/', $relativePath), '/');
}

/**
 * @param array{prod_ssh_user:string,prod_ssh_host:string} $env
 */
function dbSyncBuildScpDownloadHint(array $env, string $remoteRelativePath, string $localRelativePath): string
{
    $remote = dbSyncProdRemotePath($remoteRelativePath);
    $userHost = $env['prod_ssh_user'] . '@' . $env['prod_ssh_host'];

    return 'scp ' . $userHost . ':' . $remote . ' ' . str_replace('/', '\\', $localRelativePath);
}

function dbSyncDeleteDirContents(string $dir): void
{
    $items = scandir($dir);
    if ($items === false) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            dbSyncDeleteDirContents($path);
            @rmdir($path);
        } else {
            @unlink($path);
        }
    }
}
