@echo off
cd /d "%~dp0"
set "PHP_EXE=%LOCALAPPDATA%\php-8.4\php.exe"
if not exist "%PHP_EXE%" set "PHP_EXE=php"
echo SmartLift em http://127.0.0.1:8000
echo Feche esta janela para encerrar o servidor.
"%PHP_EXE%" artisan serve --host=127.0.0.1 --port=8000
