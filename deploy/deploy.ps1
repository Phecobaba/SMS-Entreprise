[CmdletBinding()]
param (
    [Parameter(Mandatory)]
    [ValidatePattern('^[A-Za-z0-9.-]+$')]
    [string] $HostName,

    [Parameter(Mandatory)]
    [ValidatePattern('^[A-Za-z0-9._-]+$')]
    [string] $UserName,

    [Parameter(Mandatory)]
    [ValidatePattern('^[A-Za-z0-9_./-]+$')]
    [string] $RemotePath,

    [ValidateRange(1, 65535)]
    [int] $Port = 22,

    [string] $IdentityFile,

    [switch] $ValidateOnly,

    [switch] $UseExistingBuild
)

$ErrorActionPreference = 'Stop'
$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
if ($UseExistingBuild -and ($HostName -notin @('localhost', '127.0.0.1'))) {
    throw '-UseExistingBuild is restricted to a local deployment target.'
}
$php = if (Test-Path 'C:\xampp\php\php.exe') {
    'C:\xampp\php\php.exe'
} elseif (Get-Command php -ErrorAction SilentlyContinue) {
    (Get-Command php).Source
} else {
    throw 'PHP was not found. Install PHP 8.2+ or add it to PATH.'
}
$phpTestArgs = if (($php -eq 'C:\xampp\php\php.exe') -and (Test-Path 'C:\xampp\php\ext\php_gd.dll')) { @('-d', 'extension=gd') } else { @() }
$composer = if (($php -eq 'C:\xampp\php\php.exe') -and (Test-Path 'C:\ProgramData\ComposerSetup\bin\composer.phar')) {
    'C:\ProgramData\ComposerSetup\bin\composer.phar'
} elseif (Get-Command composer -ErrorAction SilentlyContinue) {
    (Get-Command composer).Source
} else {
    throw 'Composer was not found.'
}

foreach ($command in @('npm', 'ssh', 'scp', 'tar')) {
    if (-not (Get-Command $command -ErrorAction SilentlyContinue)) {
        throw "$command was not found in PATH."
    }
}

Push-Location $projectRoot
try {
    if ($UseExistingBuild) {
        if (-not (Test-Path 'public/build/manifest.json')) {
            throw 'The local existing-build mode requires a completed public/build/manifest.json.'
        }
    } else {
        if ($composer.EndsWith('.phar')) {
            & $php $composer validate --strict
        } else {
            & $composer validate --strict
        }
        if ($LASTEXITCODE -ne 0) { throw 'Composer validation failed.' }

        if ($phpTestArgs.Count -gt 0) {
            # artisan test launches a new PHP process and drops command-line -d settings.
            & $php @phpTestArgs vendor/phpunit/phpunit/phpunit
        } else {
            & $php artisan test
        }
        if ($LASTEXITCODE -ne 0) { throw 'The test suite failed.' }

        & npm ci
        if ($LASTEXITCODE -ne 0) { throw 'npm ci failed.' }

        & npm run build
        if ($LASTEXITCODE -ne 0) { throw 'The production asset build failed.' }
    }

    $releaseId = Get-Date -Format 'yyyyMMddHHmmss'
    $temporaryRoot = Join-Path ([System.IO.Path]::GetTempPath()) "oakfield-deploy-$releaseId"
    $archive = Join-Path $temporaryRoot "oakfield-$releaseId.tar.gz"
    New-Item -ItemType Directory -Path $temporaryRoot | Out-Null

    try {
        & tar -czf $archive `
            --exclude='./.git' `
            --exclude='./.env' `
            --exclude='./deploy/local/.local' `
            --exclude='./node_modules' `
            --exclude='./vendor' `
            --exclude='./tests' `
            --exclude='./storage/logs/*' `
            --exclude='./storage/framework/cache/*' `
            --exclude='./storage/framework/sessions/*' `
            --exclude='./storage/framework/views/*' `
            --exclude='./database/*.sqlite' `
            .
        if ($LASTEXITCODE -ne 0) { throw 'Could not create the deployment archive.' }

        if ($ValidateOnly) {
            Write-Host 'Deployment preflight and archive validation completed successfully.'
            return
        }

        $remoteArchive = "oakfield-$releaseId.tar.gz"
        $remoteScript = "oakfield-remote-deploy-$releaseId.sh"
        $connection = "$UserName@$HostName"
        $sshOptions = @('-o', 'BatchMode=yes', '-o', 'StrictHostKeyChecking=accept-new', '-o', 'ConnectTimeout=10', '-p', $Port.ToString())
        $scpOptions = @('-o', 'BatchMode=yes', '-o', 'StrictHostKeyChecking=accept-new', '-o', 'ConnectTimeout=10', '-P', $Port.ToString())

        if ($IdentityFile) {
            $resolvedIdentity = (Resolve-Path -LiteralPath $IdentityFile).Path
            $sshOptions += @('-i', $resolvedIdentity)
            $scpOptions += @('-i', $resolvedIdentity)
        }

        & scp @scpOptions $archive "${connection}:~/$remoteArchive"
        if ($LASTEXITCODE -ne 0) { throw 'Archive upload failed.' }

        & scp @scpOptions (Join-Path $PSScriptRoot 'remote-deploy.sh') "${connection}:~/$remoteScript"
        if ($LASTEXITCODE -ne 0) { throw 'Remote deployment script upload failed.' }

        & ssh @sshOptions $connection "bash ~/$remoteScript '$RemotePath' ~/$remoteArchive '$releaseId'"
        if ($LASTEXITCODE -ne 0) { throw 'Remote deployment failed.' }

        Write-Host "Deployment $releaseId completed successfully."
    } finally {
        $resolvedTemporaryRoot = (Resolve-Path -LiteralPath $temporaryRoot -ErrorAction SilentlyContinue)
        $systemTemporaryRoot = [System.IO.Path]::GetFullPath([System.IO.Path]::GetTempPath())
        if ($resolvedTemporaryRoot -and $resolvedTemporaryRoot.Path.StartsWith($systemTemporaryRoot)) {
            Remove-Item -LiteralPath $resolvedTemporaryRoot.Path -Recurse -Force
        }
    }
} finally {
    Pop-Location
}
