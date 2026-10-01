# Serveur de démonstration sur le réseau local (PC + téléphone).
#
# Utilise UNIQUEMENT la base de test isolée .tmp/pharmacare-stabilisation-browser.sqlite
# et ses comptes de démonstration ; la base configurée dans .env n'est jamais ouverte.
# Le processus reste actif après la fermeture du terminal. Arrêt : stop-demo.ps1.
#
#   powershell -ExecutionPolicy Bypass -File backend\tests\Browser\serve-demo.ps1
param(
    [string]$PublicUrl = 'http://192.168.137.1:8000',
    [int]$Port = 8000
)
$ErrorActionPreference = 'Stop'
$root = (Resolve-Path (Join-Path $PSScriptRoot '..\..\..')).Path
$tmp = Join-Path $root '.tmp'
$database = Join-Path $tmp 'pharmacare-stabilisation-browser.sqlite'
$pidFile = Join-Path $tmp 'serve-demo.pid'

if (-not (Test-Path $database)) { throw "Base de test absente : $database (voir backend/tests/Browser/README.md)." }
if (Test-Path $pidFile) {
    $previous = Get-Content $pidFile
    if (Get-Process -Id $previous -ErrorAction SilentlyContinue) { throw "Serveur déjà lancé (PID $previous). Arrêt : stop-demo.ps1." }
}

# Variables d'environnement prioritaires sur .env (Dotenv ne les écrase pas).
$env:APP_ENV = 'local'
$env:APP_URL = $PublicUrl
$env:DB_CONNECTION = 'sqlite'
$env:DB_DATABASE = $database
$env:DB_URL = ''
$env:QUEUE_CONNECTION = 'sync'   # pas de worker nécessaire
$env:MAIL_MAILER = 'log'         # aucun e-mail réel envoyé
$env:SESSION_DRIVER = 'file'

$process = Start-Process -FilePath 'php' `
    -ArgumentList 'artisan', 'serve', '--host=0.0.0.0', "--port=$Port", '--no-reload' `
    -WorkingDirectory (Join-Path $root 'backend') -WindowStyle Hidden -PassThru `
    -RedirectStandardOutput (Join-Path $tmp 'serve-demo.log') `
    -RedirectStandardError (Join-Path $tmp 'serve-demo.err.log')
$process.Id | Set-Content $pidFile
Write-Output "Serveur de démonstration lancé (PID $($process.Id)) : $PublicUrl"
