@echo off
setlocal
TITLE Instalar robot do Spot Master

echo A configurar o robot do Spot Master...
echo.

powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0register-robot-task.ps1"
if errorlevel 1 (
    echo.
    echo ERRO: Nao foi possivel criar a tarefa do robot.
    pause
    exit /b 1
)

call "%~dp0run_checker.bat"
if errorlevel 1 (
    echo.
    echo ERRO: A tarefa foi criada, mas o teste do robot falhou.
    pause
    exit /b 1
)

echo.
echo Robot configurado e testado com sucesso.
echo Atualize o Dashboard no navegador.
pause