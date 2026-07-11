# ============================================================
# serve_and_tunnel.ps1 — Despliega SportTime en un puerto propio
# (servidor embebido de PHP) y lo expone con un Cloudflare Tunnel.
#
# No usa los puertos 80/443 ni toca tu Apache/XAMPP.
#
# Uso:   powershell -ExecutionPolicy Bypass -File scripts\serve_and_tunnel.ps1
#        powershell -ExecutionPolicy Bypass -File scripts\serve_and_tunnel.ps1 -Port 8090
# Parar: scripts\stop_tunnel.ps1   (detiene cloudflared)  +  scripts\stop_serve.ps1
# ============================================================
param([int]$Port = 8082)

$ErrorActionPreference = 'Stop'
$root = Split-Path $PSScriptRoot           # carpeta sgdm/
$php  = 'C:\xampp\php\php.exe'
if (-not (Test-Path $php)) { $php = (Get-Command php -ErrorAction SilentlyContinue).Source }
if (-not $php) { Write-Host 'ERROR: no se encontró php.exe (XAMPP).'; exit 1 }

# ── 1) Servidor PHP embebido (front controller) ─────────────
# Rutas citadas para soportar espacios en la ruta del proyecto.
Write-Host "Iniciando SportTime en http://localhost:$Port ..."
$argline = '-S 127.0.0.1:{0} -t "{1}\public" "{1}\scripts\php_router.php"' -f $Port, $root
Start-Process $php -ArgumentList $argline -WorkingDirectory $root -WindowStyle Hidden
Start-Sleep -Seconds 2

# ── 2) Túnel Cloudflare hacia ese puerto ────────────────────
$log = Join-Path $root "cf_tunnel_$Port.log"
if (Test-Path $log) { Remove-Item $log -Force }
Start-Process 'cloudflared' `
    -ArgumentList 'tunnel', '--url', "http://localhost:$Port", '--no-autoupdate' `
    -RedirectStandardError $log -WindowStyle Hidden

Write-Host 'Abriendo túnel… (esperando URL pública)'
Start-Sleep -Seconds 8
$url = [regex]::Match((Get-Content $log -Raw -ErrorAction SilentlyContinue), 'https://[a-z0-9-]+\.trycloudflare\.com').Value
if ($url) {
    Write-Host ""
    Write-Host "  URL PÚBLICA:  $url"
    Write-Host "  Local:        http://localhost:$Port"
    Write-Host ""
    Write-Host "  Detener:  scripts\stop_tunnel.ps1  y  scripts\stop_serve.ps1"
} else {
    Write-Host "No se pudo obtener la URL todavía. Revisá cf_tunnel.log"
}
