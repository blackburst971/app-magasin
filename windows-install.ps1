#Requires -RunAsAdministrator
<#
.SYNOPSIS
    Installation complète d'App-Magasin sur Windows 10 Pro 22H2
.DESCRIPTION
    Script d'installation automatique pour configurer Nginx, PHP 8.2, Composer, Node.js et déployer l'application
.NOTES
    - Exécuter en tant qu'Administrateur
    - Le code source doit déjà être téléchargé dans le répertoire cible
    - IP du serveur: 10.15.80.229:8080
#>

param(
    [string]$InstallPath = "C:\inetpub\wwwroot\app-magasin",
    [string]$NginxPath = "C:\nginx",
    [int]$Port = 8080,
    [string]$AppUrl = "http://10.15.80.229:8080"
)

$ErrorActionPreference = "Stop"
$ProgressPreference = "SilentlyContinue"

# Couleurs pour les messages
function Write-Success { Write-Host "✓ $args" -ForegroundColor Green }
function Write-Info { Write-Host "ℹ $args" -ForegroundColor Cyan }
function Write-Error-Custom { Write-Host "✗ $args" -ForegroundColor Red }
function Write-Step { Write-Host "`n═══ $args ═══" -ForegroundColor Yellow }

# Vérification administrateur
if (-not ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    Write-Error-Custom "Ce script doit être exécuté en tant qu'Administrateur"
    exit 1
}

Write-Host @"
╔═══════════════════════════════════════════════════════════╗
║     Installation App-Magasin sur Windows 10 Pro 22H2     ║
║                    Serveur Principal                      ║
╚═══════════════════════════════════════════════════════════╝
"@ -ForegroundColor Cyan

Write-Info "Répertoire d'installation: $InstallPath"
Write-Info "URL de l'application: $AppUrl"

# ═══════════════════════════════════════════════════════════
# ÉTAPE 1: Vérification du code source
# ═══════════════════════════════════════════════════════════
Write-Step "Vérification du code source"

if (-not (Test-Path $InstallPath)) {
    Write-Error-Custom "Le répertoire $InstallPath n'existe pas"
    Write-Info "Veuillez télécharger le code source depuis GitHub avant de lancer ce script"
    exit 1
}

if (-not (Test-Path "$InstallPath\artisan")) {
    Write-Error-Custom "Le fichier artisan n'existe pas dans $InstallPath"
    Write-Info "Assurez-vous d'avoir téléchargé tout le code source de l'application"
    exit 1
}

Write-Success "Code source détecté"

# ═══════════════════════════════════════════════════════════
# ÉTAPE 2: Installation de PHP 8.2
# ═══════════════════════════════════════════════════════════
Write-Step "Installation de PHP 8.2"

$phpPath = "C:\php"
$phpVersion = "8.2.27"
$phpZip = "$env:TEMP\php-$phpVersion-nts-Win32-vs16-x64.zip"
$phpUrl = "https://windows.php.net/downloads/releases/php-$phpVersion-nts-Win32-vs16-x64.zip"

if (-not (Test-Path $phpPath)) {
    Write-Info "Téléchargement de PHP $phpVersion..."
    Invoke-WebRequest -Uri $phpUrl -OutFile $phpZip -UseBasicParsing

    Write-Info "Extraction de PHP..."
    New-Item -ItemType Directory -Path $phpPath -Force | Out-Null
    Expand-Archive -Path $phpZip -DestinationPath $phpPath -Force
    Remove-Item $phpZip

    # Configuration php.ini
    Copy-Item "$phpPath\php.ini-production" "$phpPath\php.ini"

    # Configuration de base
    $phpIni = Get-Content "$phpPath\php.ini" -Raw

    # Activer les extensions
    $extensions = @(
        'extension=curl',
        'extension=fileinfo',
        'extension=gd',
        'extension=mbstring',
        'extension=openssl',
        'extension=pdo_sqlite',
        'extension=sqlite3'
    )

    foreach ($ext in $extensions) {
        if ($phpIni -notmatch [regex]::Escape($ext)) {
            Add-Content "$phpPath\php.ini" "`n$ext"
        }
    }

    # Paramètres additionnels
    Add-Content "$phpPath\php.ini" @"

; Configuration pour Laravel
extension_dir = "C:\php\ext"
date.timezone = America/La_Paz
upload_max_filesize = 50M
post_max_size = 50M
memory_limit = 256M
max_execution_time = 300
cgi.fix_pathinfo = 0
"@

    # Ajout au PATH
    $envPath = [Environment]::GetEnvironmentVariable("Path", "Machine")
    if ($envPath -notlike "*$phpPath*") {
        [Environment]::SetEnvironmentVariable("Path", "$envPath;$phpPath", "Machine")
        $env:Path = "$env:Path;$phpPath"
    }

    Write-Success "PHP $phpVersion installé"
} else {
    Write-Success "PHP déjà installé"
}

