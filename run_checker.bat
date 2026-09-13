@echo off
setlocal

set "PHP_EXE=php"
where php >nul 2>&1
if errorlevel 1 (
	if exist "C:\xampp\php\php.exe" (
		set "PHP_EXE=C:\xampp\php\php.exe"
	) else (
		echo ERRO: PHP nao encontrado. Instale o XAMPP ou adicione o PHP ao PATH.
		exit /b 1
	)
)

"%PHP_EXE%" -f "%~dp0scripts\check_schedules.php"
exit /b %ERRORLEVEL%