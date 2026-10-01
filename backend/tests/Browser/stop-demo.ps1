# Arrête le serveur lancé par serve-demo.ps1 (processus et sous-processus PHP).
#
#   powershell -ExecutionPolicy Bypass -File backend\tests\Browser\stop-demo.ps1
$root = (Resolve-Path (Join-Path $PSScriptRoot '..\..\..')).Path
$pidFile = Join-Path $root '.tmp\serve-demo.pid'
if (-not (Test-Path $pidFile)) { Write-Output 'Aucun serveur de démonstration enregistré.'; exit 0 }
$id = Get-Content $pidFile
taskkill /PID $id /T /F | Out-Null
Remove-Item $pidFile
Write-Output "Serveur de démonstration arrêté (PID $id)."
