@echo off
setlocal
TITLE Criar backup do Spot Master

echo A criar backup em C:\BackupMySQL...
echo Este processo pode demorar alguns minutos.
echo.

powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0backup-system.ps1" -Destination "C:\BackupMySQL" -RetentionCount 5
if errorlevel 1 (
    echo.
    echo ERRO: O backup nao foi concluido.
    pause
    exit /b 1
)

echo.
echo Backup concluido com sucesso.
pause