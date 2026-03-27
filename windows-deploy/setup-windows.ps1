#Requires -RunAsAdministrator
<#
.SYNOPSIS
    Installation automatique de App Magasin Lycée sur Windows Server 2019
.DESCRIPTION
    Ce script installe et configure IIS, PHP 8.2, MySQL, Composer, Node.js
    et déploie l'application Laravel automatiquement.
.PARAMETER AppPath
    Chemin de l'application (défaut: C:\inetpub\wwwroot\app-magasin)
.PARAMETER DBPassword
    Mot de passe MySQL pour l'application
.PARAMETER SkipMySQL
    Ignorer l'installation de MySQL (si déjà installé)
#>
param(
    [string]$AppPath = "C:\inetpub\wwwroot\app-magasin",
    [string]$DBPassword = "AppMagasin@2024!",
    [string]$DBName = "app_magasin",
    [string]$DBUser = "appmagasin",
    [switch]$SkipMySQL,
    [switch]$SkipNode
)

$ErrorActionPreference = "Stop"
$PHP_VERSION = "8.2.28"
$PHP_PATH = "C:\PHP82"
$MYSQL_PATH = "C:\MySQL80"
$NODE_VERSION = "20.11.1"

function Write-Step {
    param([string]$Message, [string]$Color = "Cyan")
    Write-Host "`n==> $Message" -ForegroundColor $Color
}

function Test-CommandExists {
    param([string]$Command)
    return (Get-Command $Command -ErrorAction SilentlyContinue) -ne $null
}

Write-Host @"
╔══════════════════════════════════════════╗
║   App Magasin Lycée - Setup Windows      ║
║   Windows Server 2019 + IIS + PHP + MySQL║
╚══════════════════════════════════════════╝
"@ -ForegroundColor Cyan

# === STEP 1: IIS ===
Write-Step "Installation de IIS et modules requis..."
$features = @(
    "Web-Server", "Web-Common-Http", "Web-Static-Content",
    "Web-Default-Doc", "Web-Http-Errors", "Web-Http-Redirect",
    "Web-ISAPI-Ext", "Web-ISAPI-Filter", "Web-CGI",
    "Web-Http-Logging", "Web-Log-Libraries", "Web-Url-Auth",
    "Web-Windows-Auth", "Web-Filtering", "Web-Mgmt-Console"
)
foreach ($feature in $features) {
    $state = Get-WindowsFeature -Name $feature
    if (-not $state.Installed) {
        Install-WindowsFeature -Name $feature -IncludeManagementTools | Out-Null
        Write-Host "  [OK] $feature installé" -ForegroundColor Green
    } else {
        Write-Host "  --> $feature déjà installé" -ForegroundColor Gray
    }
}

# === STEP 2: URL Rewrite Module ===
Write-Step "Installation du module URL Rewrite pour IIS..."
$urlRewriteMsi = "$env:TEMP\rewrite_amd64_en-US.msi"
if (-not (Test-Path "C:\Windows\System32\inetsrv\rewrite.dll")) {
    Write-Host "  Téléchargement URL Rewrite..." -ForegroundColor Yellow
    Invoke-WebRequest -Uri "https://download.microsoft.com/download/1/2/8/128E2E22-C1B9-44A4-BE2A-5859ED1D4592/rewrite_amd64_en-US.msi" `
        -OutFile $urlRewriteMsi -UseBasicParsing
    Start-Process msiexec.exe -ArgumentList "/i `"$urlRewriteMsi`" /quiet /norestart" -Wait
    Write-Host "  [OK] URL Rewrite installé" -ForegroundColor Green
} else {
    Write-Host "  --> URL Rewrite déjà installé" -ForegroundColor Gray
}

