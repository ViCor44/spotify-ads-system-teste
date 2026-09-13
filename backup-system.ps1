param(
    [string]$Destination = (Join-Path $PSScriptRoot 'backups')
)

$ErrorActionPreference = 'Stop'
$root = $PSScriptRoot
$timestamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$staging = Join-Path ([System.IO.Path]::GetTempPath()) "spot-master-backup-$timestamp"
$mysqlDefaults = Join-Path ([System.IO.Path]::GetTempPath()) "spot-master-mysql-$timestamp.cnf"

function Find-Executable {
    param([string]$Name, [string[]]$Fallbacks)

    $command = Get-Command $Name -ErrorAction SilentlyContinue
    if ($command) { return $command.Source }
    foreach ($fallback in $Fallbacks) {
        if (Test-Path $fallback) { return $fallback }
    }
    return $null
}

$php = Find-Executable 'php' @('C:\xampp\php\php.exe', 'C:\Program Files\PHP\php.exe')
$mysqldump = Find-Executable 'mysqldump' @('C:\xampp\mysql\bin\mysqldump.exe')
if (-not $php -or -not $mysqldump) {
    throw 'PHP ou mysqldump não encontrado. Confirme a instalação do XAMPP.'
}
if (-not (Test-Path (Join-Path $root 'config\database.php'))) {
    throw 'O ficheiro config\database.php não existe.'
}

try {
    New-Item -ItemType Directory -Path $staging -Force | Out-Null
    New-Item -ItemType Directory -Path $Destination -Force | Out-Null

    $configJson = & $php -r "require '$($root.Replace('\', '/'))/config/database.php'; echo json_encode(['host'=>DB_HOST,'user'=>DB_USER,'pass'=>DB_PASS,'name'=>DB_NAME]);"
    if ($LASTEXITCODE -ne 0) { throw 'Não foi possível ler a configuração da base de dados.' }
    $database = $configJson | ConvertFrom-Json

    $escapedPassword = ([string]$database.pass).Replace('\', '\\').Replace('"', '\"')
    @"
[client]
host="$($database.host)"
user="$($database.user)"
password="$escapedPassword"
"@ | Set-Content -Path $mysqlDefaults -Encoding ASCII

    $sqlFile = Join-Path $staging 'database.sql'
    & $mysqldump "--defaults-extra-file=$mysqlDefaults" --single-transaction --default-character-set=utf8mb4 --result-file="$sqlFile" $database.name
    if ($LASTEXITCODE -ne 0) { throw 'A exportação da base de dados falhou.' }

    foreach ($relativePath in @('config\database.php', 'public\uploads', 'storage')) {
        $source = Join-Path $root $relativePath
        if (Test-Path $source) {
            $target = Join-Path $staging $relativePath
            New-Item -ItemType Directory -Path (Split-Path $target) -Force | Out-Null
            Copy-Item $source $target -Recurse -Force
        }
    }
    Get-ChildItem (Join-Path $root 'config') -Filter 'google-tts-sa*.json' -ErrorAction SilentlyContinue | ForEach-Object {
        $targetDir = Join-Path $staging 'config'
        New-Item -ItemType Directory -Path $targetDir -Force | Out-Null
        Copy-Item $_.FullName $targetDir -Force
    }

    $archive = Join-Path $Destination "spot-master-backup-$timestamp.zip"
    Compress-Archive -Path (Join-Path $staging '*') -DestinationPath $archive -CompressionLevel Optimal
    Write-Host "Backup criado: $archive" -ForegroundColor Green
    Write-Host 'Este ficheiro contém credenciais. Guarde-o num local externo e protegido.' -ForegroundColor Yellow
} finally {
    Remove-Item $staging -Recurse -Force -ErrorAction SilentlyContinue
    Remove-Item $mysqlDefaults -Force -ErrorAction SilentlyContinue
}