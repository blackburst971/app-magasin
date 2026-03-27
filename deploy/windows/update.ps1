#Requires -RunAsAdministrator
# ==============================================================================
#  App Magasin - Mise a jour Windows Server 2019
#
#  USAGE (depuis la racine du projet) :
#    PowerShell -ExecutionPolicy Bypass -File deploy\windows\update.ps1
#
#  Ce script :
#    1. Git pull (recupere les nouveaux fichiers depuis GitHub)
#    2. Composer install (nouvelles dependances PHP si besoin)
#    3. Migrations base de donnees
#    4. Reconstruction des caches Laravel
#    5. Redemarrage du queue worker
#    (Pas de npm/vite - les assets compiles sont inclus dans git)
# ==============================================================================
Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

# ── Variables ─────────────────────────────────────────────────────────────────
$APP_DIR  = (Get-Location).Path
$PHP_DIR  = "C:\php"
$SITE     = "AppMagasin"
$IP1      = "10.15.9.114"
$IP2      = "10.15.80.229"
$PORT     = 8080

function Step($n, $msg) { Write-Host "`n[$n] $msg" -ForegroundColor Cyan }
function OK($msg)        { Write-Host "  [OK] $msg" -ForegroundColor Green }
function Warn($msg)      { Write-Host "  [!!] $msg" -ForegroundColor Yellow }
function Fail($msg)      { Write-Host "  [XX] $msg" -ForegroundColor Red; exit 1 }

# ==============================================================================
# Validation
# ==============================================================================
if (-not (Test-Path "$APP_DIR\artisan")) {
    Fail "Lancez ce script depuis la racine du projet (artisan introuvable dans $APP_DIR)."
}
if (-not (Test-Path "$APP_DIR\.git")) {
    Fail "Ce dossier n'est pas un depot Git. Lancez d'abord deploy\windows\git-init.ps1"
}

Write-Host ""
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host "  App Magasin - Mise a jour via Git" -ForegroundColor Cyan
Write-Host "==========================================" -ForegroundColor Cyan

# ==============================================================================
# ETAPE 1 — Mode maintenance
# ==============================================================================
Step "1/6" "Mode maintenance ON"

Set-Location $APP_DIR
& php artisan down --render="errors.503" --retry=60 2>$null
if ($LASTEXITCODE -ne 0) { & php artisan down --retry=60 }
OK "Application en maintenance"

# ==============================================================================
# ETAPE 2 — Git pull
# ==============================================================================
Step "2/6" "Recuperation des mises a jour (git pull)"

# Verifier que git est disponible
$_gitObj = Get-Command git -ErrorAction SilentlyContinue
$gitPath = if ($_gitObj) { $_gitObj.Source } else { $null }
if (-not $gitPath) {
    $candidates = @("C:\Program Files\Git\bin\git.exe", "C:\Program Files (x86)\Git\bin\git.exe")
    foreach ($c in $candidates) {
        if (Test-Path $c) { $gitPath = $c; break }
    }
}
if (-not $gitPath) {
    Fail "git n'est pas installe. Installez Git for Windows : https://git-scm.com/download/win"
}

# Sauvegarder le hash avant pull
$hashBefore = & git rev-parse --short HEAD 2>$null

# Stash les fichiers locaux non commites (ex: .env ne doit jamais etre ecrase)
& git stash 2>$null

# Pull
& git pull origin main
if ($LASTEXITCODE -ne 0) {
    & git stash pop 2>$null
    Fail "git pull a echoue. Verifiez la connectivite et les droits."
}

# Restaurer les fichiers locaux
& git stash pop 2>$null

$hashAfter = & git rev-parse --short HEAD 2>$null
if ($hashBefore -eq $hashAfter) {
    OK "Aucune mise a jour disponible (hash: $hashAfter) - poursuite quand meme"
} else {
    OK "Mis a jour : $hashBefore -> $hashAfter"
}

# Afficher le dernier commit
$lastCommit = & git log -1 --pretty=format:"%s (%ai)" 2>$null
OK "Dernier commit : $lastCommit"

# ==============================================================================
# ETAPE 3 — Dependances PHP
# ==============================================================================
Step "3/6" "Mise a jour des dependances PHP"

& php artisan clear-compiled 2>$null
& php artisan package:discover --ansi
& composer install --no-dev --optimize-autoloader --no-interaction --quiet
OK "Composer OK"

# ==============================================================================
# ETAPE 4 — Migrations base de donnees
# ==============================================================================
Step "4/6" "Migrations base de donnees"

& php artisan migrate --force
OK "Migrations OK"

# ==============================================================================
# ETAPE 5 — Reconstruction des caches
# ==============================================================================
Step "5/6" "Reconstruction des caches"

& php artisan config:cache
& php artisan route:cache
& php artisan view:cache
& php artisan event:cache
OK "Caches reconstruits"

# Lien storage (au cas ou il aurait ete perdu)
if (-not (Test-Path "public\storage")) {
    & php artisan storage:link
    $dir  = "$APP_DIR\public\storage"
    $acl  = Get-Acl $dir
    $rule = New-Object System.Security.AccessControl.FileSystemAccessRule(
        "IIS_IUSRS", "Modify", "ContainerInherit,ObjectInherit", "None", "Allow"
    )
    $acl.SetAccessRule($rule)
    Set-Acl $dir $acl
    OK "Lien storage recree"
}

# Verification binding IIS
Import-Module WebAdministration -ErrorAction SilentlyContinue
if (Test-Path "IIS:\Sites\$SITE") {
    $bindings  = Get-WebBinding -Name $SITE -ErrorAction SilentlyContinue
    $has2ndIP  = $bindings | Where-Object { $_.bindingInformation -like "*${IP2}:${PORT}*" }
    if (-not $has2ndIP) {
        New-WebBinding -Name $SITE -IPAddress $IP2 -Port $PORT -Protocol "http"
        OK "Binding ${IP2}:${PORT} ajoute"
    }
}

# ==============================================================================
# ETAPE 6 — Redemarrage des services
# ==============================================================================
Step "6/6" "Redemarrage des services"

Stop-Service -Name "AppMagasinWorker" -Force -ErrorAction SilentlyContinue
Start-Service -Name "AppMagasinWorker" -ErrorAction SilentlyContinue
OK "Queue worker redemarre"

# Recycler le pool IIS pour vider le cache PHP
Import-Module WebAdministration -ErrorAction SilentlyContinue
$pool = "AppMagasinPool"
if (Test-Path "IIS:\AppPools\$pool") {
    Restart-WebAppPool -Name $pool
    OK "Pool IIS '$pool' recycle"
}

& php artisan up
OK "Application remise en ligne"

# ==============================================================================
# RESUME
# ==============================================================================
Write-Host ""
Write-Host "==========================================" -ForegroundColor Green
Write-Host "  Mise a jour terminee !" -ForegroundColor Green
Write-Host "  Commit deploye : $hashAfter" -ForegroundColor Green
Write-Host "==========================================" -ForegroundColor Green
Write-Host ""
Write-Host "  http://${IP1}:${PORT}" -ForegroundColor Yellow
Write-Host "  http://${IP2}:${PORT}" -ForegroundColor Yellow
Write-Host ""
