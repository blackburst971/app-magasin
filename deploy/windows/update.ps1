#Requires -RunAsAdministrator
# ==============================================================================
#  App Magasin - Mise a jour Windows Server 2019
#
#  USAGE (depuis la racine du projet) :
#    PowerShell -ExecutionPolicy Bypass -File deploy\windows\update.ps1
#
#  Ce script :
#    0. Sauvegarde automatique (BDD + fichiers) AVANT toute modification
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
# ETAPE 0 — Sauvegarde automatique de securite AVANT toute modification
# ==============================================================================
Step "0/7" "Sauvegarde automatique de securite"

$BACKUP_DIR  = "$APP_DIR\storage\app\backups"
$DB_PATH     = "$APP_DIR\database\database.sqlite"
$STORAGE_DIR = "$APP_DIR\storage\app\public"

if (-not (Test-Path $BACKUP_DIR)) {
    New-Item -ItemType Directory -Path $BACKUP_DIR -Force | Out-Null
}

$backupName = "pre_deploy_" + (Get-Date -Format "yyyyMMdd_HHmmss") + ".zip"
$backupPath = "$BACKUP_DIR\$backupName"

try {
    Add-Type -Assembly "System.IO.Compression.FileSystem"

    $zip = [System.IO.Compression.ZipFile]::Open($backupPath, "Create")

    # Ajouter la base de donnees SQLite
    if (Test-Path $DB_PATH) {
        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
            $zip, $DB_PATH, "database/database.sqlite",
            [System.IO.Compression.CompressionLevel]::Optimal
        ) | Out-Null
    }

    # Ajouter les fichiers uploades (storage/app/public)
    if (Test-Path $STORAGE_DIR) {
        $storageItems = Get-ChildItem -Path $STORAGE_DIR -Recurse -File
        foreach ($item in $storageItems) {
            $relativePath = "storage/app/public/" + $item.FullName.Substring($STORAGE_DIR.Length + 1).Replace("\", "/")
            [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
                $zip, $item.FullName, $relativePath,
                [System.IO.Compression.CompressionLevel]::Optimal
            ) | Out-Null
        }
    }

    $zip.Dispose()

    $backupSize = [math]::Round((Get-Item $backupPath).Length / 1MB, 1)
    OK "Sauvegarde creee : $backupName ($backupSize Mo)"

    # Conserver uniquement les 5 dernieres sauvegardes automatiques de deploiement
    $oldBackups = Get-ChildItem -Path $BACKUP_DIR -Filter "pre_deploy_*.zip" |
                  Sort-Object LastWriteTime -Descending |
                  Select-Object -Skip 5
    foreach ($old in $oldBackups) {
        Remove-Item $old.FullName -Force
        Warn "Ancienne sauvegarde supprimee : $($old.Name)"
    }
} catch {
    Warn "Impossible de creer la sauvegarde : $_"
    Warn "La mise a jour continue, mais sauvegardez manuellement avant de continuer si necessaire."
}

# ==============================================================================
# ETAPE 1 — Mode maintenance
# ==============================================================================
Step "1/7" "Mode maintenance ON"

Set-Location $APP_DIR
& php artisan down --render="errors.503" --retry=60 2>$null
if ($LASTEXITCODE -ne 0) { & php artisan down --retry=60 }
OK "Application en maintenance"

# ==============================================================================
# ETAPE 2 — Git pull
# ==============================================================================
Step "2/7" "Recuperation des mises a jour (git pull)"

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
$hashBefore = & git rev-parse --short HEAD
if ($LASTEXITCODE -ne 0) { Fail "Impossible de lire le commit courant. Depot Git valide ?" }

# Pull (le .env est dans .gitignore donc git ne le touche jamais)
& git pull origin main
if ($LASTEXITCODE -ne 0) {
    Fail "git pull a echoue. Verifiez la connectivite et les droits GitHub."
}

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
Step "3/7" "Mise a jour des dependances PHP"

& php artisan clear-compiled 2>$null
& php artisan package:discover --ansi
& composer install --no-dev --optimize-autoloader --no-interaction --quiet
OK "Composer OK"

# ==============================================================================
# ETAPE 4 — Migrations base de donnees
# ==============================================================================
Step "4/7" "Migrations base de donnees"

& php artisan migrate --force
OK "Migrations OK"

# ==============================================================================
# ETAPE 5 — Reconstruction des caches
# ==============================================================================
Step "5/7" "Reconstruction des caches"

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
Step "6/7" "Redemarrage des services"

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
# ETAPE 7 — Confirmation de la sauvegarde disponible
# ==============================================================================
Step "7/7" "Verification de la sauvegarde"

if (Test-Path $backupPath) {
    $finalSize = [math]::Round((Get-Item $backupPath).Length / 1MB, 1)
    OK "Sauvegarde pre-deploiement disponible : $backupName ($finalSize Mo)"
    OK "Restauration possible depuis : Parametres > Sauvegardes"
}

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
