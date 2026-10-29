#Requires -RunAsAdministrator
<#
.SYNOPSIS
    Installation complète d'App-Magasin sur Windows 10 Pro 22H2
.DESCRIPTION
    Script d'installation automatique pour configurer IIS, PHP 8.2, Composer, Node.js et déployer l'application
.NOTES
    - Exécuter en tant qu'Administrateur
    - Le code source doit déjà être téléchargé dans le répertoire cible
    - IP du serveur: 10.15.80.229:8080
#>

param(
    [string]$InstallPath = "C:\inetpub\wwwroot\app-magasin",
    [string]$SiteName = "AppMagasin",
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
# ÉTAPE 2: Installation des fonctionnalités IIS
# ═══════════════════════════════════════════════════════════
Write-Step "Installation d'IIS et des modules requis"

$iisFeatures = @(
    "IIS-WebServerRole",
    "IIS-WebServer",
    "IIS-CommonHttpFeatures",
    "IIS-HttpErrors",
    "IIS-ApplicationDevelopment",
    "IIS-HealthAndDiagnostics",
    "IIS-Performance",
    "IIS-Security",
    "IIS-RequestFiltering",
    "IIS-StaticContent",
    "IIS-DefaultDocument",
    "IIS-DirectoryBrowsing",
    "IIS-HttpLogging",
    "IIS-CGI",
    "IIS-ISAPIExtensions",
    "IIS-ISAPIFilter",
    "IIS-ManagementConsole"
)

foreach ($feature in $iisFeatures) {
    $installed = Get-WindowsOptionalFeature -Online -FeatureName $feature -ErrorAction SilentlyContinue
    if ($installed.State -ne "Enabled") {
        Write-Info "Installation de $feature..."
        Enable-WindowsOptionalFeature -Online -FeatureName $feature -All -NoRestart | Out-Null
    }
}

Write-Success "IIS configuré"

# ═══════════════════════════════════════════════════════════
# ÉTAPE 3: Installation de PHP 8.2
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

    $phpIniSettings = @{
        'extension_dir' = '"C:\php\ext"'
        'extension=curl' = 'extension=curl'
        'extension=fileinfo' = 'extension=fileinfo'
        'extension=gd' = 'extension=gd'
        'extension=mbstring' = 'extension=mbstring'
        'extension=openssl' = 'extension=openssl'
        'extension=pdo_sqlite' = 'extension=pdo_sqlite'
        'extension=sqlite3' = 'extension=sqlite3'
        'date.timezone' = 'date.timezone = "America/La_Paz"'
        'upload_max_filesize' = 'upload_max_filesize = 50M'
        'post_max_size' = 'post_max_size = 50M'
        'memory_limit' = 'memory_limit = 256M'
        'max_execution_time' = 'max_execution_time = 300'
    }

    $phpIniContent = Get-Content "$phpPath\php.ini" -Raw
    foreach ($key in $phpIniSettings.Keys) {
        if ($phpIniContent -notmatch [regex]::Escape($phpIniSettings[$key])) {
            Add-Content "$phpPath\php.ini" $phpIniSettings[$key]
        }
    }

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
# ÉTAPE 4: Installation de Composer
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
# ÉTAPE 5: Installation de Node.js et npm
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
# ÉTAPE 6: Configuration IIS pour PHP
# ═══════════════════════════════════════════════════════════
Write-Step "Configuration du gestionnaire FastCGI pour PHP"

Import-Module WebAdministration

# Configuration FastCGI
$fastCgiPath = "C:\php\php-cgi.exe"
$fastCgiConfig = Get-WebConfiguration -Filter "/system.webServer/fastCgi/application[@fullPath='$fastCgiPath']" -PSPath "IIS:\"
if (-not $fastCgiConfig) {
    Add-WebConfiguration -Filter "/system.webServer/fastCgi" -Value @{
        fullPath = $fastCgiPath
        maxInstances = 4
        instanceMaxRequests = 10000
        activityTimeout = 300
        requestTimeout = 300
        queueLength = 1000
    }
    Write-Success "Gestionnaire FastCGI configuré"
}

# ═══════════════════════════════════════════════════════════
# ÉTAPE 7: Création du site IIS
# ═══════════════════════════════════════════════════════════
Write-Step "Configuration du site IIS"

# Arrêt du site par défaut
Stop-WebSite -Name "Default Web Site" -ErrorAction SilentlyContinue

# Suppression du site existant si présent
if (Get-WebSite -Name $SiteName -ErrorAction SilentlyContinue) {
    Remove-WebSite -Name $SiteName
    Write-Info "Site existant supprimé"
}

# Création du pool d'applications
$appPoolName = "${SiteName}Pool"
if (Test-Path "IIS:\AppPools\$appPoolName") {
    Remove-WebAppPool -Name $appPoolName
}

New-WebAppPool -Name $appPoolName
Set-ItemProperty "IIS:\AppPools\$appPoolName" -Name managedRuntimeVersion -Value ""
Set-ItemProperty "IIS:\AppPools\$appPoolName" -Name processModel.identityType -Value 0  # LocalSystem

# Création du site
New-WebSite -Name $SiteName `
    -PhysicalPath "$InstallPath\public" `
    -ApplicationPool $appPoolName `
    -Port $Port `
    -IPAddress "*"

# Configuration du gestionnaire PHP
New-WebHandler -Name "PHP_via_FastCGI" `
    -Path "*.php" `
    -Verb "*" `
    -Modules "FastCgiModule" `
    -ScriptProcessor "$fastCgiPath" `
    -ResourceType File `
    -PSPath "IIS:\Sites\$SiteName" `
    -Force

# Document par défaut
Set-WebConfigurationProperty -Filter "//defaultDocument/files" `
    -PSPath "IIS:\Sites\$SiteName" `
    -Name Collection `
    -Value @{value='index.php'}

# URL Rewrite pour Laravel (via web.config)
$webConfig = @"
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
    <system.webServer>
        <rewrite>
            <rules>
                <rule name="Imported Rule 1" stopProcessing="true">
                    <match url="^(.*)/$" ignoreCase="false" />
                    <conditions>
                        <add input="{REQUEST_FILENAME}" matchType="IsDirectory" ignoreCase="false" negate="true" />
                    </conditions>
                    <action type="Redirect" redirectType="Permanent" url="/{R:1}" />
                </rule>
                <rule name="Imported Rule 2" stopProcessing="true">
                    <match url="^" ignoreCase="false" />
                    <conditions>
                        <add input="{REQUEST_FILENAME}" matchType="IsDirectory" ignoreCase="false" negate="true" />
                        <add input="{REQUEST_FILENAME}" matchType="IsFile" ignoreCase="false" negate="true" />
                    </conditions>
                    <action type="Rewrite" url="index.php" />
                </rule>
            </rules>
        </rewrite>
        <defaultDocument>
            <files>
                <clear />
                <add value="index.php" />
            </files>
        </defaultDocument>
    </system.webServer>
</configuration>
"@

Set-Content -Path "$InstallPath\public\web.config" -Value $webConfig -Force

Write-Success "Site IIS créé et configuré"

# ═══════════════════════════════════════════════════════════
# ÉTAPE 8: Permissions sur les dossiers
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
        $permission = "IIS_IUSRS", "FullControl", "ContainerInherit,ObjectInherit", "None", "Allow"
        $accessRule = New-Object System.Security.AccessControl.FileSystemAccessRule $permission
        $acl.SetAccessRule($accessRule)
        Set-Acl $folder $acl
    }
}

