@ECHO OFF
setlocal EnableExtensions

set "PHP_EXE="
set "ROOT="

if exist "D:\OSPanel\modules\PHP-7.4\php.exe" set "PHP_EXE=D:\OSPanel\modules\PHP-7.4\php.exe"
if "%PHP_EXE%"=="" if exist "C:\OSPanel\modules\PHP-7.4\php.exe" set "PHP_EXE=C:\OSPanel\modules\PHP-7.4\php.exe"
if "%PHP_EXE%"=="" if exist "D:\OSPanel\modules\PHP-8.0\php.exe" set "PHP_EXE=D:\OSPanel\modules\PHP-8.0\php.exe"
if "%PHP_EXE%"=="" if exist "C:\OSPanel\modules\PHP-8.0\php.exe" set "PHP_EXE=C:\OSPanel\modules\PHP-8.0\php.exe"

if "%PHP_EXE%"=="" (
    where php >nul 2>&1
    if not errorlevel 1 set "PHP_EXE=php"
)

if "%PHP_EXE%"=="" (
    echo Error: php.exe not found. Expected D:\OSPanel\modules\PHP-7.4\php.exe
    exit /b 1
)

pushd "%~dp0..\.."
set "ROOT=%CD%"
popd

"%PHP_EXE%" "%ROOT%\local\tools\local_db_import.php" %*
exit /b %ERRORLEVEL%
