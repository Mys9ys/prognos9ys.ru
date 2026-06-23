@ECHO OFF
setlocal
call "%~dp0ospanel_env.bat"
if "%PHP_EXE%"=="" (
    echo Error: php.exe not found.
    exit /b 1
)
"%PHP_EXE%" "%PROJECT_ROOT%\local\tools\reset_achievement_rewards.php" %*
