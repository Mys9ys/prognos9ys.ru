@ECHO OFF
setlocal
call "%~dp0ospanel_env.bat"
if "%PHP_EXE%"=="" (
    echo Error: php.exe not found. Check OSPanel modules.
    exit /b 1
)
"%PHP_EXE%" "%PROJECT_ROOT%\local\tools\analyze_achievement_scales.php" %*
