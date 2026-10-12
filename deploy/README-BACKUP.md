# Installation Serveur Backup - App Magasin BTS MAV

## Vue d'ensemble

Ce guide vous permet d'installer un **serveur backup en miroir** de l'application App Magasin sur un **Raspberry Pi 5 avec Ubuntu Server**.

**Architecture finale :**
- **Serveur principal** : Windows Server 2019 + IIS (production)
- **Serveur backup** : Raspberry Pi 5 + Ubuntu + Nginx (miroir automatique)
- **Synchronisation** : automatique toutes les heures depuis Windows vers Raspberry

---

## Prérequis

### Matériel
- ✅ Raspberry Pi 5 avec 8 Go de RAM
- ✅ Carte microSD 32 Go minimum (64 Go recommandé)
- ✅ Alimentation Raspberry Pi officielle
- ✅ Connexion réseau (Ethernet recommandé)

### Logiciels
- ✅ Ubuntu Server 22.04 LTS ou 24.04 LTS (ARM64)
- ✅ Accès SSH au Raspberry Pi
- ✅ OpenSSH Client installé sur Windows Server

---

## PARTIE 1 : Installation sur Raspberry Pi (Ubuntu)

### Étape 1 : Préparer Ubuntu Server sur Raspberry Pi

1. **Télécharger Ubuntu Server** pour Raspberry Pi :
   - Site : https://ubuntu.com/download/raspberry-pi
   - Choisir : Ubuntu Server 24.04 LTS (64-bit)

2. **Flasher la carte microSD** avec Raspberry Pi Imager :
   - Télécharger : https://www.raspberrypi.com/software/
   - Sélectionner l'image Ubuntu Server téléchargée
   - Configurer (via engrenage) :
     - Nom d'utilisateur : `pi` (ou votre choix)
     - Mot de passe : (votre choix sécurisé)
     - Activer SSH
     - Configurer WiFi (optionnel si Ethernet)

3. **Démarrer le Raspberry Pi** :
   - Insérer la carte microSD
   - Brancher Ethernet (recommandé)
   - Brancher l'alimentation
   - Attendre 2-3 minutes le premier démarrage

4. **Se connecter en SSH** :
   ```bash
   ssh pi@<IP_DU_RASPBERRY>
   ```
   Trouver l'IP : vérifier sur votre routeur ou utiliser `arp -a` depuis Windows

---

### Étape 2 : Télécharger le code source depuis GitHub

**Sur le Raspberry Pi** (via SSH) :

```bash
# Se connecter en SSH
ssh pi@<IP_RASPBERRY>

# Installer Git (si pas déjà installé)
sudo apt update
sudo apt install -y git

# Créer le répertoire et télécharger le code source
sudo mkdir -p /var/www
cd /var/www
sudo git clone https://github.com/blackburst971/app-magasin.git

# Vérifier que le téléchargement est complet
ls -la /var/www/app-magasin
```

Vous devez voir les fichiers de l'application : `app/`, `composer.json`, `package.json`, etc.

✅ **Code source téléchargé !**

---

### Étape 3 : Lancer le script d'installation

Le script d'installation est **inclus dans le code source** téléchargé.

**Sur le Raspberry Pi** (toujours en SSH) :

```bash
# Se placer dans le dossier de déploiement
cd /var/www/app-magasin/deploy

# Rendre le script exécutable
chmod +x install-ubuntu-backup.sh

# Lancer l'installation (avec sudo)
sudo ./install-ubuntu-backup.sh
```

Le script va automatiquement :
1. ✅ Vérifier que le code source est présent
2. ✅ Mettre à jour Ubuntu
3. ✅ Installer PHP 8.2 + toutes les extensions requises
4. ✅ Installer Composer
5. ✅ Installer Node.js 20
6. ✅ Installer les dépendances PHP (composer install)
7. ✅ Compiler les assets frontend (npm run build)
8. ✅ Configurer Nginx
9. ✅ Configurer le pare-feu (UFW)
10. ✅ Optimiser PHP-FPM pour Raspberry Pi

**Durée estimée** : 15-30 minutes (selon connexion Internet)

---

### Étape 4 : Configuration post-installation

Une fois l'installation terminée, **sur le Raspberry Pi** :

#### 4.1. Configurer le fichier .env

```bash
sudo nano /var/www/app-magasin/.env
```

Modifier les paramètres suivants :

```env
APP_NAME="App Magasin BTS MAV - BACKUP"
APP_ENV=production
APP_DEBUG=false
APP_URL=http://<IP_RASPBERRY>

# Base de données (déjà configuré par le script)
DB_CONNECTION=sqlite

# Configuration SMTP (même que serveur principal)
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=btspointenoire.magasin@gmail.com
MAIL_PASSWORD=<votre_mot_de_passe_application>
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=btspointenoire.magasin@gmail.com
```

Sauvegarder : `Ctrl+O`, `Entrée`, `Ctrl+X`

