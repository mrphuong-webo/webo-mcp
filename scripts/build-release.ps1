# WordPress.org-safe release zip: no .git, no archives, no dev/docs, only runtime files.
param(
    [string]$PluginSlug = "webo-mcp",
    [string]$Version = "",
    [string]$SourceDir = "..",
    [string]$OutputDir = "..\dist"
)

$ErrorActionPreference = "Stop"
$scriptRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$sourcePath = (Resolve-Path (Join-Path $scriptRoot $SourceDir)).Path
$outputPath = Join-Path $scriptRoot $OutputDir
$tempPath = Join-Path $outputPath ("_tmp_" + $PluginSlug)

# --- 1) Xóa sạch thư mục dist (zip cũ, _tmp_, mọi thứ) ---
if (Test-Path $outputPath) {
    Get-ChildItem -Path $outputPath -Force | Remove-Item -Recurse -Force
}
New-Item -Path $outputPath -ItemType Directory -Force | Out-Null

New-Item -Path $tempPath -ItemType Directory -Force | Out-Null

# Tiền tố đường dẫn không bao giờ đưa vào zip
$blockedPathRegex = [regex]::new(
    '(?i)(^|/)(\.git|\.github|dist|scripts|examples|docs|node_modules)(/|$)|' +
    '(^|/)vendor/wordpress/abilities-api/packages(/|$)|' +
    '(^|/)\.cursor(/|$)'
)

# Phần mở rộng không được (nén, dev, lock, v.v.)
$blockedExt = [System.Collections.Generic.HashSet[string]]::new([StringComparer]::OrdinalIgnoreCase)
@(
    'zip','7z','rar','tar','gz','tgz','bz2','xz','lzma',
    'md','json','yml','yaml','lock','map','ps1','sh','bat','cmd',
    'gitignore','distignore','editorconfig','nvmrc','xml','neon','dist','xml.dist'
) | ForEach-Object { [void]$blockedExt.Add($_) }

function Test-AllowedFile {
    param([string]$RelativePath, [string]$FileName)

    $rel = $RelativePath -replace '\\', '/'
    if ($blockedPathRegex.IsMatch($rel)) { return $false }

    $ext = [System.IO.Path]::GetExtension($FileName)
    if ($ext.Length -gt 0) { $ext = $ext.TrimStart('.').ToLowerInvariant() } else { $ext = '' }

    # File không có đuôi (trừ LICENSE đôi khi) — loại
    if ($ext -eq '') { return $false }

    if ($blockedExt.Contains($ext)) { return $false }

    # readme.txt (bắt buộc WordPress.org)
    if ($rel -eq 'readme.txt' -and $ext -eq 'txt') { return $true }

    # Chỉ readme.txt ở root; các .txt khác (vd. prompt n8n) không đưa vào
    if ($ext -eq 'txt' -and $rel -ne 'readme.txt') { return $false }

    # Ngôn ngữ
    if ($rel -match '(?i)^languages/') {
        return @('pot','po','mo') -contains $ext
    }

    # Vendor: chỉ PHP (bỏ toàn bộ package.json, README, tests, v.v.)
    if ($rel -match '(?i)^vendor/') {
        if ($ext -ne 'php') { return $false }
        if ($rel -match '(?i)/tests?(/|$)') { return $false }
        return $true
    }

    # Phần còn lại của plugin (inc, assets, …)
    $allowed = @('php','css','js','png','jpg','jpeg','gif','svg','webp','ico','woff','woff2','ttf','eot','otf')
    return $allowed -contains $ext
}

$files = Get-ChildItem -Path $sourcePath -Recurse -File -Force | Where-Object {
    $relativePath = $_.FullName.Substring($sourcePath.Length).TrimStart('\', '/')
    Test-AllowedFile -RelativePath $relativePath -FileName $_.Name
}

$targetRoot = Join-Path $tempPath $PluginSlug
New-Item -Path $targetRoot -ItemType Directory -Force | Out-Null

foreach ($file in $files) {
    $relativePath = $file.FullName.Substring($sourcePath.Length).TrimStart('\', '/')
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
if (-not $Version) { $Version = "dev" }

$zipPath = Join-Path $outputPath ("$PluginSlug-$Version.zip")
Compress-Archive -Path $targetRoot -DestinationPath $zipPath -CompressionLevel Optimal
Remove-Item -Path $tempPath -Recurse -Force

Write-Output "Release package created: $zipPath"