# === STEP 3: PHP 8.2 ===
Write-Step "Installation de PHP 8.2..."
if (-not (Test-Path "$PHP_PATH\php.exe")) {
    New-Item -ItemType Directory -Force -Path $PHP_PATH | Out-Null
    $phpZip = "$env:TEMP\php-$PHP_VERSION-nts-Win32-vs16-x64.zip"
    Write-Host "  Téléchargement PHP $PHP_VERSION..." -ForegroundColor Yellow
    Invoke-WebRequest -Uri "https://windows.php.net/downloads/releases/php-$PHP_VERSION-nts-Win32-vs16-x64.zip" `
        -OutFile $phpZip -UseBasicParsing
    Expand-Archive -Path $phpZip -DestinationPath $PHP_PATH -Force
    Write-Host "  [OK] PHP extrait dans $PHP_PATH" -ForegroundColor Green
} else {
    Write-Host "  --> PHP déjà installé dans $PHP_PATH" -ForegroundColor Gray
}

# Configure PHP
$phpIniSrc = "$AppPath\windows-deploy\php.ini.recommended"
$phpIniDst = "$PHP_PATH\php.ini"
if (Test-Path $phpIniSrc) {
    Copy-Item $phpIniSrc $phpIniDst -Force
    Write-Host "  [OK] php.ini configuré" -ForegroundColor Green
} elseif (Test-Path "$PHP_PATH\php.ini-production") {
    Copy-Item "$PHP_PATH\php.ini-production" $phpIniDst
    # Enable required extensions
    $phpIni = Get-Content $phpIniDst
    $extensions = @("extension=pdo_mysql","extension=mbstring","extension=openssl","extension=gd","extension=zip","extension=fileinfo","extension=intl","extension=sodium","extension=curl")
    foreach ($ext in $extensions) {
        $phpIni = $phpIni -replace ";$ext", $ext
    }
    $phpIni | Set-Content $phpIniDst
    Write-Host "  [OK] php.ini configuré depuis php.ini-production" -ForegroundColor Green
}

# Add PHP to PATH
$currentPath = [Environment]::GetEnvironmentVariable("Path", "Machine")
if ($currentPath -notlike "*$PHP_PATH*") {
    [Environment]::SetEnvironmentVariable("Path", "$currentPath;$PHP_PATH", "Machine")
    $env:PATH = "$env:PATH;$PHP_PATH"
    Write-Host "  [OK] PHP ajouté au PATH" -ForegroundColor Green
}

# Configure IIS FastCGI for PHP
Write-Step "Configuration de IIS pour PHP (FastCGI)..."
Import-Module WebAdministration -ErrorAction SilentlyContinue
$phpCgiPath = "$PHP_PATH\php-cgi.exe"
$existingHandler = Get-WebConfiguration "system.webServer/fastCgi/application[@fullPath='$phpCgiPath']" -PSPath "MACHINE/WEBROOT/APPHOST"
if (-not $existingHandler) {
    Add-WebConfiguration -Filter "system.webServer/fastCgi" -PSPath "MACHINE/WEBROOT/APPHOST" `
        -Value @{fullPath = $phpCgiPath; instanceMaxRequests = 10000}
    Write-Host "  [OK] FastCGI PHP configuré dans IIS" -ForegroundColor Green
}

