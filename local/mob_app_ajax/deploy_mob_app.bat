@ECHO OFF
setlocal

rem Корень репозитория prognos9ys (на два уровня выше local\mob_app_ajax)
set "ROOT=%~dp0..\.."
set "FRONTEND=%ROOT%\frontend"
set "MOB_APP=%ROOT%\mob_app"
set "MOB_APP_AJAX=%ROOT%\local\mob_app_ajax\ajax"
set "NODE_PATH=C:\Program Files\nodejs"

set PATH=%NODE_PATH%;%PATH%

if not exist "%FRONTEND%\package.json" (
    echo Error: frontend not found at %FRONTEND%
    exit /b 1
)

cd /d "%FRONTEND%"
call npm run build
if errorlevel 1 exit /b 1

if exist "%MOB_APP%\js" rd /s /q "%MOB_APP%\js"
if exist "%MOB_APP%\css" rd /s /q "%MOB_APP%\css"

robocopy "%FRONTEND%\dist" "%MOB_APP%" /E /XF .htaccess /XD ajax /NFL /NDL /NJH /NJS /nc /ns /np
copy /Y "%FRONTEND%\src\assets\img\no_logo.jpg" "%MOB_APP%\img\no_logo.jpg" >nul
if not exist "%MOB_APP%\img\estate" mkdir "%MOB_APP%\img\estate"
copy /Y "%FRONTEND%\src\assets\estate\pangaea_world.png" "%MOB_APP%\img\estate\pangaea_world.png" >nul
robocopy "%MOB_APP_AJAX%" "%MOB_APP%\ajax" /E /NFL /NDL /NJH /NJS /nc /ns /np
if exist "%FRONTEND%\dist\ajax" (
    robocopy "%FRONTEND%\dist\ajax" "%MOB_APP%\ajax" /E /NFL /NDL /NJH /NJS /nc /ns /np
)

echo Deploy complete: %MOB_APP%
exit /b 0
