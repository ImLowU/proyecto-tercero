# ============================================================
# stop_serve.ps1 — Detiene el servidor embebido de PHP de SportTime
# (solo el que sirve este proyecto vía php_router.php; no toca Apache)
# ============================================================
$procs = Get-CimInstance Win32_Process | Where-Object { $_.CommandLine -like '*php_router.php*' }
if ($procs) {
    $procs | ForEach-Object { try { Stop-Process -Id $_.ProcessId -Force -ErrorAction Stop; "Detenido PHP server (PID $($_.ProcessId))" } catch {} }
} else {
    Write-Host "No hay servidor PHP de SportTime corriendo."
}
