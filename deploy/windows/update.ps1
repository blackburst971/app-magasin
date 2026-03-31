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
#    3. Build des assets frontend (node + vite)
#    4. Migrations base de donnees
#    5. Reconstruction des caches Laravel
#    6. Redemarrage du queue worker
# ==============================================================================
Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

# ── Variables ─────────────────────────────────────────────────────────────────
$APP_DIR  = (Get-Location).Path
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
Step "0/8" "Sauvegarde automatique de securite"

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
Step "1/8" "Mode maintenance ON"

Set-Location $APP_DIR
& php artisan down --render="errors.503" --retry=60 2>$null
if ($LASTEXITCODE -ne 0) { & php artisan down --retry=60 }
OK "Application en maintenance"

# ==============================================================================
# ETAPE 2 — Git pull
# ==============================================================================
Step "2/8" "Recuperation des mises a jour (git pull)"

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

# Annuler les modifications locales sur les fichiers traces (build, scripts)
# Le .env est dans .gitignore et ne sera jamais touche
& git reset --hard HEAD
if ($LASTEXITCODE -ne 0) { Warn "git reset --hard a echoue (non bloquant)" }

# Supprimer les fichiers non traces qui pourraient bloquer le pull (ex: anciens assets de build)
& git clean -fd -- public/build/
if ($LASTEXITCODE -ne 0) { Warn "git clean public/build/ a echoue (non bloquant)" }

# Pull depuis GitHub
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
Step "3/8" "Mise a jour des dependances PHP"

& php artisan clear-compiled 2>$null
& php artisan package:discover --ansi
& composer install --no-dev --optimize-autoloader --no-interaction --quiet
OK "Composer OK"

# ==============================================================================
# ETAPE 4 — Build des assets frontend (Vite)
# ==============================================================================
Step "4/8" "Build des assets frontend (Vite)"

# Recherche de node.exe dans les emplacements courants
$nodePath = $null
$nodeCandidates = @(
    "C:\Program Files\nodejs\node.exe",
    "C:\Program Files (x86)\nodejs\node.exe",
    "$env:APPDATA\nvm\nodejs\node.exe",
    "$env:ProgramFiles\nvm\nodejs\node.exe"
)

# Chercher aussi dans le PATH
$_nodeObj = Get-Command node -ErrorAction SilentlyContinue
if ($_nodeObj) { $nodePath = $_nodeObj.Source }

if (-not $nodePath) {
    foreach ($c in $nodeCandidates) {
        if (Test-Path $c) { $nodePath = $c; break }
    }
}

if (-not $nodePath) {
    Warn "node.exe introuvable - le build Vite est ignore (assets du depot utilises)."
    Warn "Installez Node.js LTS depuis https://nodejs.org/ pour activer le build automatique."
} else {
    $nodeVersion = & "$nodePath" --version 2>$null
    OK "Node.js trouve : $nodePath ($nodeVersion)"

    # Installer les dependances npm si node_modules absent ou package.json plus recent
    if (-not (Test-Path "node_modules")) {
        Warn "node_modules absent - installation des dependances npm..."
        & "$nodePath" "$APP_DIR\node_modules\.bin\npm" install --prefer-offline 2>$null
        if ($LASTEXITCODE -ne 0) {
            # Fallback: chercher npm.cmd
            $npmPath = Join-Path (Split-Path $nodePath) "npm.cmd"
            if (Test-Path $npmPath) {
                & cmd /c "`"$npmPath`" install --prefer-offline"
            }
        }
    }

    $viteBin = "$APP_DIR\node_modules\.bin\vite"
    if (-not (Test-Path "$viteBin.cmd") -and -not (Test-Path $viteBin)) {
        Warn "vite introuvable dans node_modules - lancez 'npm install' manuellement."
    } else {
        # Sur Windows, vite est un .cmd
        $viteCmd = if (Test-Path "$viteBin.cmd") { "$viteBin.cmd" } else { $viteBin }
        & "$nodePath" "$APP_DIR\node_modules\.bin\vite" build
        if ($LASTEXITCODE -ne 0) {
            # Fallback via .cmd direct
            & cmd /c "`"$viteCmd`" build"
        }
        if ($LASTEXITCODE -eq 0) {
            OK "Assets Vite construits"
        } else {
            Warn "Le build Vite a echoue - les anciens assets resteront en place."
        }
    }
}

# ==============================================================================
# ETAPE 5 — Migrations base de donnees
# ==============================================================================
Step "5/8" "Migrations base de donnees"

& php artisan migrate --force
OK "Migrations OK"

# ==============================================================================
# ETAPE 6 — Reconstruction des caches
# ==============================================================================
Step "6/8" "Reconstruction des caches"

& php artisan config:cache
& php artisan route:cache
& php artisan event:cache
# Note: view:cache desactive (mbstring requis, non disponible sur ce serveur)
OK "Caches reconstruits"

# Lien storage (au cas ou il aurait ete perdu)
if (-not (Test-Path "public\storage")) {
    & php artisan storage:link
    OK "Lien storage recree"
}

# ==============================================================================
# ETAPE 7 — Redemarrage des services
# ==============================================================================
Step "7/8" "Redemarrage des services"

Stop-Service -Name "AppMagasinWorker" -Force -ErrorAction SilentlyContinue
Start-Service -Name "AppMagasinWorker" -ErrorAction SilentlyContinue
OK "Queue worker redemarre"

# Redemarrer PHP-FPM pour vider l'OPcache
Restart-Service -Name "AppMagasinPhpFcgi" -ErrorAction SilentlyContinue
OK "PHP-FPM (AppMagasinPhpFcgi) redemarre"

# Redemarrer Nginx
Restart-Service -Name "AppMagasinNginx" -ErrorAction SilentlyContinue
OK "Nginx (AppMagasinNginx) redemarre"

& php artisan up
OK "Application remise en ligne"

# ==============================================================================
# ETAPE 7 — Confirmation de la sauvegarde disponible
# ==============================================================================
Step "8/8" "Verification de la sauvegarde"

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
