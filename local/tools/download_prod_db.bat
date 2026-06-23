@ECHO OFF
setlocal

rem Скачать дамп БД с боя (читает PROD_SSH_* из .osp\env.ini).
rem   local\tools\download_prod_db.bat
rem   local\tools\download_prod_db.bat prognos9ys_20260622_120000.sql.gz

call "%~dp0ospanel_env.bat"

if "%PHP_EXE%"=="" (
    echo Error: php.exe not found in OSPanel (%OSPANEL_ROOT%\modules\PHP-*).
    exit /b 1
)

for /f "usebackq delims=" %%i in (`"%PHP_EXE%" "%PROJECT_ROOT%\local\tools\download_prod_db.php" %*`) do set "SCP_CMD=%%i"
if "%SCP_CMD%"=="" (
    echo Failed to build scp command. Check .osp\env.ini PROD_SSH_HOST.
    exit /b 1
)

echo %SCP_CMD%
%SCP_CMD%
exit /b %ERRORLEVEL%
