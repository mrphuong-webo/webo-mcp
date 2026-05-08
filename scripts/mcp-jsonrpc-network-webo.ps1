#requires -Version 5.1
<#
.SYNOPSIS
  Gọi WEBO MCP JSON-RPC trên network.webo.vn (giống @automattic/mcp-wordpress-remote): initialize → tools/list → tools/call.

.DESCRIPTION
  Dùng Application Password WordPress (Basic auth). Set biến môi trường trước khi chạy:
    setx WEBO_NETWORK_WP_APP_PASSWORD "xxxx-xxxx-xxxx-xxxx"
  (hoặc chỉ trong session hiện tại: $env:WEBO_NETWORK_WP_APP_PASSWORD = "...")

.PARAMETER RouterUrl
  Mặc định: https://network.webo.vn/wp-json/mcp/v1/router

.PARAMETER WpUser
  Mặc định: webo (khớp .cursor/mcp.json)

.PARAMETER Domain
  Tên miền cần tra cứu trong WP Ultimo (list-domains).

.PARAMETER ToolName
  Tool MCP để gọi. Trên network.webo.vn, Ultimo dùng dispatcher — tra domain: webo-ultimo/customers-query + arguments.action=list-domains (mặc định).

.PARAMETER DispatcherAction
  Khi dùng Ultimo dispatcher (customers-query / domains-mutate / infra-ops): giá trị action (vd: list-domains).

.PARAMETER SkipList
  Bỏ qua tools/list (chỉ initialize + tools/call).

.EXAMPLE
  .\scripts\mcp-jsonrpc-network-webo.ps1 -Domain "minhquangialai.com"
#>

[CmdletBinding()]
param(
    [string] $RouterUrl = 'https://network.webo.vn/wp-json/mcp/v1/router',
    [string] $WpUser = 'webo',
    [string] $Domain = 'minhquangialai.com',
    [string] $ToolName = 'webo-ultimo/customers-query',
    [string] $DispatcherAction = 'list-domains',
    [switch] $SkipList
)

$ErrorActionPreference = 'Stop'

if (-not $env:WEBO_NETWORK_WP_APP_PASSWORD) {
    Write-Error 'Thiếu WEBO_NETWORK_WP_APP_PASSWORD. Ví dụ: $env:WEBO_NETWORK_WP_APP_PASSWORD = "app-password-here"'
}

$pair = [Convert]::ToBase64String([Text.Encoding]::ASCII.GetBytes("${WpUser}:$($env:WEBO_NETWORK_WP_APP_PASSWORD)"))
$headers = @{
    Authorization  = "Basic $pair"
    'Content-Type' = 'application/json; charset=utf-8'
}

function Invoke-McpRpc {
    param([hashtable] $Body)
    $json = $Body | ConvertTo-Json -Compress -Depth 12
    return Invoke-RestMethod -Uri $RouterUrl -Method Post -Headers $headers -Body $json
}

# 1) initialize
$init = Invoke-McpRpc @{
    jsonrpc = '2.0'
    method  = 'initialize'
    params  = @{}
    id      = 1
}

if (-not $init.result.session_id) {
    Write-Error "initialize thất bại: $($init | ConvertTo-Json -Compress -Depth 8)"
}

$sessionId = [string] $init.result.session_id
Write-Host "session_id: $sessionId"

# 2) tools/list (optional)
if (-not $SkipList) {
    $list = Invoke-McpRpc @{
        jsonrpc = '2.0'
        method  = 'tools/list'
        params  = @{ session_id = $sessionId }
        id      = 2
    }
    Write-Host ''
    Write-Host '--- tools/list (rut gon) ---'
    $tools = $list.result.tools
    if ($null -eq $tools) {
        $list | ConvertTo-Json -Depth 6
    }
    else {
        $tools | Select-Object name, description | Format-Table -AutoSize
        $ultimo = @($tools | Where-Object { $_.name -like 'webo-ultimo*' })
        if ($ultimo.Count -gt 0) {
            Write-Host ''
            Write-Host "(webo-ultimo tools: $($ultimo.Count))"
        }
        else {
            Write-Warning 'Khong thay tool webo-ultimo trong tools/list — kiem tra addon webo-mcp-ultimo tren network.'
        }
    }
}

# 3) tools/call — tra domain mapping (Ultimo: dispatcher + action)
$arguments = @{
    domain = $Domain
    limit  = 50
}
if ($DispatcherAction -ne '') {
    $arguments['action'] = $DispatcherAction
}

$callBody = @{
    jsonrpc = '2.0'
    method  = 'tools/call'
    params  = @{
        session_id = $sessionId
        name       = $ToolName
        arguments  = $arguments
    }
    id      = 3
}

Write-Host "`n--- tools/call $ToolName ---"
$result = Invoke-McpRpc $callBody
$result | ConvertTo-Json -Depth 12

if ($result.error) {
    exit 1
}
