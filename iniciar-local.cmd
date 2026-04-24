@echo off
setlocal

set "PROJECT_DIR=%~dp0"
if "%PROJECT_DIR:~-1%"=="\" set "PROJECT_DIR=%PROJECT_DIR:~0,-1%"
for %%I in ("%PROJECT_DIR%") do set "PROJECT_NAME=%%~nxI"
set "LARAGON_EXE=C:\laragon\laragon.exe"
set "BROWSERSYNC_CMD=%APPDATA%\npm\browser-sync.cmd"
set "PROXY_TARGET=localhost/%PROJECT_NAME%"
set "BROWSERSYNC_PORT=3001"
set "FILES_WATCH=assets/**/*.css,assets/**/*.js,**/*.php"
set "APACHE_VERSION="
set "APACHE_ROOT="
set "APACHE_EXE="

if not exist "%LARAGON_EXE%" (
  echo Laragon não encontrado em "%LARAGON_EXE%".
  pause
  exit /b 1
)

if not exist "%BROWSERSYNC_CMD%" (
  echo BrowserSync não encontrado em "%BROWSERSYNC_CMD%".
  echo Rode: npm install -g browser-sync
  pause
  exit /b 1
)

start "" "%LARAGON_EXE%"
timeout /t 4 /nobreak >nul

for /f "usebackq delims=" %%A in (`powershell -NoProfile -ExecutionPolicy Bypass -Command "$ini='C:\laragon\usr\laragon.ini'; $section=''; $ver=''; foreach($line in Get-Content $ini){ if($line -match '^\[(.+)\]$'){ $section=$matches[1]; continue }; if($section -eq 'apache' -and $line -match '^Version=(.+)$'){ $ver=$matches[1]; break } }; if(-not $ver){ $ver='httpd-2.4.54-win64-VS16' }; Write-Output $ver"`) do set "APACHE_VERSION=%%A"
set "APACHE_ROOT=C:\laragon\bin\apache\%APACHE_VERSION%"
set "APACHE_EXE=%APACHE_ROOT%\bin\httpd.exe"

if not exist "%APACHE_EXE%" (
  echo Apache não encontrado em "%APACHE_EXE%".
  pause
  exit /b 1
)

powershell -NoProfile -ExecutionPolicy Bypass -Command "$p='%APACHE_EXE%'; $r='%APACHE_ROOT%'; if(-not (Get-Process httpd -ErrorAction SilentlyContinue)){ Start-Process -FilePath $p -ArgumentList @('-d',$r,'-C','ServerName localhost') -WindowStyle Hidden | Out-Null }"
timeout /t 2 /nobreak >nul

cd /d "%PROJECT_DIR%"
if errorlevel 1 (
  echo Projeto não encontrado em "%PROJECT_DIR%".
  pause
  exit /b 1
)

echo.
echo Iniciando BrowserSync...
echo Abra: http://localhost:%BROWSERSYNC_PORT%/%PROJECT_NAME%
echo.
call "%BROWSERSYNC_CMD%" start --proxy "%PROXY_TARGET%" --files "%FILES_WATCH%" --port %BROWSERSYNC_PORT% --no-ui --no-open
