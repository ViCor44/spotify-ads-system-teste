@echo off
REM Define o título da janela para ser fácil de identificar
TITLE Spot Master Robot

echo Robot de Agendamento do Spot Master iniciado. Nao feche esta janela.
echo.

:loop
echo [%TIME%] Verificando agendamentos...
REM Executa o nosso script PHP
"C:\xampp\php\php.exe" -f "C:\xampp\htdocs\spotify-ads-system-teste\scripts\check_schedules.php"

echo [%TIME%] Verificacao concluida. A aguardar 60 segundos...
echo.

REM Espera 60 segundos antes de recomecar o ciclo
timeout /t 60 /nobreak

REM Volta ao início do ciclo
goto loop