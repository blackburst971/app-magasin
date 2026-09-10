#Requires -RunAsAdministrator
# ==============================================================================
#  App Magasin - Installation Windows Server 2019
#
#  AVANT de lancer ce script :
#    1. Copier le dossier du projet dans :
#       C:\inetpub\wwwroot\app-magasin\
#    2. Ouvrir PowerShell en tant qu'Administrateur
#    3. Se placer dans le dossier du projet :
#       cd C:\inetpub\wwwroot\app-magasin
#    4. Lancer :
#       PowerShell -ExecutionPolicy Bypass -File deploy\windows\install.ps1
# ==============================================================================
Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

# ── Variables a adapter si besoin ─────────────────────────────────────────────
$APP_DIR  = (Get-Location).Path   # Dossier courant = racine du projet
$PHP_DIR  = "C:\php"
$NSSM_DIR = "C:\tools\nssm"
$IP1      = "10.15.9.114"
$IP2      = "10.15.80.229"
$PORT     = 8080                  # Port dedie App Magasin (80 et 3456 deja utilises)
$SITE     = "AppMagasin"
$POOL     = "AppMagasinPool"

# ── Fonctions utilitaires ──────────────────────────────────────────────────────
function Step($n, $msg) { Write-Host "`n[$n] $msg" -ForegroundColor Cyan }
function OK($msg)        { Write-Host "  [OK] $msg" -ForegroundColor Green }
function Warn($msg)      { Write-Host "  [!!] $msg" -ForegroundColor Yellow }
function Fail($msg)      { Write-Host "  [XX] $msg" -ForegroundColor Red; exit 1 }

function Refresh-Path {
    $env:Path = [Environment]::GetEnvironmentVariable("Path", "Machine") + ";" +
                [Environment]::GetEnvironmentVariable("Path", "User")
}

# ==============================================================================
# ETAPE 0 — Validation
# ==============================================================================
Step "0/9" "Validation de l'environnement"

if (-not (Test-Path "$APP_DIR\artisan")) {
    Fail "Le fichier 'artisan' est introuvable dans $APP_DIR. Lancez ce script depuis la racine du projet."
}
OK "Dossier projet : $APP_DIR"

# ==============================================================================
# ETAPE 1 — Chocolatey
# ==============================================================================
Step "1/9" "Chocolatey (gestionnaire de paquets)"