Write-Success "Permissions configurées"

# ═══════════════════════════════════════════════════════════
# ÉTAPE 9: Configuration de l'application Laravel
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
    'DB_DATABASE=.*' = "DB_DATABASE=$InstallPath\database\database.sqlite"
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
# ÉTAPE 10: Installation des dépendances
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
# ÉTAPE 11: Initialisation de Laravel
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
# ÉTAPE 12: Configuration du pare-feu
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
# ÉTAPE 13: Redémarrage d'IIS
# ═══════════════════════════════════════════════════════════
Write-Step "Démarrage des services"

Restart-Service W3SVC -Force
Start-WebSite -Name $SiteName

Write-Success "Site démarré"

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

🔧 Prochaines étapes :
   1. Ouvrez un navigateur et accédez à $AppUrl
   2. Créez votre compte administrateur
   3. Configurez les paramètres SMTP dans le fichier .env si nécessaire
   4. Configurez la synchronisation avec le Raspberry Pi (serveur backup)

📝 Fichiers de configuration :
   - Application : $InstallPath\.env
   - PHP         : C:\php\php.ini
   - IIS         : $InstallPath\public\web.config

"@ -ForegroundColor Green

Write-Info "Pour accéder aux logs IIS : C:\inetpub\logs\LogFiles"
Write-Info "Pour redémarrer l'application : Restart-WebSite -Name '$SiteName'"

Write-Host "`nAppuyez sur une touche pour quitter..." -ForegroundColor Yellow
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
