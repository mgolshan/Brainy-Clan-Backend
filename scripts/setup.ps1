# Brainy Clan backend - one-time setup for Windows (Docker Desktop).
#
#   powershell -ExecutionPolicy Bypass -File scripts\setup.ps1
#
# Builds the PHP image, starts MySQL/Redis/Mailpit, runs docker/scripts/bootstrap.sh in
# the app container (composer, .env, package installers, migrations, admin user), starts
# the full stack and finally runs the interactive Laravel Boost installer.
#
# Works in Windows PowerShell 5.1 and PowerShell 7. No PHP or Composer is needed on
# Windows: everything PHP-related runs inside Docker.

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version 2.0

$MysqlWaitSeconds = 120

function Write-Step([string]$Message) {
    Write-Host ''
    Write-Host "==> $Message" -ForegroundColor Cyan
}

function Stop-WithError([string]$Message) {
    Write-Host ''
    Write-Host "ERROR: $Message" -ForegroundColor Red
    exit 1
}

# Native commands (docker) are called directly, never through a function or scriptblock,
# so interactive steps keep a real console/TTY. Their exit code is checked right after.
function Assert-LastExit([string]$FailMessage) {
    if ($LASTEXITCODE -ne 0) {
        Stop-WithError "$FailMessage (exit code $LASTEXITCODE)"
    }
}

# Runs a native command and returns its trimmed stdout ('' on failure). Never throws.
function Get-NativeOutput([scriptblock]$Command) {
    $previous = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    try {
        $output = & $Command 2>$null
        if ($LASTEXITCODE -ne 0) {
            return ''
        }
        if ($null -eq $output) {
            return ''
        }
        return (($output | Out-String).Trim())
    }
    catch {
        return ''
    }
    finally {
        $ErrorActionPreference = $previous
    }
}

$RootDir = Split-Path -Parent $PSScriptRoot
Push-Location $RootDir

try {
    Write-Step 'Checking Docker'

    if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
        Stop-WithError 'docker was not found. Install Docker Desktop (WSL2 backend) first.'
    }

    $previous = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    docker info *> $null
    $dockerInfoExit = $LASTEXITCODE
    docker compose version *> $null
    $composeExit = $LASTEXITCODE
    $ErrorActionPreference = $previous

    if ($dockerInfoExit -ne 0) {
        Stop-WithError 'Docker is not running. Start Docker Desktop, wait until it says "Engine running", then run this script again.'
    }
    if ($composeExit -ne 0) {
        Stop-WithError "'docker compose' (Compose v2) is not available. Update Docker Desktop."
    }

    Write-Step 'Building the PHP image'
    docker compose build
    Assert-LastExit 'docker compose build failed'

    Write-Step 'Starting MySQL, Redis and Mailpit'
    docker compose up -d mysql redis mailpit
    Assert-LastExit 'Could not start mysql/redis/mailpit'

    Write-Step "Waiting for MySQL to become healthy (up to $MysqlWaitSeconds s)"
    $mysqlContainer = Get-NativeOutput { docker compose ps -q mysql }
    if ($mysqlContainer -eq '') {
        Stop-WithError 'The mysql container is not running.'
    }

    $waited = 0
    while ($true) {
        $status = Get-NativeOutput { docker inspect -f '{{if .State.Health}}{{.State.Health.Status}}{{else}}none{{end}}' $mysqlContainer }
        if ($status -eq 'healthy') {
            Write-Host '    MySQL is healthy.'
            break
        }
        if ($waited -ge $MysqlWaitSeconds) {
            $ErrorActionPreference = 'Continue'
            docker compose logs --tail 50 mysql
            $ErrorActionPreference = 'Stop'
            Stop-WithError "MySQL did not become healthy within $MysqlWaitSeconds s (status: $status)."
        }
        Write-Host "    status: $status, waiting..."
        Start-Sleep -Seconds 3
        $waited += 3
    }

    Write-Step 'Bootstrapping Laravel inside the app container'
    docker compose run --rm app bash docker/scripts/bootstrap.sh
    Assert-LastExit 'docker/scripts/bootstrap.sh failed (scroll up for the error)'

    Write-Step 'Starting the full stack (app, nginx, horizon, scheduler)'
    docker compose --profile workers up -d
    Assert-LastExit 'Could not start the stack'

    Write-Step 'Laravel Boost installer (interactive)'
    Write-Host '    Answer the prompts. When asked which agents/editors to configure, select'
    Write-Host '    "Claude Code" (and PhpStorm / Junie if it is offered and you use it).'
    Write-Host '    The MCP config it writes is replaced with a Docker-based one right afterwards.'
    Write-Host ''
    docker compose exec app php artisan boost:install
    Assert-LastExit 'php artisan boost:install failed'

    Write-Step 'Pointing the Boost MCP server (.mcp.json) at the Docker container'
    docker compose exec -T app php docker/scripts/write-mcp-json.php
    Assert-LastExit 'Could not write .mcp.json'

    Write-Step 'All done'
    Write-Host ''
    Write-Host '    API             http://localhost:8080          (health check: /up)'
    Write-Host '    Admin panel     http://localhost:8080/admin    admin@brainyclan.test / password'
    Write-Host '    Horizon         http://localhost:8080/horizon'
    Write-Host '    Mailpit         http://localhost:8025'
    Write-Host '    MySQL (host)    127.0.0.1:3307                 brainy / secret, database brainy_clan'
    Write-Host '    Redis (host)    127.0.0.1:6380'
    Write-Host ''
    Write-Host '    Daily use:  docker compose --profile workers up -d   /   docker compose --profile workers down'
    Write-Host ''
}
finally {
    Pop-Location
}
