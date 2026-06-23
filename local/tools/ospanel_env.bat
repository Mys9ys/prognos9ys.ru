@ECHO OFF
set "TOOLS_ROOT=%~dp0"
set "PROJECT_ROOT="
set "OSPANEL_ROOT="
set "PHP_EXE="

pushd "%TOOLS_ROOT%..\.."
set "PROJECT_ROOT=%CD%"
popd

pushd "%PROJECT_ROOT%\..\.."
set "OSPANEL_ROOT=%CD%"
popd

if not exist "%OSPANEL_ROOT%\modules" (
    if exist "D:\OSPanel\modules" set "OSPANEL_ROOT=D:\OSPanel"
)
if not exist "%OSPANEL_ROOT%\modules" (
    if exist "C:\OSPanel\modules" set "OSPANEL_ROOT=C:\OSPanel"
)

if exist "%OSPANEL_ROOT%\modules\PHP-7.4\php.exe" set "PHP_EXE=%OSPANEL_ROOT%\modules\PHP-7.4\php.exe"
if "%PHP_EXE%"=="" if exist "%OSPANEL_ROOT%\modules\PHP-7.4\PHP\php.exe" set "PHP_EXE=%OSPANEL_ROOT%\modules\PHP-7.4\PHP\php.exe"
if "%PHP_EXE%"=="" if exist "%OSPANEL_ROOT%\modules\PHP-8.0\php.exe" set "PHP_EXE=%OSPANEL_ROOT%\modules\PHP-8.0\php.exe"
if "%PHP_EXE%"=="" if exist "%OSPANEL_ROOT%\modules\PHP-8.1\php.exe" set "PHP_EXE=%OSPANEL_ROOT%\modules\PHP-8.1\php.exe"
if "%PHP_EXE%"=="" if exist "%OSPANEL_ROOT%\modules\PHP-8.2\php.exe" set "PHP_EXE=%OSPANEL_ROOT%\modules\PHP-8.2\php.exe"
if "%PHP_EXE%"=="" if exist "%OSPANEL_ROOT%\modules\PHP-8.3\php.exe" set "PHP_EXE=%OSPANEL_ROOT%\modules\PHP-8.3\php.exe"

if "%PHP_EXE%"=="" (
    where php >nul 2>&1
    if not errorlevel 1 set "PHP_EXE=php"
)