if (-not (Get-Command choco -ErrorAction SilentlyContinue)) {
    Set-ExecutionPolicy Bypass -Scope Process -Force
    [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
    Invoke-Expression ((New-Object Net.WebClient).DownloadString('https://community.chocolatey.org/install.ps1'))
    Refresh-Path
    OK "Chocolatey installe"
} else {
    OK "Chocolatey deja present"
}

# ==============================================================================
# ETAPE 2 — PHP 8.2 NTS (requis pour IIS FastCGI)
# ==============================================================================
Step "2/9" "PHP 8.2 NTS (FastCGI pour IIS)"

if (-not (Test-Path "$PHP_DIR\php.exe")) {
    $phpUrl = "https://windows.php.net/downloads/releases/php-8.2.28-nts-Win32-vs16-x64.zip"
    $phpZip = "$env:TEMP\php82.zip"
    Write-Host "  Telechargement PHP 8.2..."
    Invoke-WebRequest -Uri $phpUrl -OutFile $phpZip -UseBasicParsing
    New-Item -ItemType Directory -Path $PHP_DIR -Force | Out-Null
    Expand-Archive -Path $phpZip -DestinationPath $PHP_DIR -Force
    Remove-Item $phpZip

    # php.ini depuis le template production
    Copy-Item "$PHP_DIR\php.ini-production" "$PHP_DIR\php.ini"

    # Activation des extensions requises par Laravel
    $ini = Get-Content "$PHP_DIR\php.ini"
    $extensions = @(
        "extension=curl",       "extension=fileinfo",  "extension=gd",
        "extension=intl",       "extension=mbstring",  "extension=openssl",
        "extension=pdo_sqlite", "extension=sqlite3",   "extension=xml",
        "extension=zip",        "extension=bcmath",    "extension=ctype",
        "extension=tokenizer"
    )
    foreach ($ext in $extensions) {
        $ini = $ini -replace "^;($ext)", '$1'
    }
    $ini = $ini -replace '^;?(extension_dir\s*=\s*)"ext"', 'extension_dir = "C:\php\ext"'
    $ini = $ini -replace '^;?(date\.timezone\s*=)',         'date.timezone = Europe/Paris'
    $ini = $ini -replace 'upload_max_filesize\s*=\s*\S+',   'upload_max_filesize = 32M'
    $ini = $ini -replace 'post_max_size\s*=\s*\S+',         'post_max_size = 32M'
    $ini = $ini -replace 'memory_limit\s*=\s*\S+',          'memory_limit = 256M'
    $ini = $ini -replace 'max_execution_time\s*=\s*\S+',    'max_execution_time = 300'
    Set-Content "$PHP_DIR\php.ini" $ini

    # Ajout au PATH systeme
    $sysPath = [Environment]::GetEnvironmentVariable("Path", "Machine")
    if ($sysPath -notlike "*$PHP_DIR*") {
        [Environment]::SetEnvironmentVariable("Path", "$sysPath;$PHP_DIR", "Machine")
    }
    Refresh-Path
    OK "PHP 8.2 installe dans $PHP_DIR"
} else {
    OK "PHP 8.2 deja present"
}

# ==============================================================================
# ETAPE 3 — Composer
# ==============================================================================
Step "3/9" "Composer"

if (-not (Get-Command composer -ErrorAction SilentlyContinue)) {
    $composerSetup = "$env:TEMP\ComposerSetup.exe"
    Invoke-WebRequest -Uri "https://getcomposer.org/Composer-Setup.exe" -OutFile $composerSetup -UseBasicParsing
    Start-Process -FilePath $composerSetup -ArgumentList "/VERYSILENT /SUPPRESSMSGBOXES /PHP=$PHP_DIR\php.exe" -Wait
    Remove-Item $composerSetup
    Refresh-Path
    OK "Composer installe"
} else {
    OK "Composer deja present"
}

# ==============================================================================
# ETAPE 4 — Node.js 20 LTS
# ==============================================================================
Step "4/9" "Node.js 20 LTS"

if (-not (Get-Command node -ErrorAction SilentlyContinue)) {
    choco install nodejs-lts --version=20.19.0 -y --no-progress
    Refresh-Path
    OK "Node.js installe"
} else {
    OK "Node.js deja present ($(node --version))"
}

# ==============================================================================
# ETAPE 5 — NSSM (gestionnaire de services Windows)
# ==============================================================================
Step "5/9" "NSSM"

if (-not (Test-Path "$NSSM_DIR\nssm.exe")) {
    choco install nssm -y --no-progress
    Refresh-Path
    $nssmChoco = "C:\ProgramData\chocolatey\bin\nssm.exe"
    if (Test-Path $nssmChoco) {
        New-Item -ItemType Directory -Path $NSSM_DIR -Force | Out-Null
        Copy-Item $nssmChoco "$NSSM_DIR\nssm.exe"
    }
    OK "NSSM installe"
} else {
    OK "NSSM deja present"
}
$NSSM = if (Test-Path "$NSSM_DIR\nssm.exe") { "$NSSM_DIR\nssm.exe" } else { "nssm" }

# ==============================================================================
# ETAPE 6 — IIS + modules + URL Rewrite
# ==============================================================================
Step "6/9" "IIS et modules requis"

$features = @(
    "IIS-WebServerRole", "IIS-WebServer", "IIS-CommonHttpFeatures",
    "IIS-StaticContent", "IIS-DefaultDocument",
    "IIS-HttpErrors", "IIS-ApplicationDevelopment",
    "IIS-CGI",
    "IIS-ISAPIExtensions", "IIS-ISAPIFilter",
    "IIS-HttpLogging", "IIS-LoggingLibraries",
    "IIS-Security", "IIS-RequestFiltering",
    "IIS-HttpCompressionStatic", "IIS-HttpCompressionDynamic",
    "IIS-ManagementConsole"
)
foreach ($f in $features) {
    $state = (Get-WindowsOptionalFeature -Online -FeatureName $f -ErrorAction SilentlyContinue).State
    if ($state -ne "Enabled") {
        Enable-WindowsOptionalFeature -Online -FeatureName $f -All -NoRestart | Out-Null
        Write-Host "  Active : $f"
    }
}

Import-Module WebAdministration -ErrorAction SilentlyContinue

# URL Rewrite 2.1 (requis pour les routes Laravel via .htaccess/web.config)
$urlRewriteInstalled = Get-ItemProperty "HKLM:\SOFTWARE\Microsoft\IIS Extensions\URL Rewrite" -ErrorAction SilentlyContinue
if (-not $urlRewriteInstalled) {
    $urlRewriteMsi = "$env:TEMP\rewrite_amd64.msi"
    Write-Host "  Telechargement URL Rewrite 2.1..."
    Invoke-WebRequest -Uri "https://download.microsoft.com/download/1/2/8/128E2E22-C1B9-44A4-BE2A-5859ED1D4592/rewrite_amd64_en-US.msi" `
        -OutFile $urlRewriteMsi -UseBasicParsing
    Start-Process msiexec -ArgumentList "/i `"$urlRewriteMsi`" /quiet /norestart" -Wait
    Remove-Item $urlRewriteMsi
    OK "URL Rewrite 2.1 installe"
} else {
    OK "URL Rewrite deja present"
}
OK "IIS OK"

# ==============================================================================
# ETAPE 7 — Dependances PHP/JS + initialisation Laravel
# ==============================================================================
Step "7/9" "Dependances et initialisation Laravel"

Set-Location $APP_DIR

# .env depuis le template si absent
if (-not (Test-Path "$APP_DIR\.env")) {
    Copy-Item "$APP_DIR\deploy\windows\.env.production" "$APP_DIR\.env"
    OK ".env cree depuis le template"
} else {
    Warn ".env deja present - non ecrase (editez-le si besoin)"
}

# Dossiers obligatoires
New-Item -ItemType Directory -Path "$APP_DIR\storage\logs" -Force | Out-Null
New-Item -ItemType Directory -Path "$APP_DIR\database"     -Force | Out-Null

Write-Host "  composer install..."
& composer install --no-dev --optimize-autoloader --no-interaction --quiet

Write-Host "  npm ci + vite build..."
& npm ci --silent
& npm run build

# Cle application
if (-not (Select-String -Path ".env" -Pattern "APP_KEY=base64:" -Quiet)) {
    & php artisan key:generate --force
    OK "APP_KEY generee"
}

# Base SQLite
if (-not (Test-Path "database\database.sqlite")) {
    New-Item -ItemType File -Path "database\database.sqlite" -Force | Out-Null
    OK "Fichier SQLite cree"
}

& php artisan package:discover --ansi
& php artisan migrate --force --seed
OK "Base de donnees initialisee"

& php artisan config:cache
& php artisan route:cache
& php artisan view:cache
& php artisan event:cache
OK "Caches Laravel generes"

# Lien storage (jonction Windows)
if (-not (Test-Path "public\storage")) {
    & php artisan storage:link
    OK "Lien storage cree"
} else {
    Warn "Lien storage deja present"
}

# ==============================================================================
# ETAPE 8 — Permissions IIS_IUSRS
# ==============================================================================
Step "8/9" "Permissions IIS_IUSRS"

foreach ($dir in @("$APP_DIR\storage", "$APP_DIR\bootstrap\cache", "$APP_DIR\public\storage")) {
    if (Test-Path $dir) {
        $acl  = Get-Acl $dir
        $rule = New-Object System.Security.AccessControl.FileSystemAccessRule(
            "IIS_IUSRS", "Modify", "ContainerInherit,ObjectInherit", "None", "Allow"
        )
        $acl.SetAccessRule($rule)
        Set-Acl $dir $acl
    }
}
OK "Permissions appliquees sur storage, bootstrap/cache, public/storage"

# ==============================================================================
# ETAPE 9 — IIS : FastCGI, AppPool, Site, Queue Worker, Planificateur
# ==============================================================================
Step "9/9" "Configuration IIS et services"

Import-Module WebAdministration -ErrorAction Stop

# --- PHP FastCGI ---
$phpExe = "$PHP_DIR\php-cgi.exe"
$existingFcgi = Get-WebConfiguration "system.webServer/fastCgi/application[@fullPath='$phpExe']" `
    -PSPath "IIS:\" -ErrorAction SilentlyContinue
if (-not $existingFcgi) {
    Add-WebConfiguration -Filter "system.webServer/fastCgi" -PSPath "IIS:\" -Value @{
        fullPath        = $phpExe
        maxInstances    = 10
        idleTimeout     = 300
        activityTimeout = 90
        queueLength     = 1000
    }
    OK "PHP FastCGI enregistre dans IIS"
} else {
    OK "PHP FastCGI deja enregistre"
}

if (-not (Get-WebHandler -Name "PHP_via_FastCGI" -ErrorAction SilentlyContinue)) {
    New-WebHandler -Name "PHP_via_FastCGI" -Path "*.php" -Verb "*" `
        -Modules "FastCgiModule" -ScriptProcessor $phpExe -ResourceType "File"
    OK "Handler PHP enregistre"
}

