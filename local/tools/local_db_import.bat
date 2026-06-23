@ECHO OFF
setlocal

rem Импорт дампа БД с боя в локальную OSPanel-копию.
rem   local\tools\local_db_import.bat .osp\backup\db\prognos9ys_YYYYMMDD.sql.gz --confirm --sanitize

set "ROOT=%~dp0..\.."
set "PHP_EXE="

if exist "C:\OSPanel\modules\PHP-7.4\PHP\php.exe" set "PHP_EXE=C:\OSPanel\modules\PHP-7.4\PHP\php.exe"
if exist "C:\OSPanel\modules\PHP-8.0\PHP\php.exe" if "%PHP_EXE%"=="" set "PHP_EXE=C:\OSPanel\modules\PHP-8.0\PHP\php.exe"
if exist "C:\OSPanel\modules\PHP-8.1\PHP\php.exe" if "%PHP_EXE%"=="" set "PHP_EXE=C:\OSPanel\modules\PHP-8.1\PHP\php.exe"

if "%PHP_EXE%"=="" (
    where php >nul 2>&1
    if errorlevel 1 (
        echo Error: php.exe not found. Install OSPanel PHP or add php to PATH.
        exit /b 1
    )
    set "PHP_EXE=php"
)

"%PHP_EXE%" "%ROOT%\local\tools\local_db_import.php" %*
exit /b %ERRORLEVEL%
