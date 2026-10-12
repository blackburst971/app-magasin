# Guide de démarrage rapide - Serveur Backup Raspberry Pi

## ⚡ Installation en 4 étapes

### 📋 Avant de commencer

**Ce dont vous avez besoin :**
- ✅ Raspberry Pi 5 (8 Go RAM) avec Ubuntu Server installé
- ✅ Connexion réseau (IP du Raspberry Pi)
- ✅ Accès SSH au Raspberry Pi
- ✅ OpenSSH Client sur Windows Server

---

## Étape 1️⃣ : Télécharger le code source sur Raspberry Pi

**Sur le Raspberry Pi** (via SSH) :

```bash
# Se connecter
ssh pi@<IP_RASPBERRY>

# Installer Git si nécessaire
sudo apt update && sudo apt install -y git

# Télécharger le code source depuis GitHub
sudo mkdir -p /var/www
cd /var/www
sudo git clone https://github.com/blackburst971/app-magasin.git

# Vérifier
ls -la /var/www/app-magasin
```

✅ **Code source téléchargé !**

---

## Étape 2️⃣ : Lancer l'installation automatique

**Sur le Raspberry Pi** (toujours en SSH) :

```bash
# Se placer dans le dossier de déploiement
cd /var/www/app-magasin/deploy

# Rendre le script exécutable
chmod +x install-ubuntu-backup.sh

# Lancer l'installation (TOUT est automatique !)
sudo ./install-ubuntu-backup.sh
```

Le script va installer :
- ✅ PHP 8.2 + toutes extensions requises
- ✅ Composer
- ✅ Node.js 20
- ✅ Nginx (serveur web)
- ✅ Toutes les dépendances PHP (composer install)
- ✅ Compilation assets frontend (npm run build)
- ✅ Configuration Nginx et pare-feu

⏱️ **Durée : 15-30 minutes**

---

## Étape 3️⃣ : Configurer l'application

**Sur le Raspberry Pi** :

```bash
# Modifier .env
sudo nano /var/www/app-magasin/.env

# Ajuster ces paramètres :
# - APP_URL=http://<IP_RASPBERRY>
# - MAIL_USERNAME, MAIL_PASSWORD (même config que serveur principal)

# Lancer les migrations
cd /var/www/app-magasin
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan db:seed --class=AdminSeeder
```

**Tester dans le navigateur :**
```
http://<IP_RASPBERRY>
```

✅ **Le serveur backup est en ligne !**

---

## Étape 4️⃣ : Configurer SSH sans mot de passe

**Sur Windows** (PowerShell administrateur) :

```powershell
# Générer une clé SSH (si pas déjà fait)
ssh-keygen -t rsa -b 4096

# Copier vers Raspberry Pi
type $env:USERPROFILE\.ssh\id_rsa.pub | ssh pi@<IP_RASPBERRY> "mkdir -p ~/.ssh && cat >> ~/.ssh/authorized_keys"

# Tester
ssh pi@<IP_RASPBERRY>  # Doit se connecter sans mot de passe
```

---

## Étape 5️⃣ : Activer la synchronisation automatique

**Sur Windows** (PowerShell administrateur) :

```powershell
cd C:\inetpub\wwwroot\app-magasin\deploy
.\setup-scheduled-sync.ps1 -BackupServerIP "<IP_RASPBERRY>" -BackupUser "pi"
```

✅ **Synchronisation automatique toutes les heures configurée !**

---

## 🧪 Tester la synchronisation

**Depuis Windows** :
```powershell
cd C:\inetpub\wwwroot\app-magasin\deploy
.\sync-to-backup.ps1 -BackupServerIP "<IP_RASPBERRY>" -BackupUser "pi"
```

**Vérifier les logs** :
```powershell
Get-Content C:\inetpub\wwwroot\app-magasin\logs\sync-backup-*.log
```

---

## 📊 Vérifications

### Sur le Raspberry Pi

```bash
# Services actifs
systemctl status nginx
systemctl status php8.2-fpm

# Espace disque
df -h /var/www/app-magasin

# Base de données
ls -lh /var/www/app-magasin/database/database.sqlite
```

### Sur Windows

```powershell
# Statut tâche planifiée
Get-ScheduledTask -TaskName "App-Magasin-Backup-Sync"

# Déclencher manuellement
Start-ScheduledTask -TaskName "App-Magasin-Backup-Sync"
```

---

## 🆘 Problèmes courants

| Problème | Solution |
|----------|----------|
| **Permission denied (SSH)** | Vérifier les clés SSH : `ssh pi@<IP> "cat ~/.ssh/authorized_keys"` |
| **Page blanche Raspberry** | Vérifier permissions : `sudo chmod -R 775 /var/www/app-magasin/storage` |
| **Sync échoue** | Vérifier connectivité : `ping <IP_RASPBERRY>` |
| **Nginx erreur** | Voir logs : `sudo tail /var/log/nginx/app-magasin-error.log` |

---

## 📚 Documentation complète

Pour plus de détails, consulter :
```
C:\inetpub\wwwroot\app-magasin\deploy\README-BACKUP.md
```

---

## ✅ Checklist finale

- [ ] Script d'installation exécuté avec succès
- [ ] Application accessible sur `http://<IP_RASPBERRY>`
- [ ] Connexion SSH sans mot de passe fonctionne
- [ ] Tâche planifiée créée
- [ ] Synchronisation testée manuellement
- [ ] Services Nginx et PHP-FPM actifs
- [ ] Logs vérifiés

---

**🎉 Votre serveur backup est opérationnel !**

Le serveur Windows synchronise automatiquement toutes les heures vers le Raspberry Pi.
En cas de panne du serveur principal, le Raspberry Pi peut prendre le relais immédiatement.
