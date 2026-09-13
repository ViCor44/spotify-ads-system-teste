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

echo [%TIME%] Verificacao concluida. A aguardar 60 segundos...
echo.

REM Espera 60 segundos antes de recomecar o ciclo
timeout /t 60 /nobreak

REM Volta ao início do ciclo
goto loop