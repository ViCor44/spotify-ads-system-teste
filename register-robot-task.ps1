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

$phpWin = 'C:\xampp\php\php-win.exe'
$checkerScript = Join-Path $PSScriptRoot 'scripts\check_schedules.php'
if (-not (Test-Path $phpWin)) {
    throw "PHP sem janela não encontrado: $phpWin"
}
if (-not (Test-Path $checkerScript)) {
    throw "Verificador não encontrado: $checkerScript"
}

$action = New-ScheduledTaskAction `
    -Execute $phpWin `
    -Argument ('-f "{0}"' -f $checkerScript) `
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
    -Description 'Executa silenciosamente o verificador de anúncios do Spot Master a cada minuto.' `
    -Force | Out-Null

Write-Host "Tarefa criada: $taskName" -ForegroundColor Green