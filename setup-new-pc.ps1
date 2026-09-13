param(
    [switch]$CheckOnly,
    [switch]$RegisterTask
)

$ErrorActionPreference = 'Stop'
$root = $PSScriptRoot

function Find-Executable {
    param([string]$Name, [string[]]$Fallbacks)

    $command = Get-Command $Name -ErrorAction SilentlyContinue
    if ($command) {
        return $command.Source
    }

    foreach ($fallback in $Fallbacks) {
        if (Test-Path $fallback) {
            return $fallback
        }
    }

    return $null
}

$php = Find-Executable 'php' @('C:\xampp\php\php.exe', 'C:\Program Files\PHP\php.exe')
if (-not $php) {
    throw 'PHP não encontrado. Instale o XAMPP com PHP 8.0 ou superior.'
}

$config = Join-Path $root 'config\database.php'
$configExample = Join-Path $root 'config\database.example.php'
if (-not (Test-Path $config)) {
    Copy-Item $configExample $config
    Write-Host "Configuração criada em $config" -ForegroundColor Yellow
    Write-Host 'Preencha as credenciais nesse ficheiro e execute este script novamente.' -ForegroundColor Yellow
    exit 2
}

if (-not $CheckOnly) {
    $composer = Find-Executable 'composer' @('C:\ProgramData\ComposerSetup\bin\composer.bat')
    if ($composer) {
        & $composer install --no-interaction --prefer-dist --optimize-autoloader
        if ($LASTEXITCODE -ne 0) {
            throw 'O Composer não conseguiu instalar as dependências.'
        }
    } elseif (-not (Test-Path (Join-Path $root 'vendor\autoload.php'))) {
        throw 'Composer não encontrado e a pasta vendor não existe. Instale o Composer.'
    } else {
        Write-Host 'Composer não encontrado; a pasta vendor existente será utilizada.' -ForegroundColor Yellow
    }
}

$installArgs = @((Join-Path $root 'scripts\install.php'))
if ($CheckOnly) {
    $installArgs += '--check'
}

& $php @installArgs
$installExitCode = $LASTEXITCODE
if ($installExitCode -eq 0 -and $RegisterTask -and -not $CheckOnly) {
    & (Join-Path $root 'register-robot-task.ps1')
    if ($LASTEXITCODE -ne 0) {
        exit $LASTEXITCODE
    }
}
exit $installExitCode