# Compression
Set-WebConfigurationProperty -Filter "system.webServer/urlCompression" `
    -PSPath "IIS:\" -Name "doDynamicCompression" -Value $true
Set-WebConfigurationProperty -Filter "system.webServer/urlCompression" `
    -PSPath "IIS:\" -Name "doStaticCompression"  -Value $true

# --- Application Pool (No Managed Code) ---
if (-not (Test-Path "IIS:\AppPools\$POOL")) {
    New-WebAppPool -Name $POOL
    OK "AppPool '$POOL' cree"
} else {
    OK "AppPool '$POOL' deja present"
}
Set-ItemProperty "IIS:\AppPools\$POOL" -Name managedRuntimeVersion         -Value ""
Set-ItemProperty "IIS:\AppPools\$POOL" -Name processModel.identityType     -Value 4
Set-ItemProperty "IIS:\AppPools\$POOL" -Name recycling.periodicRestart.time -Value "00:00:00"
OK "AppPool configure (No Managed Code, ApplicationPoolIdentity)"

# --- Site IIS sur port $PORT (ne touche PAS aux sites existants) ---
if (-not (Test-Path "IIS:\Sites\$SITE")) {
    New-Website -Name $SITE `
        -PhysicalPath "$APP_DIR\public" `
        -ApplicationPool $POOL `
        -IPAddress $IP1 `
        -Port $PORT `
        -Force
    OK "Site '$SITE' cree sur ${IP1}:${PORT}"
} else {
    Set-ItemProperty "IIS:\Sites\$SITE" -Name physicalPath    -Value "$APP_DIR\public"
    Set-ItemProperty "IIS:\Sites\$SITE" -Name applicationPool -Value $POOL
    OK "Site '$SITE' mis a jour"
}

# Binding 2eme IP
$bindings = Get-WebBinding -Name $SITE
$has2ndIP = $bindings | Where-Object { $_.bindingInformation -like "*${IP2}:${PORT}*" }
if (-not $has2ndIP) {
    New-WebBinding -Name $SITE -IPAddress $IP2 -Port $PORT -Protocol "http"
    OK "Binding ajoute : ${IP2}:${PORT}"
} else {
    OK "Binding ${IP2}:${PORT} deja present"
}

Start-Website -Name $SITE
OK "Site IIS demarre"

# --- Queue Worker via NSSM ---
$workerSvc = "AppMagasinWorker"
$existingSvc = Get-Service -Name $workerSvc -ErrorAction SilentlyContinue
if ($existingSvc) {
    Stop-Service -Name $workerSvc -Force -ErrorAction SilentlyContinue
    & $NSSM remove $workerSvc confirm
}
& $NSSM install  $workerSvc "$PHP_DIR\php.exe"
& $NSSM set      $workerSvc AppParameters   "$APP_DIR\artisan queue:work database --sleep=3 --tries=3 --max-time=3600"
& $NSSM set      $workerSvc AppDirectory     $APP_DIR
& $NSSM set      $workerSvc DisplayName     "App Magasin - Queue Worker"
& $NSSM set      $workerSvc Description     "Jobs Laravel : notifications, emails, alertes stock"
& $NSSM set      $workerSvc Start            SERVICE_AUTO_START
& $NSSM set      $workerSvc AppStdout       "$APP_DIR\storage\logs\worker-stdout.log"
& $NSSM set      $workerSvc AppStderr       "$APP_DIR\storage\logs\worker-stderr.log"
& $NSSM set      $workerSvc AppRotateFiles   1
& $NSSM set      $workerSvc AppRotateBytes   10485760
Start-Service -Name $workerSvc
OK "Service queue worker demarre"

# --- Planificateur Laravel (artisan schedule:run toutes les minutes) ---
$taskName = "AppMagasin-Scheduler"
Unregister-ScheduledTask -TaskName $taskName -Confirm:$false -ErrorAction SilentlyContinue

$action  = New-ScheduledTaskAction `
    -Execute          "$PHP_DIR\php.exe" `
    -Argument         "$APP_DIR\artisan schedule:run" `
    -WorkingDirectory $APP_DIR

$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date) `
    -RepetitionInterval (New-TimeSpan -Minutes 1) `
    -RepetitionDuration ([TimeSpan]::MaxValue)

