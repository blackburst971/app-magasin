#Requires -RunAsAdministrator
# ==============================================================================
#  App Magasin - Initialisation Git sur le serveur (UNE SEULE FOIS)
#
#  Ce script configure le depot Git sur le serveur pour les prochaines
#  mises a jour via git pull.
#
#  AVANT de lancer :
#    1. Installer Git for Windows si pas encore fait :
#       https://git-scm.com/download/win
#    2. Remplacer VOTRE_URL_GITHUB ci-dessous par l'URL de votre depot
#    3. Ouvrir PowerShell en Administrateur depuis la racine du projet
#    4. Lancer : PowerShell -ExecutionPolicy Bypass -File deploy\windows\git-init.ps1
# ==============================================================================

# !! MODIFIEZ CETTE URL AVEC VOTRE DEPOT GITHUB !!
$GITHUB_URL = "https://github.com/VOTRE_NOM/app-magasin.git"

# ==============================================================================
Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"
$APP_DIR = (Get-Location).Path

function Step($n, $msg) { Write-Host "`n[$n] $msg" -ForegroundColor Cyan }
function OK($msg)        { Write-Host "  [OK] $msg" -ForegroundColor Green }
function Warn($msg)      { Write-Host "  [!!] $msg" -ForegroundColor Yellow }
function Fail($msg)      { Write-Host "  [XX] $msg" -ForegroundColor Red; exit 1 }

if (-not (Test-Path "$APP_DIR\artisan")) {
    Fail "Lancez depuis la racine du projet."
}
if ($GITHUB_URL -like "*VOTRE_NOM*") {
    Fail "Modifiez la variable GITHUB_URL dans ce script avant de le lancer."
}

Write-Host ""
Write-Host "==========================================" -ForegroundColor Cyan
Write-Host "  App Magasin - Init Git sur le serveur" -ForegroundColor Cyan
Write-Host "==========================================" -ForegroundColor Cyan

# ==============================================================================
Step "1/4" "Verification de Git"
# ==============================================================================
$_gitObj = Get-Command git -ErrorAction SilentlyContinue
$gitCmd  = if ($_gitObj) { $_gitObj.Source } else { $null }
if (-not $gitCmd) {
    $candidates = @("C:\Program Files\Git\bin\git.exe", "C:\Program Files (x86)\Git\bin\git.exe")
    foreach ($c in $candidates) { if (Test-Path $c) { $gitCmd = $c; break } }
}
if (-not $gitCmd) {
    Fail "Git n'est pas installe. Telechargez-le sur https://git-scm.com/download/win"
}
OK "Git trouve : $gitCmd"

# ==============================================================================
Step "2/4" "Sauvegarde du fichier .env"
# ==============================================================================
# Le .env contient les secrets de production, il ne sera JAMAIS ecrase par git
if (Test-Path ".env") {
    Copy-Item ".env" ".env.server.backup" -Force
    OK ".env sauvegarde dans .env.server.backup"
} else {
    Warn "Aucun .env trouve - assurez-vous qu'il existe avant de faire la mise a jour"
}

# ==============================================================================
Step "3/4" "Arret IIS + initialisation du depot Git"
# ==============================================================================

# IIS verrouille certains fichiers (ex: public/index.php), il faut l'arreter
# avant que git puisse ecraser les fichiers existants.
$POOL = "AppMagasinPool"
Import-Module WebAdministration -ErrorAction SilentlyContinue
if (Test-Path "IIS:\AppPools\$POOL") {
    Stop-WebAppPool -Name $POOL -ErrorAction SilentlyContinue
    Start-Sleep -Seconds 2
    OK "Pool IIS '$POOL' arrete"
} else {
    Warn "Pool IIS '$POOL' introuvable - poursuite sans arret IIS"
}

if (Test-Path ".git") {
    Warn "Un depot Git existe deja. Mise a jour de l'origine."
    & git remote set-url origin $GITHUB_URL
} else {
    & git init
    & git remote add origin $GITHUB_URL
    OK "Depot Git initialise"
}

OK "Remote configure : $GITHUB_URL"

# ==============================================================================
Step "4/4" "Premier git pull (synchronisation des fichiers)"
# ==============================================================================
Warn "Connexion a GitHub..."

& git fetch origin main
if ($LASTEXITCODE -ne 0) {
    # Redemarrer IIS avant de quitter
    Start-WebAppPool -Name $POOL -ErrorAction SilentlyContinue
    Fail "git fetch a echoue."
}

# reset --hard : ecrase les fichiers existants par la version GitHub
# IIS est arrete donc les fichiers ne sont plus verrouilles
& git reset --hard origin/main
if ($LASTEXITCODE -ne 0) {
    Start-WebAppPool -Name $POOL -ErrorAction SilentlyContinue
    Fail "git reset a echoue. Verifiez les permissions sur le dossier."
}

# .env est dans .gitignore : git ne le touche jamais
if (Test-Path ".env.server.backup") {
    if (-not (Test-Path ".env")) {
        Copy-Item ".env.server.backup" ".env" -Force
        OK ".env restaure depuis la sauvegarde"
    } else {
        OK ".env present - non modifie"
    }
}

# Redemarrer IIS
if (Test-Path "IIS:\AppPools\$POOL") {
    Start-WebAppPool -Name $POOL
    OK "Pool IIS '$POOL' redemarre"
}

$hash = & git rev-parse --short HEAD
OK "Code synchronise ! Commit actuel : $hash"

# ==============================================================================
Write-Host ""
Write-Host "==========================================" -ForegroundColor Green
Write-Host "  Git initialise avec succes !" -ForegroundColor Green
Write-Host "==========================================" -ForegroundColor Green
Write-Host ""
Write-Host "  Pour les prochaines mises a jour, lancez simplement :" -ForegroundColor Yellow
Write-Host "  PowerShell -ExecutionPolicy Bypass -File deploy\windows\update.ps1" -ForegroundColor White
Write-Host ""