# ═══════════════════════════════════════════════════════════
# ÉTAPE 3: Installation de Composer
# ═══════════════════════════════════════════════════════════
Write-Step "Installation de Composer"

$composerPath = "$env:ProgramData\ComposerSetup\bin"
if (-not (Test-Path "$composerPath\composer.bat")) {
    Write-Info "Téléchargement de Composer..."
    $composerInstaller = "$env:TEMP\composer-setup.exe"
    Invoke-WebRequest -Uri "https://getcomposer.org/Composer-Setup.exe" -OutFile $composerInstaller -UseBasicParsing

    Write-Info "Installation de Composer..."
    Start-Process -FilePath $composerInstaller -ArgumentList "/VERYSILENT /NORESTART" -Wait
    Remove-Item $composerInstaller

    # Ajout au PATH
    $envPath = [Environment]::GetEnvironmentVariable("Path", "Machine")
    if ($envPath -notlike "*$composerPath*") {
        [Environment]::SetEnvironmentVariable("Path", "$envPath;$composerPath", "Machine")
        $env:Path = "$env:Path;$composerPath"
    }

    Write-Success "Composer installé"
} else {
    Write-Success "Composer déjà installé"
}

# ═══════════════════════════════════════════════════════════
# ÉTAPE 4: Installation de Node.js et npm
# ═══════════════════════════════════════════════════════════
Write-Step "Installation de Node.js"

$nodeVersion = "20.18.1"
$nodeMsi = "$env:TEMP\node-v$nodeVersion-x64.msi"

if (-not (Get-Command node -ErrorAction SilentlyContinue)) {
    Write-Info "Téléchargement de Node.js $nodeVersion..."
    Invoke-WebRequest -Uri "https://nodejs.org/dist/v$nodeVersion/node-v$nodeVersion-x64.msi" -OutFile $nodeMsi -UseBasicParsing

    Write-Info "Installation de Node.js..."
    Start-Process msiexec.exe -ArgumentList "/i `"$nodeMsi`" /quiet /norestart" -Wait
    Remove-Item $nodeMsi

    # Rafraîchir le PATH
    $env:Path = [System.Environment]::GetEnvironmentVariable("Path", "Machine") + ";" + [System.Environment]::GetEnvironmentVariable("Path", "User")

    Write-Success "Node.js installé"
} else {
    Write-Success "Node.js déjà installé"
}

# ═══════════════════════════════════════════════════════════
# ÉTAPE 5: Installation de Nginx
# ═══════════════════════════════════════════════════════════
Write-Step "Installation de Nginx"

$nginxVersion = "1.26.2"
$nginxZip = "$env:TEMP\nginx-$nginxVersion.zip"
$nginxUrl = "http://nginx.org/download/nginx-$nginxVersion.zip"

if (-not (Test-Path $NginxPath)) {
    Write-Info "Téléchargement de Nginx $nginxVersion..."
    Invoke-WebRequest -Uri $nginxUrl -OutFile $nginxZip -UseBasicParsing

    Write-Info "Extraction de Nginx..."
    Expand-Archive -Path $nginxZip -DestinationPath "C:\" -Force
    Rename-Item "C:\nginx-$nginxVersion" $NginxPath
    Remove-Item $nginxZip

    Write-Success "Nginx $nginxVersion installé"
} else {
    Write-Success "Nginx déjà installé"
}

# ═══════════════════════════════════════════════════════════
# ÉTAPE 6: Configuration de Nginx
# ═══════════════════════════════════════════════════════════
Write-Step "Configuration de Nginx"

$nginxConf = @"
worker_processes  1;

events {
    worker_connections  1024;
}

http {
    include       mime.types;
    default_type  application/octet-stream;

    sendfile        on;
    keepalive_timeout  65;

    server {
        listen       $Port;
        server_name  10.15.80.229;

        root   "$($InstallPath -replace '\\', '/')/public";
        index  index.php index.html;

        charset utf-8;

        location / {
            try_files `$uri `$uri/ /index.php?`$query_string;
        }

        location = /favicon.ico { access_log off; log_not_found off; }
        location = /robots.txt  { access_log off; log_not_found off; }

        error_page 404 /index.php;

        location ~ \.php$ {
            fastcgi_pass   127.0.0.1:9000;
            fastcgi_index  index.php;
            fastcgi_param  SCRIPT_FILENAME  `$realpath_root`$fastcgi_script_name;
            include        fastcgi_params;
        }

        location ~ /\.(?!well-known).* {
            deny all;
        }
    }
}
"@

Set-Content -Path "$NginxPath\conf\nginx.conf" -Value $nginxConf -Force
Write-Success "Configuration Nginx créée"

