$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $MyInvocation.MyCommand.Path
if (-not (Test-Path (Join-Path $root 'public\router.php'))) {
    throw 'Missing public/router.php. Extract patch ZIP in project root.'
}
$php = if ($env:SW_PHP_BIN) { $env:SW_PHP_BIN } elseif (Test-Path 'C:\xampp\php\php.exe') { 'C:\xampp\php\php.exe' } else { (Get-Command php -ErrorAction Stop).Source }
if (-not (Test-Path $php)) { throw "PHP not found: $php" }
$endpoint = 'http://127.0.0.1:8787/healthz'
function Get-Health {
    try {
        $res = Invoke-WebRequest -Uri $endpoint -UseBasicParsing -TimeoutSec 2 -ErrorAction Stop
        if ($res.Content.Trim() -eq 'STILLWIRED_HTTP_READY') { return $true }
        throw 'Port 8787 is held by an unexpected service.'
    } catch [System.Net.WebException] {
        return $false
    }
}
if (Get-Health) { Write-Host 'HTTP_ALREADY_RUNNING: 127.0.0.1:8787'; exit 0 }
$varDir = Join-Path $root 'var'
if (-not (Test-Path $varDir)) { New-Item -ItemType Directory -Path $varDir | Out-Null }
$out = Join-Path $varDir 'http-server.stdout.log'
$err = Join-Path $varDir 'http-server.stderr.log'
$proc = Start-Process -FilePath $php -ArgumentList @('-S','127.0.0.1:8787','-t','public','public/router.php') -WorkingDirectory $root -RedirectStandardOutput $out -RedirectStandardError $err -PassThru
Write-Host "HTTP_STARTED_PID=$($proc.Id)"
for ($i = 0; $i -lt 24; $i++) {
    Start-Sleep -Milliseconds 250
    if ($proc.HasExited) { break }
    if (Get-Health) {
        Write-Host 'HTTP_HEALTH_PASS: STILLWIRED_HTTP_READY'
        Write-Host 'LOCAL_ONLY: 127.0.0.1:8787'
        exit 0
    }
}
Write-Host 'HTTP_START_FAILED:'
if (Test-Path $err) { Get-Content $err | Select-Object -Last 20 }
if (-not $proc.HasExited) { taskkill.exe /PID $proc.Id /T /F | Out-Null }
exit 1
