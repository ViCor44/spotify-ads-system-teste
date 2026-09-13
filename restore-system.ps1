param(
    [Parameter(Mandatory = $true)]
    [string]$BackupFile
)

$ErrorActionPreference = 'Stop'
$root = $PSScriptRoot
$staging = Join-Path ([System.IO.Path]::GetTempPath()) ('spot-master-restore-' + [guid]::NewGuid().ToString('N'))
$mysqlDefaults = Join-Path ([System.IO.Path]::GetTempPath()) ('spot-master-mysql-' + [guid]::NewGuid().ToString('N') + '.cnf')

function Find-Executable {
    param([string]$Name, [string[]]$Fallbacks)

    $command = Get-Command $Name -ErrorAction SilentlyContinue
    if ($command) { return $command.Source }
    foreach ($fallback in $Fallbacks) {
        if (Test-Path $fallback) { return $fallback }
    }
    return $null
}

if (-not (Test-Path $BackupFile)) { throw "Backup não encontrado: $BackupFile" }
$php = Find-Executable 'php' @('C:\xampp\php\php.exe', 'C:\Program Files\PHP\php.exe')
$mysql = Find-Executable 'mysql' @('C:\xampp\mysql\bin\mysql.exe')
if (-not $php -or -not $mysql) { throw 'PHP ou mysql não encontrado. Confirme a instalação do XAMPP.' }

try {
    Expand-Archive -Path $BackupFile -DestinationPath $staging -Force
    if (-not (Test-Path (Join-Path $staging 'database.sql')) -or -not (Test-Path (Join-Path $staging 'config\database.php'))) {
        throw 'O ficheiro indicado não é um backup válido do Spot Master.'
    }

    Copy-Item (Join-Path $staging 'config\database.php') (Join-Path $root 'config\database.php') -Force
    foreach ($relativePath in @('public\uploads', 'storage')) {
        $source = Join-Path $staging $relativePath
        if (-not (Test-Path $source)) { continue }

        $target = Join-Path $root $relativePath
        New-Item -ItemType Directory -Path $target -Force | Out-Null
        Get-ChildItem $source -Force | Copy-Item -Destination $target -Recurse -Force
    }
    Get-ChildItem (Join-Path $staging 'config') -Filter 'google-tts-sa*.json' -ErrorAction SilentlyContinue | ForEach-Object {
        Copy-Item $_.FullName (Join-Path $root 'config') -Force
    }

    & (Join-Path $root 'setup-new-pc.ps1')
    if ($LASTEXITCODE -ne 0) { throw 'A preparação do sistema falhou antes do restauro.' }

    $configJson = & $php -r "require '$($root.Replace('\', '/'))/config/database.php'; echo json_encode(['host'=>DB_HOST,'user'=>DB_USER,'pass'=>DB_PASS,'name'=>DB_NAME]);"
    $database = $configJson | ConvertFrom-Json
    $escapedPassword = ([string]$database.pass).Replace('\', '\\').Replace('"', '\"')
    @"
[client]
host="$($database.host)"
user="$($database.user)"
password="$escapedPassword"
"@ | Set-Content -Path $mysqlDefaults -Encoding ASCII

    $sqlFile = (Join-Path $staging 'database.sql').Replace('\', '/')
    & $mysql "--defaults-extra-file=$mysqlDefaults" --default-character-set=utf8mb4 $database.name "--execute=source $sqlFile"
    if ($LASTEXITCODE -ne 0) { throw 'A importação da base de dados falhou.' }

    & (Join-Path $root 'setup-new-pc.ps1') -CheckOnly
    if ($LASTEXITCODE -ne 0) { throw 'O restauro terminou, mas o diagnóstico encontrou erros.' }
    Write-Host 'Restauro concluído com sucesso.' -ForegroundColor Green
} finally {
    Remove-Item $staging -Recurse -Force -ErrorAction SilentlyContinue
    Remove-Item $mysqlDefaults -Force -ErrorAction SilentlyContinue
}