# === STEP 4: MySQL (optional) ===
if (-not $SkipMySQL) {
    Write-Step "Installation de MySQL 8.0..."
    if (-not (Test-CommandExists "mysql")) {
        $mysqlZip = "$env:TEMP\mysql-8.0.zip"
        Write-Host "  Téléchargement MySQL 8.0..." -ForegroundColor Yellow
        Invoke-WebRequest -Uri "https://dev.mysql.com/get/Downloads/MySQL-8.0/mysql-8.0.36-winx64.zip" `
            -OutFile $mysqlZip -UseBasicParsing
        Expand-Archive -Path $mysqlZip -DestinationPath "C:\" -Force
        Rename-Item "C:\mysql-8.0.36-winx64" $MYSQL_PATH -ErrorAction SilentlyContinue
        # Add to PATH
        $currentPath = [Environment]::GetEnvironmentVariable("Path", "Machine")
        [Environment]::SetEnvironmentVariable("Path", "$currentPath;$MYSQL_PATH\bin", "Machine")
        $env:PATH = "$env:PATH;$MYSQL_PATH\bin"
        # Initialize MySQL
        & "$MYSQL_PATH\bin\mysqld.exe" --initialize-insecure --user=mysql --basedir="$MYSQL_PATH" --datadir="$MYSQL_PATH\data"
        # Install as service
        & "$MYSQL_PATH\bin\mysqld.exe" --install MySQL80 --defaults-file="$MYSQL_PATH\my.ini"
        Start-Service MySQL80
        Write-Host "  [OK] MySQL installé et démarré" -ForegroundColor Green
        # Create database and user
        Start-Sleep -Seconds 3
        & "$MYSQL_PATH\bin\mysql.exe" -u root -e "CREATE DATABASE IF NOT EXISTS $DBName CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
        & "$MYSQL_PATH\bin\mysql.exe" -u root -e "CREATE USER IF NOT EXISTS '$DBUser'@'localhost' IDENTIFIED BY '$DBPassword';"
        & "$MYSQL_PATH\bin\mysql.exe" -u root -e "GRANT ALL PRIVILEGES ON ${DBName}.* TO '$DBUser'@'localhost'; FLUSH PRIVILEGES;"
        Write-Host "  [OK] Base de données $DBName créée" -ForegroundColor Green
    } else {
        Write-Host "  --> MySQL/MariaDB déjà disponible" -ForegroundColor Gray
    }
}

# === STEP 5: Composer ===
Write-Step "Installation de Composer..."
if (-not (Test-CommandExists "composer")) {
    $composerSetup = "$env:TEMP\composer-setup.php"
    Invoke-WebRequest -Uri "https://getcomposer.org/installer" -OutFile $composerSetup -UseBasicParsing
    & php $composerSetup --install-dir="$PHP_PATH" --filename=composer
    $currentPath = [Environment]::GetEnvironmentVariable("Path", "Machine")
    if ($currentPath -notlike "*$PHP_PATH*") {
        [Environment]::SetEnvironmentVariable("Path", "$currentPath;$PHP_PATH", "Machine")
    }
    Write-Host "  [OK] Composer installé" -ForegroundColor Green
} else {
    Write-Host "  --> Composer déjà installé" -ForegroundColor Gray
}

# === STEP 6: Node.js ===
if (-not $SkipNode) {
    Write-Step "Installation de Node.js $NODE_VERSION..."
    if (-not (Test-CommandExists "node")) {
        $nodeMsi = "$env:TEMP\node-v$NODE_VERSION-x64.msi"
        Invoke-WebRequest -Uri "https://nodejs.org/dist/v$NODE_VERSION/node-v$NODE_VERSION-x64.msi" `
            -OutFile $nodeMsi -UseBasicParsing
        Start-Process msiexec.exe -ArgumentList "/i `"$nodeMsi`" /quiet /norestart" -Wait
        Write-Host "  [OK] Node.js installé" -ForegroundColor Green
    } else {
        Write-Host "  --> Node.js déjà installé" -ForegroundColor Gray
    }
}

# === STEP 7: Create IIS Website ===
Write-Step "Création du site IIS..."
Import-Module WebAdministration
$siteName = "AppMagasin"
$physicalPath = "$AppPath\public"

if (-not (Test-Path $physicalPath)) {
    New-Item -ItemType Directory -Force -Path $physicalPath | Out-Null
}

if (-not (Get-Website -Name $siteName -ErrorAction SilentlyContinue)) {
    New-Website -Name $siteName -PhysicalPath $physicalPath -Port 80 -Force
    Write-Host "  [OK] Site IIS '$siteName' créé" -ForegroundColor Green
} else {
    Set-ItemProperty "IIS:\Sites\$siteName" -Name physicalPath -Value $physicalPath
    Write-Host "  --> Site IIS '$siteName' mis à jour" -ForegroundColor Gray
}

# Set application pool to use PHP
$poolName = "AppMagasinPool"
if (-not (Get-WebConfiguration "system.applicationHost/applicationPools/add[@name='$poolName']")) {
    New-WebAppPool -Name $poolName
    Set-ItemProperty "IIS:\AppPools\$poolName" managedRuntimeVersion ""
    Set-ItemProperty "IIS:\Sites\$siteName" applicationPool $poolName
    Write-Host "  [OK] Application pool configuré" -ForegroundColor Green
}

# Set permissions on storage and bootstrap/cache
$paths = @("$AppPath\storage", "$AppPath\bootstrap\cache")
foreach ($p in $paths) {
    if (Test-Path $p) {
        icacls $p /grant "IIS_IUSRS:(OI)(CI)F" /T /Q
        Write-Host "  [OK] Permissions IIS sur $p" -ForegroundColor Green
    }
}

# === STEP 8: Configure .env ===
Write-Step "Configuration du fichier .env..."
if (-not (Test-Path "$AppPath\.env")) {
    if (Test-Path "$AppPath\.env.example") {
        Copy-Item "$AppPath\.env.example" "$AppPath\.env"
        # Update database settings
        (Get-Content "$AppPath\.env") `
            -replace "DB_DATABASE=.*", "DB_DATABASE=$DBName" `
            -replace "DB_USERNAME=.*", "DB_USERNAME=$DBUser" `
            -replace "DB_PASSWORD=.*", "DB_PASSWORD=$DBPassword" `
            -replace "APP_ENV=.*", "APP_ENV=production" `
            -replace "APP_DEBUG=.*", "APP_DEBUG=false" |
            Set-Content "$AppPath\.env"
        Write-Host "  [OK] .env créé depuis .env.example" -ForegroundColor Green
    }
}

