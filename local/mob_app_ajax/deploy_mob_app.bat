@ECHO OFF
setlocal

set PROG_VUE=D:\OSPanel\home\prog.vue
set PROGNOS9YS=D:\OSPanel\home\prognos9ys
set NODE_PATH=C:\Program Files\nodejs

set PATH=%NODE_PATH%;%PATH%

cd /d "%PROG_VUE%"
call npm run build
if errorlevel 1 exit /b 1

robocopy "%PROG_VUE%\dist" "%PROGNOS9YS%\mob_app" /E /XF .htaccess /XD ajax /NFL /NDL /NJH /NJS /nc /ns /np
robocopy "%PROGNOS9YS%\local\mob_app_ajax\ajax" "%PROGNOS9YS%\mob_app\ajax" /E /NFL /NDL /NJH /NJS /nc /ns /np
robocopy "%PROG_VUE%\dist\ajax" "%PROGNOS9YS%\mob_app\ajax" /E /NFL /NDL /NJH /NJS /nc /ns /np

echo Deploy complete: %PROGNOS9YS%\mob_app
exit /b 0
