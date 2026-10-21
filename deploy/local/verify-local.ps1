[CmdletBinding()]
param (
    [string] $IdentityFile = (Join-Path $PSScriptRoot '.local/id_ed25519')
)

$ErrorActionPreference = 'Stop'
$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot '../..')).Path
$composeFile = Join-Path $PSScriptRoot 'compose.yaml'
$appRoot = '/home/oakfield/oakfield-sms'
$connection = 'oakfield@127.0.0.1'

if (-not (Test-Path -LiteralPath $IdentityFile)) {
    throw "Create the local SSH key first: ssh-keygen -t ed25519 -f '$IdentityFile'"
}

& docker info --format '{{.ServerVersion}}'
if ($LASTEXITCODE -ne 0) { throw 'The Docker Linux engine is unavailable.' }

& docker compose --progress plain -f $composeFile up -d --build
if ($LASTEXITCODE -ne 0) { throw 'The local deployment stack did not start.' }

$sshOptions = @(
    '-o', 'BatchMode=yes',
    '-o', 'StrictHostKeyChecking=accept-new',
    '-o', 'ConnectTimeout=10',
    '-p', '2222',
    '-i', (Resolve-Path -LiteralPath $IdentityFile).Path
)

$ready = $false
for ($attempt = 0; $attempt -lt 20; $attempt++) {
    & ssh @sshOptions $connection 'true' 2>$null
    if ($LASTEXITCODE -eq 0) { $ready = $true; break }
    Start-Sleep -Seconds 3
}
if (-not $ready) { throw 'Local SSH did not become ready.' }

$phpVersion = (& ssh @sshOptions $connection 'php -v' | Select-Object -First 1)
if ($LASTEXITCODE -ne 0 -or $phpVersion -notmatch '^PHP 8\.2\.') {
    throw "Expected PHP 8.2.x on the target; found: $phpVersion"
}

Push-Location $projectRoot
try {
    & (Join-Path $projectRoot 'deploy/deploy.ps1') `
        -HostName '127.0.0.1' -UserName 'oakfield' -Port 2222 `
        -RemotePath $appRoot -IdentityFile $IdentityFile
    if ($LASTEXITCODE -ne 0) { throw 'First deployment failed.' }

    $firstRelease = (& ssh @sshOptions $connection "readlink $appRoot/current").Trim()
    if ($LASTEXITCODE -ne 0 -or -not $firstRelease) { throw 'Could not read the first active release.' }
    $firstReleaseId = $firstRelease.Split('/')[-1]

    $firstHealth = Invoke-WebRequest 'http://127.0.0.1:8080/up' -UseBasicParsing
    if ($firstHealth.StatusCode -ne 200) { throw 'The first release health check failed.' }

    & (Join-Path $projectRoot 'deploy/deploy.ps1') `
        -HostName '127.0.0.1' -UserName 'oakfield' -Port 2222 `
        -RemotePath $appRoot -IdentityFile $IdentityFile
    if ($LASTEXITCODE -ne 0) { throw 'Second deployment failed.' }

    $secondRelease = (& ssh @sshOptions $connection "readlink $appRoot/current").Trim()
    if ($LASTEXITCODE -ne 0 -or $secondRelease -eq $firstRelease) {
        throw 'The second deployment did not activate a new release.'
    }

    & ssh @sshOptions $connection "bash $appRoot/current/deploy/rollback.sh $appRoot $firstReleaseId"
    if ($LASTEXITCODE -ne 0) { throw 'Rollback failed.' }

    $activeRelease = (& ssh @sshOptions $connection "readlink $appRoot/current").Trim()
    if ($activeRelease -ne $firstRelease) { throw 'Rollback did not restore the first release.' }

    $rollbackHealth = Invoke-WebRequest 'http://127.0.0.1:8080/up' -UseBasicParsing
    if ($rollbackHealth.StatusCode -ne 200) { throw 'The rolled-back release health check failed.' }

    $fileModes = & ssh @sshOptions $connection "stat -c '%a %U %G' $appRoot/current $appRoot/shared/storage"
    if ($LASTEXITCODE -ne 0) { throw 'Could not inspect target file permissions.' }

    $cronReady = $false
    for ($attempt = 0; $attempt -lt 15; $attempt++) {
        & ssh @sshOptions $connection "test -s $appRoot/shared/storage/logs/cron.log"
        if ($LASTEXITCODE -eq 0) { $cronReady = $true; break }
        Start-Sleep -Seconds 5
    }
    if (-not $cronReady) { throw 'The cron-driven scheduler did not write its log.' }

    Write-Host "Local deployment proof passed. PHP: $phpVersion"
    Write-Host "First release: $firstReleaseId; second release: $($secondRelease.Split('/')[-1]); rollback active: $firstReleaseId"
    Write-Host "HTTP /up: $($firstHealth.StatusCode) before rollback, $($rollbackHealth.StatusCode) after rollback"
    Write-Host "File modes: $($fileModes -join '; ')"
    Write-Host 'Cron-driven scheduler: observed.'
} finally {
    Pop-Location
}