# ═══════════════════════════════════════════════════════════
# ÉTAPE 7: Installation de NSSM (gestionnaire de services)
# ═══════════════════════════════════════════════════════════
Write-Step "Installation de NSSM"

$nssmPath = "C:\nssm"
$nssmZip = "$env:TEMP\nssm-2.24.zip"
$nssmUrl = "https://nssm.cc/release/nssm-2.24.zip"

if (-not (Test-Path "$nssmPath\nssm.exe")) {
    Write-Info "Téléchargement de NSSM..."
    Invoke-WebRequest -Uri $nssmUrl -OutFile $nssmZip -UseBasicParsing

    Write-Info "Extraction de NSSM..."
    Expand-Archive -Path $nssmZip -DestinationPath $env:TEMP -Force
    New-Item -ItemType Directory -Path $nssmPath -Force | Out-Null
    Copy-Item "$env:TEMP\nssm-2.24\win64\nssm.exe" $nssmPath
    Remove-Item $nssmZip
    Remove-Item "$env:TEMP\nssm-2.24" -Recurse

    Write-Success "NSSM installé"
} else {
    Write-Success "NSSM déjà installé"
}

# ═══════════════════════════════════════════════════════════
# ÉTAPE 8: Création des services Windows
# ═══════════════════════════════════════════════════════════
Write-Step "Configuration des services Windows"

# Arrêt des services s'ils existent
Get-Service -Name "nginx" -ErrorAction SilentlyContinue | Stop-Service -Force
Get-Service -Name "php-cgi" -ErrorAction SilentlyContinue | Stop-Service -Force

# Service Nginx
$nginxService = Get-Service -Name "nginx" -ErrorAction SilentlyContinue
if (-not $nginxService) {
    & "$nssmPath\nssm.exe" install nginx "$NginxPath\nginx.exe"
    & "$nssmPath\nssm.exe" set nginx AppDirectory $NginxPath
    & "$nssmPath\nssm.exe" set nginx DisplayName "Nginx Web Server"
    & "$nssmPath\nssm.exe" set nginx Description "Serveur web Nginx pour App-Magasin"
    & "$nssmPath\nssm.exe" set nginx Start SERVICE_AUTO_START
    Write-Success "Service Nginx créé"
}

# Service PHP-CGI
$phpCgiService = Get-Service -Name "php-cgi" -ErrorAction SilentlyContinue
if (-not $phpCgiService) {
    & "$nssmPath\nssm.exe" install php-cgi "$phpPath\php-cgi.exe" "-b 127.0.0.1:9000"
    & "$nssmPath\nssm.exe" set php-cgi AppDirectory $phpPath
    & "$nssmPath\nssm.exe" set php-cgi DisplayName "PHP FastCGI"
    & "$nssmPath\nssm.exe" set php-cgi Description "PHP FastCGI pour App-Magasin"
    & "$nssmPath\nssm.exe" set php-cgi Start SERVICE_AUTO_START
    Write-Success "Service PHP-CGI créé"
}

# ═══════════════════════════════════════════════════════════
# ÉTAPE 9: Permissions sur les dossiers
# ═══════════════════════════════════════════════════════════
Write-Step "Configuration des permissions"

$foldersToSecure = @(
    "$InstallPath\storage",
    "$InstallPath\bootstrap\cache",
    "$InstallPath\database"
)

foreach ($folder in $foldersToSecure) {
    if (Test-Path $folder) {
        $acl = Get-Acl $folder
        $permission = "Everyone", "FullControl", "ContainerInherit,ObjectInherit", "None", "Allow"
        $accessRule = New-Object System.Security.AccessControl.FileSystemAccessRule $permission
        $acl.SetAccessRule($accessRule)
        Set-Acl $folder $acl
    }
}

Write-Success "Permissions configurées"

# ═══════════════════════════════════════════════════════════
# ÉTAPE 10: Configuration de l'application Laravel
# ═══════════════════════════════════════════════════════════
Write-Step "Configuration de Laravel"

Set-Location $InstallPath

# Copie du fichier .env
if (-not (Test-Path ".env")) {
    if (Test-Path ".env.example") {
        Copy-Item ".env.example" ".env"
    } else {
        Write-Error-Custom "Fichier .env.example manquant"
        exit 1
    }
}

# Configuration .env
$envContent = Get-Content ".env" -Raw
$envUpdates = @{
    'APP_URL=.*' = "APP_URL=$AppUrl"
    'APP_ENV=.*' = 'APP_ENV=production'
    'APP_DEBUG=.*' = 'APP_DEBUG=false'
    'DB_CONNECTION=.*' = 'DB_CONNECTION=sqlite'
    'DB_DATABASE=.*' = "DB_DATABASE=$($InstallPath -replace '\\', '/')/database/database.sqlite"
}

foreach ($pattern in $envUpdates.Keys) {
    $envContent = $envContent -replace $pattern, $envUpdates[$pattern]
}

