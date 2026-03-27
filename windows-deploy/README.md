# App Magasin Lycée — Déploiement Windows Server 2019

## Vue d'ensemble
Ce dossier contient tous les fichiers nécessaires pour déployer l'application sur **Windows Server 2019** avec **IIS + PHP + MySQL**.

## Prérequis
- Windows Server 2019 (Standard ou Datacenter)
- Droits Administrateur
- Connexion Internet pour le téléchargement des composants
- PowerShell 5.1+ (inclus avec Windows Server 2019)

## Méthode rapide (recommandée) — Script PowerShell automatique

```powershell
# Ouvrir PowerShell en tant qu'Administrateur
# Se placer dans le dossier du projet
cd C:\inetpub\wwwroot\app-magasin

# Exécuter le script d'installation
Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser
.\windows-deploy\setup-windows.ps1
```

## Architecture cible

```
Windows Server 2019
├── IIS 10 (serveur web)
│   └── Site: app-magasin (port 80/443)
│       └── web.config (URL rewriting Laravel)
├── PHP 8.2 (via PHP Manager for IIS)
│   └── Extensions: pdo_mysql, gd, zip, mbstring, fileinfo, openssl
├── MySQL 8.0 (ou MariaDB 10.11)
│   └── Base: app_magasin
├── Composer (gestionnaire dépendances PHP)
└── Node.js 20 LTS (build assets)
```

## Installation manuelle étape par étape

### 1. Activer IIS
```powershell
# Dans PowerShell Admin
.\windows-deploy\setup-windows.ps1 -StepOnly iis
```

### 2. Installer PHP 8.2
- Télécharger PHP 8.2 NTS x64 sur https://windows.php.net/download/
- Extraire dans `C:\PHP82\`
- Copier `php.ini.example` → `php.ini` (voir `windows-deploy\php.ini.recommended`)
- Ajouter `C:\PHP82` au PATH système

### 3. Installer MySQL 8.0
- Télécharger MySQL Installer: https://dev.mysql.com/downloads/installer/
- Créer la base: `CREATE DATABASE app_magasin CHARACTER SET utf8mb4;`
- Créer l'utilisateur: `CREATE USER 'appmagasin'@'localhost' IDENTIFIED BY 'VotreMotDePasse';`
- Droits: `GRANT ALL ON app_magasin.* TO 'appmagasin'@'localhost';`

### 4. Configurer IIS
- Copier `web.config` dans le dossier `public\` du projet
- Créer le site IIS pointant vers `C:\inetpub\wwwroot\app-magasin\public`
- Activer le module URL Rewrite (installé par le script)

### 5. Déployer l'application
```cmd
cd C:\inetpub\wwwroot\app-magasin
copy .env.example .env
# Éditer .env avec vos paramètres DB et email
composer install --no-dev --optimize-autoloader
npm install && npm run build
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
```

## Configuration .env pour Windows Server

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=http://votre-serveur-ou-ip

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=app_magasin
DB_USERNAME=appmagasin
DB_PASSWORD=VotreMotDePasse

MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=votre-email@gmail.com
MAIL_PASSWORD=xxxx-xxxx-xxxx-xxxx
MAIL_ENCRYPTION=tls
```

## Tâches planifiées (optionnel)
Pour les jobs Laravel en arrière-plan :
```cmd
# Créer une tâche planifiée Windows
schtasks /create /tn "LaravelScheduler" /tr "php C:\inetpub\wwwroot\app-magasin\artisan schedule:run" /sc minute /mo 1
```

## Ports et Pare-feu
```powershell
# Ouvrir le port 80
netsh advfirewall firewall add rule name="HTTP" dir=in action=allow protocol=TCP localport=80
# Ouvrir le port 443 (HTTPS)
netsh advfirewall firewall add rule name="HTTPS" dir=in action=allow protocol=TCP localport=443
```

## Dépannage

| Erreur | Solution |
|--------|----------|
| 500 Internal Server Error | Vérifier les logs: `storage\logs\laravel.log` |
| 403 Forbidden | Vérifier permissions IIS sur le dossier `public\` |
| Blank page | Activer APP_DEBUG=true temporairement |
| DB Connection failed | Vérifier MySQL service + credentials dans .env |
| PHP non trouvé | Vérifier PATH + redémarrer IIS: `iisreset` |
| URL Rewriting ne fonctionne pas | Installer le module IIS URL Rewrite |
