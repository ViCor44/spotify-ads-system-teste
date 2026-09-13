@echo off
setlocal
TITLE Instalar backup diario do Spot Master

echo A configurar o backup diario para C:\BackupMySQL...
echo.

powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0register-backup-task.ps1"
if errorlevel 1 (
    echo.
    echo ERRO: Nao foi possivel criar a tarefa automatica.
    pause
    exit /b 1
)

echo.
echo Instalacao concluida. Pode fechar esta janela.
pause