$ErrorActionPreference = 'Stop'

$source = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$releaseRoot = $PSScriptRoot
$stage = Join-Path $env:TEMP ('crossguard-infinityfree-' + [guid]::NewGuid().ToString('N'))
$zipPath = Join-Path $releaseRoot 'CrossGuard-InfinityFree.zip'
New-Item -ItemType Directory -Path $stage | Out-Null
Write-Host "Preparing release files in $stage"

try {
    robocopy $source $stage /E /MT:32 /R:1 /W:1 /NFL /NDL /NJH /NJS /NP `
        /XD .git .github .codex .vscode node_modules tests deployment bootstrap\cache `
            storage database\seeders `
        /XF .env .env.example .env.backup .env.production example.txt
    if ($LASTEXITCODE -ge 8) {
        throw "Project copy failed with robocopy exit code $LASTEXITCODE."
    }
    Write-Host 'Copied application files.'

    $bootstrapCache = Join-Path $stage 'bootstrap\cache'
    New-Item -ItemType Directory -Force -Path $bootstrapCache | Out-Null
    Get-ChildItem -LiteralPath $bootstrapCache -Filter '*.php' -File -ErrorAction SilentlyContinue |
        Remove-Item -Force
    foreach ($directory in @('storage\app\public', 'storage\framework\cache\data', 'storage\framework\sessions', 'storage\framework\views', 'storage\logs')) {
        New-Item -ItemType Directory -Force -Path (Join-Path $stage $directory) | Out-Null
    }

    Copy-Item (Join-Path $releaseRoot 'htdocs.htaccess') (Join-Path $stage '.htaccess')
    Copy-Item (Join-Path $releaseRoot '.env.production.example') (Join-Path $stage '.env.production.example')
    Copy-Item (Join-Path $releaseRoot 'README.md') (Join-Path $stage 'DEPLOYMENT-INFINITYFREE.md')

    Push-Location $stage
    try {
        Write-Host 'Configuring a non-optimized autoloader for the shared-host file limit.'
        $composerJson = Get-Content 'composer.json' -Raw | ConvertFrom-Json
        $composerJson.config.'optimize-autoloader' = $false
        $composerJson.extra.laravel.'dont-discover' = @('laravel/pail')
        $composerJson | ConvertTo-Json -Depth 100 | Set-Content 'composer.json' -Encoding utf8NoBOM

        Write-Host 'Installing production dependencies.'
        composer install --no-dev --no-interaction --prefer-dist --no-progress --no-scripts
        if ($LASTEXITCODE -ne 0) { throw "Composer install failed with exit code $LASTEXITCODE." }

        Write-Host 'Generating Laravel package manifest.'
        php artisan package:discover --ansi
        if ($LASTEXITCODE -ne 0) { throw "Laravel package discovery failed with exit code $LASTEXITCODE." }

        $seedersPath = Join-Path $stage 'database\seeders'
        if (Test-Path -LiteralPath $seedersPath) {
            $resolvedStage = (Resolve-Path -LiteralPath $stage).Path.TrimEnd('\') + '\'
            $resolvedSeeders = (Resolve-Path -LiteralPath $seedersPath).Path
            if (-not $resolvedSeeders.StartsWith($resolvedStage, [StringComparison]::OrdinalIgnoreCase)) {
                throw 'Refusing to remove seeders outside the temporary release folder.'
            }
            Remove-Item -LiteralPath $resolvedSeeders -Recurse -Force
        }
        Remove-Item -LiteralPath 'phpstan.neon', 'phpunit.xml', 'pint.json' -Force -ErrorAction SilentlyContinue

        # Composer cannot run on the host; omit its project manifests from the runtime archive.
        Remove-Item -LiteralPath 'composer.json', 'composer.lock' -Force
    }
    finally {
        Pop-Location
    }

    $oversizedPhp = Get-ChildItem $stage -Recurse -File |
        Where-Object { $_.Extension -eq '.php' -and $_.Length -ge 1000000 }
    if ($oversizedPhp) {
        $oversizedPhp | Select-Object FullName, Length | Format-Table -AutoSize
        throw 'The release contains a PHP file larger than the InfinityFree upload limit.'
    }

    $emptyFiles = @(Get-ChildItem -LiteralPath $stage -Recurse -File | Where-Object Length -eq 0)
    if ($emptyFiles.Count -gt 0) {
        $emptyFiles | Select-Object FullName | Format-Table -AutoSize
        throw 'The release contains zero-byte files, which the FTPS deploy action cannot upload reliably.'
    }

    if (Test-Path -LiteralPath $zipPath) {
        Remove-Item -LiteralPath $zipPath -Force
    }

    Add-Type -AssemblyName System.IO.Compression.FileSystem
    [IO.Compression.ZipFile]::CreateFromDirectory(
        $stage,
        $zipPath,
        [IO.Compression.CompressionLevel]::Optimal,
        $false
    )
    Write-Host 'Created ZIP archive; checking required files.'

    $archive = [IO.Compression.ZipFile]::OpenRead($zipPath)
    try {
        $paths = @($archive.Entries | ForEach-Object FullName)
        foreach ($required in @('.htaccess', '.env.production.example', 'public/index.php', 'vendor/autoload.php', 'database/schema/mysql-schema.sql')) {
            if ($required -notin $paths) {
                throw "The release archive is missing $required."
            }
        }
        if ('.env' -in $paths) { throw 'Refusing to package the local .env file.' }
    }
    finally {
        $archive.Dispose()
    }

    Get-Item -LiteralPath $zipPath | Select-Object FullName, Length
}
finally {
    Write-Host "Build folder retained for inspection: $stage"
}