$settings = New-ScheduledTaskSettingsSet `
    -ExecutionTimeLimit (New-TimeSpan -Minutes 1) `
    -MultipleInstances  IgnoreNew `
    -RestartCount       3 `
    -RestartInterval    (New-TimeSpan -Minutes 1)

Register-ScheduledTask `
    -TaskName $taskName `
    -Action   $action `
    -Trigger  $trigger `
    -Settings $settings `
    -RunLevel Highest `
    -Force | Out-Null
OK "Planificateur Laravel configure (toutes les minutes)"

# ==============================================================================
# RESUME FINAL
# ==============================================================================
Write-Host ""
Write-Host "==========================================================" -ForegroundColor Green
Write-Host "  Installation terminee avec succes !"                      -ForegroundColor Green
Write-Host "==========================================================" -ForegroundColor Green
Write-Host ""
Write-Host "  Acces a l'application :"                                  -ForegroundColor White
Write-Host "    http://${IP1}:${PORT}"                                  -ForegroundColor Yellow
Write-Host "    http://${IP2}:${PORT}"                                  -ForegroundColor Yellow
Write-Host ""
Write-Host "  Les sites existants (port 80 et 3456) ne sont PAS modifies." -ForegroundColor DarkGray
Write-Host ""
Write-Host "  Compte administrateur par defaut :"                       -ForegroundColor White
Write-Host "    Email    : admin@lycee.fr"
Write-Host "    Password : Admin@1234"
Write-Host ""
Write-Host "  Chemins importants :"                                     -ForegroundColor White
Write-Host "    Application     : $APP_DIR"
Write-Host "    Logs            : $APP_DIR\storage\logs\"
Write-Host "    Base de donnees : $APP_DIR\database\database.sqlite"
Write-Host ""
Write-Host "  Services actifs :"                                        -ForegroundColor White
Write-Host "    [OK] IIS site $SITE (AppPool $POOL, port ${PORT})"
Write-Host "    [OK] Queue worker (service AppMagasinWorker)"
Write-Host "    [OK] Planificateur artisan schedule:run"
Write-Host ""
Write-Host "  Prochaines etapes :"                                      -ForegroundColor White
Write-Host "    1. Editer $APP_DIR\.env (MAIL_*, APP_URL si besoin)"
Write-Host "    2. Ouvrir http://${IP1}:${PORT} dans un navigateur"
Write-Host "    3. Se connecter avec admin@lycee.fr / Admin@1234"
Write-Host "    4. Changer le mot de passe dans Parametres"
Write-Host ""
