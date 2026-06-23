@ECHO OFF
setlocal

rem Скачать последний дамп БД с боя (читает PROD_SSH_* из .osp\env.ini).
rem   local\tools\download_prod_db.bat
rem   local\tools\download_prod_db.bat prognos9ys_20260622_120000.sql.gz

set "ROOT=%~dp0..\.."
set "PHP_EXE="

if exist "C:\OSPanel\modules\PHP-7.4\PHP\php.exe" set "PHP_EXE=C:\OSPanel\modules\PHP-7.4\PHP\php.exe"
if exist "C:\OSPanel\modules\PHP-8.0\PHP\php.exe" if "%PHP_EXE%"=="" set "PHP_EXE=C:\OSPanel\modules\PHP-8.0\PHP\php.exe"
if "%PHP_EXE%"=="" (
    where php >nul 2>&1
    if errorlevel 1 (
        echo Error: php.exe not found.
        exit /b 1
    )
    set "PHP_EXE=php"
)

for /f "usebackq delims=" %%i in (`"%PHP_EXE%" "%ROOT%\local\tools\download_prod_db.php" %*`) do set "SCP_CMD=%%i"
if "%SCP_CMD%"=="" (
    echo Failed to build scp command. Check .osp\env.ini PROD_SSH_HOST.
    exit /b 1
)

echo %SCP_CMD%
%SCP_CMD%
exit /b %ERRORLEVEL%
