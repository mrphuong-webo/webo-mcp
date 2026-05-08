#requires -Version 5.1
<#
.SYNOPSIS
  Day domain WP Ultimo qua MCP (network.webo.vn): orchestrate DNS + sync Cloudflare + recheck, roi poll list-domains cho toi khi active + stage done.

.PARAMETER Domain
.PARAMETER SiteId
.PARAMETER MaxRounds
.PARAMETER SleepSeconds
.PARAMETER SkipOrchestrate
#>

[CmdletBinding()]
param(
    [string] $RouterUrl = 'https://network.webo.vn/wp-json/mcp/v1/router',
    [string] $WpUser = 'webo',
    [Parameter(Mandatory = $true)][string] $Domain,
    [int] $SiteId = 0,
    [int] $MaxRounds = 12,
    [int] $SleepSeconds = 20,
    [switch] $SkipOrchestrate
)

$ErrorActionPreference = 'Stop'

if (-not $env:WEBO_NETWORK_WP_APP_PASSWORD) {
    Write-Error 'Thieu WEBO_NETWORK_WP_APP_PASSWORD.'
}

function Get-BasicHeaders {
    $pair = [Convert]::ToBase64String([Text.Encoding]::ASCII.GetBytes("${WpUser}:$($env:WEBO_NETWORK_WP_APP_PASSWORD)"))
    return @{
        Authorization  = "Basic $pair"
        'Content-Type' = 'application/json; charset=utf-8'
    }
}

function Invoke-McpRpc {
    param([string] $Uri, [hashtable] $Headers, [hashtable] $Body)
    $json = $Body | ConvertTo-Json -Compress -Depth 16
    return Invoke-RestMethod -Uri $Uri -Method Post -Headers $Headers -Body $json
}

function Test-DomainRowReady {
    param($Row)
    if (-not $Row) { return $false }
    $st = [string] $Row.stage
    $active = [bool] $Row.active
    return ($active -and ($st -eq 'done' -or $st -eq 'done-without-ssl'))
}

$headers = Get-BasicHeaders

function Initialize-McpSession {
    $ini = Invoke-McpRpc -Uri $RouterUrl -Headers $headers -Body @{
        jsonrpc = '2.0'; method = 'initialize'; params = @{}; id = 1
    }
    if (-not $ini.result.session_id) {
        Write-Error "initialize failed: $($ini | ConvertTo-Json -Compress -Depth 6)"
    }
    return [string] $ini.result.session_id
}

# Sessions expire quickly after long calls — refresh before each RPC batch.
function Invoke-McpToolCall {
    param([int] $Rid, [string] $Name, [hashtable] $ToolArguments, [string] $SessionId)
    return Invoke-McpRpc -Uri $RouterUrl -Headers $headers -Body @{
        jsonrpc = '2.0'
        method  = 'tools/call'
        params  = @{
            session_id = $SessionId
            name       = $Name
            arguments  = $ToolArguments
        }
        id      = $Rid
    }
}

$idCounter = 10

# --- Phase A: push integrations ---
if (-not $SkipOrchestrate) {
    $sidA = Initialize-McpSession
    Write-Host "session_id (orchestrate)=$sidA"
    $orchArgs = @{
        action                   = 'orchestrate-domain-dns'
        domain                   = $Domain
        ensure_cloudflare_sync   = $true
        persist_ultimo_meta      = $true
        attempt_tino_ns_update   = $false
    }
    if ($SiteId -gt 0) { $orchArgs['site_id'] = $SiteId }

    Write-Host "`n=== infra-ops orchestrate-domain-dns ==="
    $r1 = Invoke-McpToolCall -Rid ($idCounter++) -Name 'webo-ultimo/infra-ops' -ToolArguments $orchArgs -SessionId $sidA
    $r1 | ConvertTo-Json -Depth 14
    if ($r1.error) {
        Write-Warning "Orchestrate error (continuing): $($r1.error.message)"
    }
}

$sidB = Initialize-McpSession
Write-Host "`nsession_id (sync)=$sidB"
$syncArgs = @{
    action         = 'sync-cloudflare-domain'
    domain         = $Domain
    sync_aapanel   = $true
    force_rebind   = $true
    run_stage_now  = $true
}
if ($SiteId -gt 0) { $syncArgs['site_id'] = $SiteId }

Write-Host "`n=== infra-ops sync-cloudflare-domain ==="
$r2 = Invoke-McpToolCall -Rid ($idCounter++) -Name 'webo-ultimo/infra-ops' -ToolArguments $syncArgs -SessionId $sidB
$r2 | ConvertTo-Json -Depth 12
if ($r2.error) {
    Write-Warning "sync-cloudflare-domain: $($r2.error.message)"
}

$sidC = Initialize-McpSession
Write-Host "`nsession_id (recheck)=$sidC"
$recheckArgs = @{
    action          = 'recheck-domain'
    domain          = $Domain
    run_now         = $true
    sync_aapanel    = $true
    force_rebind    = $true
    async_dns_probe = $true
}
if ($SiteId -gt 0) { $recheckArgs['site_id'] = $SiteId }

Write-Host "`n=== domains-mutate recheck-domain ==="
$r3 = Invoke-McpToolCall -Rid ($idCounter++) -Name 'webo-ultimo/domains-mutate' -ToolArguments $recheckArgs -SessionId $sidC
$r3 | ConvertTo-Json -Depth 12
if ($r3.error) {
    Write-Warning "recheck-domain: $($r3.error.message)"
}

# --- Phase B: poll ---
for ($i = 1; $i -le $MaxRounds; $i++) {
    Write-Host "`n--- poll $i / $MaxRounds (sleep $SleepSeconds s) ---"
    if ($i -gt 1) { Start-Sleep -Seconds $SleepSeconds }

    $sidP = Initialize-McpSession
    $listArgs = @{
        action = 'list-domains'
        domain = $Domain
        limit  = 10
    }
    $lr = Invoke-McpToolCall -Rid ($idCounter++) -Name 'webo-ultimo/customers-query' -ToolArguments $listArgs -SessionId $sidP
    if ($lr.error) {
        Write-Error $lr.error.message
    }
    $rows = $lr.result
    if ($rows -isnot [System.Array]) { $rows = @($lr.result) }
    $row = $rows | Select-Object -First 1
    $row | ConvertTo-Json -Depth 10

    if (Test-DomainRowReady -Row $row) {
        Write-Host "`nOK: domain active + stage ready."
        exit 0
    }
}

Write-Host "`nChua dat trang thai done trong gioi han poll. Kiem tra NS tai registrar / Cloudflare zone active / log tren server."
exit 2