# === STEP 9: Install dependencies and setup app ===
Write-Step "Installation des dépendances Laravel..."
Set-Location $AppPath
try {
    & composer install --no-dev --optimize-autoloader 2>&1 | Tee-Object -FilePath "$AppPath\storage\logs\install.log"
    Write-Host "  [OK] Dépendances PHP installées" -ForegroundColor Green
} catch {
    Write-Host "  [ERREUR] Composer: $_" -ForegroundColor Red
}

Write-Step "Build des assets frontend..."
try {
    & npm install 2>&1 | Out-Null
    & npm run build 2>&1 | Out-Null
    Write-Host "  [OK] Assets compilés" -ForegroundColor Green
} catch {
    Write-Host "  [ERREUR] npm: $_" -ForegroundColor Red
}

Write-Step "Configuration Laravel..."
& php artisan key:generate --ansi
& php artisan migrate --seed --force
& php artisan storage:link
& php artisan config:cache
& php artisan route:cache
& php artisan view:cache

# === STEP 10: Windows Firewall ===
Write-Step "Configuration du pare-feu Windows..."
New-NetFirewallRule -DisplayName "HTTP (App Magasin)" -Direction Inbound -Protocol TCP -LocalPort 80 -Action Allow -ErrorAction SilentlyContinue
New-NetFirewallRule -DisplayName "HTTPS (App Magasin)" -Direction Inbound -Protocol TCP -LocalPort 443 -Action Allow -ErrorAction SilentlyContinue
Write-Host "  [OK] Ports 80 et 443 ouverts" -ForegroundColor Green

# === DONE ===
Write-Host @"

╔══════════════════════════════════════════════════════════╗
║  INSTALLATION TERMINEE                                   ║
╠══════════════════════════════════════════════════════════╣
║  URL:      http://localhost                              ║
║  Admin:    admin@lycee.fr / Admin@1234                   ║
║  Demo:     user@lycee.fr / password                      ║
╠══════════════════════════════════════════════════════════╣
║  PROCHAINES ETAPES:                                      ║
║  1. Editer .env -> MAIL_USERNAME + MAIL_PASSWORD Gmail   ║
║  2. Changer les mots de passe par defaut                 ║
║  3. Configurer HTTPS si necessaire                       ║
╚══════════════════════════════════════════════════════════╝
"@ -ForegroundColor Green

Restart-Service W3SVC
Write-Host "IIS redémarré. Application disponible sur http://localhost" -ForegroundColor Cyan
