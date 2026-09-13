param(
    [switch]$Uninstall
)

$ErrorActionPreference = 'Stop'
$taskName = 'Spot Master - Verificar agendamentos'

if ($Uninstall) {
    Unregister-ScheduledTask -TaskName $taskName -Confirm:$false -ErrorAction SilentlyContinue
    Write-Host 'Tarefa do robô removida.' -ForegroundColor Green
    exit 0
}

$batchFile = Join-Path $PSScriptRoot 'run_checker.bat'
if (-not (Test-Path $batchFile)) {
    throw "Lançador não encontrado: $batchFile"
}

$action = New-ScheduledTaskAction `
    -Execute (Join-Path $env:SystemRoot 'System32\cmd.exe') `
    -Argument ('/d /c ""{0}""' -f $batchFile) `
    -WorkingDirectory $PSScriptRoot
$trigger = New-ScheduledTaskTrigger `
    -Once `
    -At (Get-Date).AddMinutes(1) `
    -RepetitionInterval (New-TimeSpan -Minutes 1) `
    -RepetitionDuration (New-TimeSpan -Days 3650)
$settings = New-ScheduledTaskSettingsSet `
    -StartWhenAvailable `
    -AllowStartIfOnBatteries `
    -DontStopIfGoingOnBatteries `
    -ExecutionTimeLimit (New-TimeSpan -Minutes 2)

Register-ScheduledTask `
    -TaskName $taskName `
    -Action $action `
    -Trigger $trigger `
    -Settings $settings `
    -Description 'Executa o verificador de anúncios do Spot Master a cada minuto.' `
    -Force | Out-Null

Write-Host "Tarefa criada: $taskName" -ForegroundColor Green