Set-Content ".env" -Value $envContent

# Création de la base de données SQLite
if (-not (Test-Path "database\database.sqlite")) {
    New-Item -ItemType File -Path "database\database.sqlite" -Force | Out-Null
    Write-Success "Base de données SQLite créée"
}

Write-Success "Fichier .env configuré"

# ═══════════════════════════════════════════════════════════
# ÉTAPE 11: Installation des dépendances
# ═══════════════════════════════════════════════════════════
Write-Step "Installation des dépendances PHP"

Write-Info "Exécution de composer install (peut prendre plusieurs minutes)..."
& composer install --no-dev --optimize-autoloader --no-interaction 2>&1 | Out-Null

if ($LASTEXITCODE -ne 0) {
    Write-Error-Custom "Erreur lors de l'installation des dépendances PHP"
    exit 1
}

Write-Success "Dépendances PHP installées"

Write-Step "Installation des dépendances Node.js"

Write-Info "Exécution de npm install (peut prendre plusieurs minutes)..."
& npm install 2>&1 | Out-Null

if ($LASTEXITCODE -ne 0) {
    Write-Error-Custom "Erreur lors de l'installation des dépendances Node.js"
    exit 1
}

Write-Success "Dépendances Node.js installées"

# ═══════════════════════════════════════════════════════════
# ÉTAPE 12: Initialisation de Laravel
# ═══════════════════════════════════════════════════════════
Write-Step "Initialisation de l'application"

# Génération de la clé
& php artisan key:generate --force | Out-Null
Write-Success "Clé d'application générée"

# Migrations
Write-Info "Exécution des migrations..."
& php artisan migrate --force | Out-Null
Write-Success "Migrations exécutées"

# Seeders (si nécessaire)
if (Test-Path "database\seeders\DatabaseSeeder.php") {
    Write-Info "Exécution des seeders..."
    & php artisan db:seed --force | Out-Null
    Write-Success "Seeders exécutés"
}

# Link storage
& php artisan storage:link | Out-Null
Write-Success "Lien symbolique storage créé"

# Build des assets
Write-Info "Compilation des assets frontend (peut prendre plusieurs minutes)..."
& npm run build | Out-Null
Write-Success "Assets compilés"

# Cache optimization
& php artisan config:cache | Out-Null
& php artisan route:cache | Out-Null
& php artisan view:cache | Out-Null
Write-Success "Caches optimisés"

# ═══════════════════════════════════════════════════════════
# ÉTAPE 13: Configuration du pare-feu
# ═══════════════════════════════════════════════════════════
Write-Step "Configuration du pare-feu Windows"

$firewallRule = Get-NetFirewallRule -DisplayName "App-Magasin HTTP" -ErrorAction SilentlyContinue
if (-not $firewallRule) {
    New-NetFirewallRule -DisplayName "App-Magasin HTTP" `
        -Direction Inbound `
        -Protocol TCP `
        -LocalPort $Port `
        -Action Allow `
        -Profile Any | Out-Null
    Write-Success "Règle de pare-feu créée"
} else {
    Write-Success "Règle de pare-feu déjà existante"
}

# ═══════════════════════════════════════════════════════════
# ÉTAPE 14: Démarrage des services
# ═══════════════════════════════════════════════════════════
Write-Step "Démarrage des services"

Start-Service php-cgi
Start-Service nginx

Write-Success "Services démarrés"

# ═══════════════════════════════════════════════════════════
# FIN
# ═══════════════════════════════════════════════════════════

Write-Host @"

╔═══════════════════════════════════════════════════════════╗
║           Installation terminée avec succès !             ║
╚═══════════════════════════════════════════════════════════╝

📍 URL de l'application : $AppUrl
📁 Répertoire           : $InstallPath
🗄️  Base de données      : $InstallPath\database\database.sqlite

🔧 Services Windows :
   - Nginx       : Démarré (port $Port)
   - PHP-CGI     : Démarré (port 9000)

🔧 Prochaines étapes :
   1. Ouvrez un navigateur et accédez à $AppUrl
   2. Créez votre compte administrateur
   3. Configurez les paramètres SMTP dans le fichier .env si nécessaire
   4. Configurez la synchronisation avec le Raspberry Pi (serveur backup)

📝 Fichiers de configuration :
   - Application : $InstallPath\.env
   - PHP         : C:\php\php.ini
   - Nginx       : $NginxPath\conf\nginx.conf

🔄 Gestion des services :
   - Redémarrer Nginx   : Restart-Service nginx
   - Redémarrer PHP-CGI : Restart-Service php-cgi
   - Voir les logs Nginx: Get-Content $NginxPath\logs\error.log -Tail 50

"@ -ForegroundColor Green

Write-Host "`nAppuyez sur une touche pour quitter..." -ForegroundColor Yellow
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
