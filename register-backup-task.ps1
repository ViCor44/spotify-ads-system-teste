param(
    [string]$Destination = 'C:\BackupMySQL',
    [string]$At = '23:00',
    [ValidateRange(1, 365)]
    [int]$RetentionCount = 5,
    [switch]$Uninstall
)

$ErrorActionPreference = 'Stop'
$taskName = 'Spot Master - Backup diario'
$existingBackupTasks = Get-ScheduledTask -ErrorAction SilentlyContinue |
    Where-Object TaskName -Like 'Spot Master - Backup*'

if ($Uninstall) {
    $existingBackupTasks | Unregister-ScheduledTask -Confirm:$false -ErrorAction SilentlyContinue
    Write-Host 'Tarefa de backup removida.' -ForegroundColor Green
    exit 0
}

$existingBackupTasks |
    Where-Object TaskName -NE $taskName |
    Unregister-ScheduledTask -Confirm:$false -ErrorAction SilentlyContinue

$backupScript = Join-Path $PSScriptRoot 'backup-system.ps1'
if (-not (Test-Path $backupScript)) {
    throw "Script de backup não encontrado: $backupScript"
}

try {
    $backupTime = [datetime]::ParseExact($At, 'HH:mm', [Globalization.CultureInfo]::InvariantCulture)
} catch {
    throw 'A hora deve estar no formato HH:mm, por exemplo 23:00.'
}

New-Item -ItemType Directory -Path $Destination -Force | Out-Null

$arguments = '-NoProfile -NonInteractive -ExecutionPolicy Bypass -File "{0}" -Destination "{1}" -RetentionCount {2}' -f `
    $backupScript, $Destination, $RetentionCount
$action = New-ScheduledTaskAction `
    -Execute (Join-Path $env:SystemRoot 'System32\WindowsPowerShell\v1.0\powershell.exe') `
    -Argument $arguments `
    -WorkingDirectory $PSScriptRoot
$trigger = New-ScheduledTaskTrigger -Daily -At $backupTime
$settings = New-ScheduledTaskSettingsSet `
    -StartWhenAvailable `
    -WakeToRun `
    -AllowStartIfOnBatteries `
    -DontStopIfGoingOnBatteries `
    -MultipleInstances IgnoreNew `
    -ExecutionTimeLimit (New-TimeSpan -Hours 1)

Register-ScheduledTask `
    -TaskName $taskName `
    -Action $action `
    -Trigger $trigger `
    -Settings $settings `
    -Description "Backup diario do Spot Master para $Destination, mantendo os ultimos $RetentionCount ficheiros." `
    -Force | Out-Null

Write-Host "Tarefa criada: $taskName" -ForegroundColor Green
Write-Host "Hora: $At | Destino: $Destination | Retencao: $RetentionCount backups" -ForegroundColor Green