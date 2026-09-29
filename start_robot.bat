@echo off
setlocal
REM Define o título da janela para ser fácil de identificar
TITLE Spot Master Robot

set "PHP_EXE=php"
where php >nul 2>&1
if errorlevel 1 (
	if exist "C:\xampp\php\php.exe" (
		set "PHP_EXE=C:\xampp\php\php.exe"
	) else (
		echo ERRO: PHP nao encontrado. Instale o XAMPP ou adicione o PHP ao PATH.
		pause
		exit /b 1
	)
)

echo Robot de Agendamento do Spot Master iniciado. Nao feche esta janela.
echo.

:loop
echo [%TIME%] Verificando agendamentos...
REM Executa o nosso script PHP
"%PHP_EXE%" -f "%~dp0scripts\check_schedules.php"

echo [%TIME%] Verificacao concluida. A sincronizar com o inicio do proximo minuto...
echo.

REM Recalcula sempre a espera para executar no segundo 00. Uma espera fixa de
REM 60 segundos acumulava o tempo gasto pelo PHP e podia atrasar os anuncios.
for /f %%S in ('powershell.exe -NoProfile -Command "$n=Get-Date; [Math]::Max(1, 60-$n.Second)"') do set "WAIT_SECONDS=%%S"
timeout /t %WAIT_SECONDS% /nobreak >nul

REM Volta ao início do ciclo
goto loop
