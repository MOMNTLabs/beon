@echo off
setlocal

echo Encerrando processos do BrowserSync...
powershell -NoProfile -ExecutionPolicy Bypass -Command "Get-CimInstance Win32_Process -Filter \"Name='node.exe'\" | Where-Object { $_.CommandLine -match 'browser-sync' } | ForEach-Object { Stop-Process -Id $_.ProcessId -Force }"

echo Encerrando Apache local...
powershell -NoProfile -ExecutionPolicy Bypass -Command "Get-Process httpd -ErrorAction SilentlyContinue | Stop-Process -Force"

echo.
echo BrowserSync e Apache encerrados.
echo Se estiver usando MySQL, pode parar no Laragon (Stop All).
pause
