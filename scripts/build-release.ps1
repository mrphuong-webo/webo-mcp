param(
    [string]$PluginSlug = "webo-mcp",
    [string]$Version = "",
    [string]$SourceDir = "..",
    [string]$OutputDir = "..\dist"
)

$scriptRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$sourcePath = Resolve-Path (Join-Path $scriptRoot $SourceDir)
$outputPath = Join-Path $scriptRoot $OutputDir
$tempPath = Join-Path $outputPath ("_tmp_" + $PluginSlug)

if (-not (Test-Path $outputPath)) {
    New-Item -Path $outputPath -ItemType Directory | Out-Null
}

if (Test-Path $tempPath) {
    Remove-Item -Path $tempPath -Recurse -Force
}

New-Item -Path $tempPath -ItemType Directory | Out-Null

$distignorePath = Join-Path $sourcePath ".distignore"
$excludePatterns = @()
if (Test-Path $distignorePath) {
    $excludePatterns = Get-Content $distignorePath | ForEach-Object { $_.Trim() } | Where-Object { $_ -and -not $_.StartsWith("#") }
}

$files = Get-ChildItem -Path $sourcePath -Recurse -File -Force | Where-Object {
    $relativePath = $_.FullName.Substring($sourcePath.Path.Length).TrimStart('\\') -replace '\\','/'
    # WordPress.org: no dotfiles, no .github trees (workflows are not dot-prefixed).
    if ($_.Name.StartsWith('.')) {
        return $false
    }
    if ($relativePath -match '(?i)(^|/)\.github(/|$)') {
        return $false
    }
    foreach ($pattern in $excludePatterns) {
        $normalizedPattern = $pattern.TrimStart('./')
        if ($normalizedPattern.EndsWith('/')) {
            if ($relativePath.StartsWith($normalizedPattern.TrimEnd('/'))) {
                return $false
            }
        } elseif ($relativePath -like $normalizedPattern) {
            return $false
        }
    }
    return $true
}

$targetRoot = Join-Path $tempPath $PluginSlug
New-Item -Path $targetRoot -ItemType Directory | Out-Null

foreach ($file in $files) {
    $relativePath = $file.FullName.Substring($sourcePath.Path.Length).TrimStart('\\')
    $targetFile = Join-Path $targetRoot $relativePath
    $targetDir = Split-Path -Parent $targetFile

    if (-not (Test-Path $targetDir)) {
        New-Item -Path $targetDir -ItemType Directory -Force | Out-Null
    }

    Copy-Item -Path $file.FullName -Destination $targetFile -Force
}

if (-not $Version) {
    $pluginMainFile = Join-Path $sourcePath ($PluginSlug + ".php")
    if (Test-Path $pluginMainFile) {
        $versionMatch = Select-String -Path $pluginMainFile -Pattern '^\s*\*\s*Version:\s*(.+)$'
        if ($versionMatch) {
            $Version = $versionMatch.Matches[0].Groups[1].Value.Trim()
        }
    }
}

if (-not $Version) {
    $Version = "dev"
}

$zipPath = Join-Path $outputPath ("$PluginSlug-$Version.zip")
if (Test-Path $zipPath) {
    Remove-Item $zipPath -Force
}

Compress-Archive -Path (Join-Path $tempPath $PluginSlug) -DestinationPath $zipPath -CompressionLevel Optimal
Remove-Item -Path $tempPath -Recurse -Force

Write-Output "Release package created: $zipPath"