#### 4.2. Lancer les migrations

```bash
cd /var/www/app-magasin
sudo -u www-data php artisan migrate --force
```

#### 4.3. Créer le compte administrateur

```bash
sudo -u www-data php artisan db:seed --class=AdminSeeder
```

#### 4.4. Régénérer les caches

```bash
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
```

---

### Étape 5 : Vérifier l'installation

Ouvrir un navigateur et accéder à :
```
http://<IP_RASPBERRY>
```

Vous devriez voir la page de connexion de l'application.

✅ **Le serveur backup est maintenant opérationnel !**

---

## PARTIE 2 : Configuration de la synchronisation automatique (Windows → Raspberry)

### Étape 6 : Configurer SSH sans mot de passe (Windows → Raspberry)

Pour que la synchronisation automatique fonctionne, il faut configurer une clé SSH.

**Sur Windows Server** (PowerShell en tant qu'administrateur) :

```powershell
# Générer une paire de clés SSH (si pas déjà fait)
ssh-keygen -t rsa -b 4096

# Copier la clé publique vers le Raspberry Pi
type $env:USERPROFILE\.ssh\id_rsa.pub | ssh pi@<IP_RASPBERRY> "mkdir -p ~/.ssh && cat >> ~/.ssh/authorized_keys"

# Tester la connexion sans mot de passe
ssh pi@<IP_RASPBERRY>
```

Si vous pouvez vous connecter sans mot de passe, c'est bon ✅

---

### Étape 7 : Configurer la tâche planifiée de synchronisation

**Sur Windows Server** (PowerShell en tant qu'administrateur) :

```powershell
# Se placer dans le dossier deploy
cd C:\inetpub\wwwroot\app-magasin\deploy

# Lancer le script de configuration de la tâche planifiée
.\setup-scheduled-sync.ps1 -BackupServerIP "<IP_RASPBERRY>" -BackupUser "pi"
```

Ce script crée une **tâche planifiée Windows** qui :
- S'exécute **automatiquement toutes les heures**
- Synchronise la base de données SQLite
- Synchronise les fichiers uploadés (images, PDFs, etc.)
- Régénère les caches Laravel sur le Raspberry Pi
- Nettoie les anciennes sauvegardes (>7 jours)

---

### Étape 8 : Tester manuellement la synchronisation

**Sur Windows Server** (PowerShell) :

```powershell
# Tester la synchronisation manuellement
cd C:\inetpub\wwwroot\app-magasin\deploy
.\sync-to-backup.ps1 -BackupServerIP "<IP_RASPBERRY>" -BackupUser "pi"
```

Vérifier les logs :
```powershell
Get-Content C:\inetpub\wwwroot\app-magasin\logs\sync-backup-*.log
```

Ou déclencher la tâche planifiée :
```powershell
Start-ScheduledTask -TaskName "App-Magasin-Backup-Sync"
```

---

## PARTIE 3 : Vérification et maintenance

### Vérifier l'état du serveur backup

**Sur le Raspberry Pi** :

```bash
# Vérifier les services
systemctl status nginx
systemctl status php8.2-fpm

# Vérifier l'espace disque
df -h /var/www/app-magasin

# Vérifier les logs Nginx
sudo tail -f /var/log/nginx/app-magasin-error.log

# Vérifier les logs Laravel
sudo tail -f /var/www/app-magasin/storage/logs/laravel.log

# Vérifier la taille de la base de données
ls -lh /var/www/app-magasin/database/database.sqlite
```

### Monitoring des ressources (Raspberry Pi)

```bash
# Installer htop (si pas déjà fait)
sudo apt install htop

# Surveiller les ressources
htop

# Surveiller la température
vcgencmd measure_temp
```

---

## PARTIE 4 : Basculement en cas de panne du serveur principal

### Scénario : Le serveur Windows tombe en panne

1. **Vérifier que le Raspberry Pi a la dernière version de la base** :
   ```bash
   ls -lh /var/www/app-magasin/database/database.sqlite
   ```

2. **Modifier temporairement l'URL dans .env** (si nécessaire) :
   ```bash
   sudo nano /var/www/app-magasin/.env
   # Modifier APP_URL si besoin
   sudo -u www-data php artisan config:cache
   ```

3. **Rediriger le trafic** :
   - Modifier le DNS ou la passerelle pour pointer vers l'IP du Raspberry Pi
   - Ou informer les utilisateurs de l'adresse temporaire

4. **Le serveur backup fonctionne en autonomie complète** ✅

---

## Commandes utiles

### Sur Windows (PowerShell)

```powershell
# Lancer une synchronisation manuelle
.\deploy\sync-to-backup.ps1 -BackupServerIP "<IP_RASPBERRY>"

# Vérifier la tâche planifiée
Get-ScheduledTask -TaskName "App-Magasin-Backup-Sync"

# Désactiver temporairement la synchronisation
Disable-ScheduledTask -TaskName "App-Magasin-Backup-Sync"

# Réactiver la synchronisation
Enable-ScheduledTask -TaskName "App-Magasin-Backup-Sync"
```

### Sur Raspberry Pi (Ubuntu)

```bash
# Redémarrer les services
sudo systemctl restart nginx
sudo systemctl restart php8.2-fpm

# Vider les caches Laravel
cd /var/www/app-magasin
sudo -u www-data php artisan cache:clear
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache

# Mettre à jour le code depuis GitHub
cd /var/www/app-magasin
sudo -u www-data git pull
sudo -u www-data composer install --no-dev
sudo -u www-data npm install
sudo -u www-data npm run build

# Voir les logs en temps réel
sudo tail -f /var/log/nginx/app-magasin-access.log
sudo tail -f /var/log/nginx/app-magasin-error.log
```

---

## Dépannage

### Problème : "Permission denied" lors de la synchronisation

**Solution** : Vérifier les permissions sur le Raspberry Pi
```bash
sudo chown -R www-data:www-data /var/www/app-magasin
sudo chmod -R 775 /var/www/app-magasin/storage
sudo chmod -R 775 /var/www/app-magasin/bootstrap/cache
```

### Problème : "Connection refused" lors de la synchronisation SSH

**Solution** : Vérifier que SSH est actif sur le Raspberry Pi
```bash
sudo systemctl status ssh
sudo systemctl start ssh
sudo systemctl enable ssh
```

### Problème : Page blanche ou erreur 500

**Solution** : Vérifier les logs et les permissions
```bash
sudo tail -n 50 /var/log/nginx/app-magasin-error.log
sudo tail -n 50 /var/www/app-magasin/storage/logs/laravel.log
sudo chmod -R 775 /var/www/app-magasin/storage
```

### Problème : Base de données non synchronisée

**Solution** : Lancer manuellement la synchronisation et vérifier les logs
```powershell
# Sur Windows
.\deploy\sync-to-backup.ps1 -BackupServerIP "<IP_RASPBERRY>"
Get-Content C:\inetpub\wwwroot\app-magasin\logs\sync-backup-*.log
```

---

## Sécurité

### Recommandations

1. **Firewall (UFW)** : déjà configuré par le script d'installation
   ```bash
   sudo ufw status
   ```

2. **Fail2ban** : protéger contre les attaques brute-force SSH
   ```bash
   sudo apt install fail2ban
   sudo systemctl enable fail2ban
   sudo systemctl start fail2ban
   ```

3. **Certificat SSL** : installer Let's Encrypt (optionnel)
   ```bash
   sudo apt install certbot python3-certbot-nginx
   sudo certbot --nginx -d votre-domaine.com
   ```

4. **Sauvegardes régulières** : les sauvegardes sont conservées 7 jours
   ```bash
   ls -lh /var/www/app-magasin/database/backup_*.sqlite
   ```

---

## Performances attendues sur Raspberry Pi 5

| Métrique | Valeur |
|----------|--------|
| **RAM utilisée** | ~500 Mo (pic à 1 Go) |
| **CPU charge moyenne** | 10-20% |
| **Temps de réponse** | <500 ms |
| **Utilisateurs simultanés** | 20-30 |
| **Espace disque requis** | 2-5 Go (avec base + uploads) |

---

## Architecture réseau recommandée

```
┌─────────────────────────────────────────────────────────┐
│                  Réseau Local BTS MAV                    │
│                                                           │
│  ┌─────────────────┐         ┌──────────────────┐       │
│  │  Serveur        │         │  Serveur         │       │
│  │  Principal      │◄───────►│  Backup          │       │
│  │  Windows        │  Sync   │  Raspberry Pi 5  │       │
│  │  10.15.80.229   │  1h     │  192.168.x.x     │       │
│  └────────┬────────┘         └────────┬─────────┘       │
│           │                           │                  │
│           └───────────┬───────────────┘                  │
│                       │                                  │
│                  ┌────▼─────┐                            │
│                  │  Switch  │                            │
│                  │          │                            │
│                  └────┬─────┘                            │
│                       │                                  │
│                  ┌────▼─────┐                            │
│                  │ Postes   │                            │
│                  │ Clients  │                            │
│                  └──────────┘                            │
└─────────────────────────────────────────────────────────┘
```

---

## Support et aide

Pour toute question ou problème :

1. Consulter les logs :
   - Windows : `C:\inetpub\wwwroot\app-magasin\logs\sync-backup-*.log`
   - Raspberry Pi : `/var/log/nginx/` et `/var/www/app-magasin/storage/logs/`

2. Vérifier la documentation Laravel : https://laravel.com/docs

3. Contacter l'administrateur système

---

**Date de création** : Octobre 2026
**Version** : 1.0
**Auteur** : Équipe BTS MAV Pointe-Noire
