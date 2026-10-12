#Requires -Version 5.1
<#
.SYNOPSIS
    Script de synchronisation automatique vers serveur backup Ubuntu

.DESCRIPTION
    Synchronise la base de données SQLite et les fichiers uploadés
    depuis le serveur Windows principal vers le serveur backup Raspberry Pi

.PARAMETER BackupServerIP
    Adresse IP du serveur backup (Raspberry Pi)

.PARAMETER BackupUser
    Nom d'utilisateur SSH sur le serveur backup (par défaut: pi)

.EXAMPLE
    .\sync-to-backup.ps1 -BackupServerIP "192.168.1.100" -BackupUser "pi"
#>

param(
    [Parameter(Mandatory=$true)]
    [string]$BackupServerIP,

    [Parameter(Mandatory=$false)]
    [string]$BackupUser = "pi",

    [Parameter(Mandatory=$false)]
    [string]$LocalAppPath = "C:\inetpub\wwwroot\app-magasin",

    [Parameter(Mandatory=$false)]
    [string]$RemoteAppPath = "/var/www/app-magasin"
)

# Configuration
$ErrorActionPreference = "Stop"
$LogFile = Join-Path $LocalAppPath "logs\sync-backup-$(Get-Date -Format 'yyyy-MM-dd').log"

# Créer le dossier logs s'il n'existe pas
$LogDir = Join-Path $LocalAppPath "logs"
if (-not (Test-Path $LogDir)) {
    New-Item -ItemType Directory -Path $LogDir -Force | Out-Null
}

# Fonction de logging
function Write-Log {
    param([string]$Message, [string]$Level = "INFO")

    $Timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    $LogMessage = "[$Timestamp] [$Level] $Message"

    Write-Host $LogMessage
    Add-Content -Path $LogFile -Value $LogMessage
}

# Fonction pour vérifier si SCP/SSH est disponible
function Test-SSHAvailable {
    try {
        $scp = Get-Command scp -ErrorAction SilentlyContinue
        $ssh = Get-Command ssh -ErrorAction SilentlyContinue
        return ($null -ne $scp -and $null -ne $ssh)
    } catch {
        return $false
    }
}

# Fonction pour synchroniser un fichier
function Sync-FileToBackup {
    param(
        [string]$LocalFile,
        [string]$RemoteDestination
    )

    try {
        Write-Log "Synchronisation: $LocalFile -> ${BackupUser}@${BackupServerIP}:${RemoteDestination}"

        # Utiliser SCP pour copier le fichier
        $scpCommand = "scp `"$LocalFile`" ${BackupUser}@${BackupServerIP}:${RemoteDestination}"

        $result = Invoke-Expression $scpCommand 2>&1

        if ($LASTEXITCODE -eq 0) {
            Write-Log "✓ Fichier synchronisé avec succès" "SUCCESS"
            return $true
        } else {
            Write-Log "✗ Erreur lors de la synchronisation: $result" "ERROR"
            return $false
        }
    } catch {
        Write-Log "✗ Exception: $_" "ERROR"
        return $false
    }
}

# Fonction pour exécuter une commande SSH sur le serveur distant
function Invoke-RemoteCommand {
    param([string]$Command)

    try {
        Write-Log "Exécution distante: $Command"

        $sshCommand = "ssh ${BackupUser}@${BackupServerIP} `"$Command`""
        $result = Invoke-Expression $sshCommand 2>&1

        if ($LASTEXITCODE -eq 0) {
            Write-Log "✓ Commande exécutée avec succès" "SUCCESS"
            return $result
        } else {
            Write-Log "✗ Erreur: $result" "ERROR"
            return $null
        }
    } catch {
        Write-Log "✗ Exception: $_" "ERROR"
        return $null
    }
}

################################################################################
# DÉBUT DU SCRIPT
################################################################################

Write-Log "═══════════════════════════════════════════════════════════" "INFO"
Write-Log "SYNCHRONISATION VERS SERVEUR BACKUP" "INFO"
Write-Log "═══════════════════════════════════════════════════════════" "INFO"

# Vérifier que SSH/SCP est disponible
if (-not (Test-SSHAvailable)) {
    Write-Log "✗ SSH/SCP non disponible. Installez OpenSSH Client sur Windows" "ERROR"
    Write-Log "  Paramètres > Applications > Fonctionnalités facultatives > OpenSSH Client" "ERROR"
    exit 1
}

Write-Log "✓ SSH/SCP disponible" "SUCCESS"

# Vérifier la connectivité au serveur backup
Write-Log "Test de connectivité au serveur $BackupServerIP..."
$ping = Test-Connection -ComputerName $BackupServerIP -Count 2 -Quiet

if (-not $ping) {
    Write-Log "✗ Impossible de joindre le serveur backup $BackupServerIP" "ERROR"
    exit 1
}

Write-Log "✓ Serveur backup accessible" "SUCCESS"

################################################################################
# 1. SYNCHRONISATION BASE DE DONNÉES SQLITE
################################################################################

Write-Log "─────────────────────────────────────────────────────────" "INFO"
Write-Log "1. Synchronisation base de données SQLite" "INFO"

$DatabaseFile = Join-Path $LocalAppPath "database\database.sqlite"
$RemoteDatabasePath = "${RemoteAppPath}/database/database.sqlite"

if (-not (Test-Path $DatabaseFile)) {
    Write-Log "✗ Base de données introuvable: $DatabaseFile" "ERROR"
    exit 1
}

$DbSize = (Get-Item $DatabaseFile).Length / 1MB
Write-Log "Taille de la base: $([math]::Round($DbSize, 2)) Mo"

# Créer une sauvegarde temporaire avec horodatage sur le serveur distant
$BackupTimestamp = Get-Date -Format "yyyy-MM-dd_HH-mm-ss"
$RemoteBackupPath = "${RemoteAppPath}/database/backup_${BackupTimestamp}.sqlite"

Write-Log "Création d'une sauvegarde sur le serveur distant..."
Invoke-RemoteCommand "sudo cp ${RemoteDatabasePath} ${RemoteBackupPath} 2>/dev/null || true"

# Synchroniser la base de données
$dbSync = Sync-FileToBackup -LocalFile $DatabaseFile -RemoteDestination $RemoteDatabasePath

if (-not $dbSync) {
    Write-Log "✗ Échec de la synchronisation de la base de données" "ERROR"
    exit 1
}

# Corriger les permissions
Invoke-RemoteCommand "sudo chown www-data:www-data ${RemoteDatabasePath}"
Invoke-RemoteCommand "sudo chmod 664 ${RemoteDatabasePath}"

Write-Log "✓ Base de données synchronisée avec succès" "SUCCESS"

################################################################################
# 2. SYNCHRONISATION DES FICHIERS UPLOADÉS (storage)
################################################################################

Write-Log "─────────────────────────────────────────────────────────" "INFO"
Write-Log "2. Synchronisation des fichiers uploadés" "INFO"

$StoragePath = Join-Path $LocalAppPath "storage\app\public"
$RemoteStoragePath = "${RemoteAppPath}/storage/app/public/"

if (Test-Path $StoragePath) {
    Write-Log "Synchronisation du dossier storage/app/public..."

    # Compter les fichiers à synchroniser
    $FileCount = (Get-ChildItem -Path $StoragePath -Recurse -File | Measure-Object).Count
    Write-Log "Nombre de fichiers: $FileCount"

    if ($FileCount -gt 0) {
        # Utiliser rsync si disponible, sinon utiliser robocopy + scp
        $rsync = Get-Command rsync -ErrorAction SilentlyContinue

        if ($null -ne $rsync) {
            Write-Log "Utilisation de rsync pour la synchronisation..."
            $rsyncCmd = "rsync -avz --delete `"$StoragePath/`" ${BackupUser}@${BackupServerIP}:${RemoteStoragePath}"
            Invoke-Expression $rsyncCmd
        } else {
            Write-Log "Rsync non disponible, utilisation de SCP..." "WARN"
            Write-Log "Pour de meilleures performances, installez WSL avec rsync" "WARN"

            # Fallback: copier les fichiers un par un (lent)
            Get-ChildItem -Path $StoragePath -Recurse -File | ForEach-Object {
                $relativePath = $_.FullName.Substring($StoragePath.Length).TrimStart('\')
                $remoteFile = "${RemoteStoragePath}${relativePath}" -replace '\\', '/'
                Sync-FileToBackup -LocalFile $_.FullName -RemoteDestination $remoteFile
            }
        }

        # Corriger les permissions
        Invoke-RemoteCommand "sudo chown -R www-data:www-data ${RemoteStoragePath}"
        Invoke-RemoteCommand "sudo chmod -R 775 ${RemoteStoragePath}"

        Write-Log "✓ Fichiers uploadés synchronisés" "SUCCESS"
    } else {
        Write-Log "Aucun fichier à synchroniser dans storage" "WARN"
    }
} else {
    Write-Log "Dossier storage introuvable, ignoré" "WARN"
}

################################################################################
# 3. SYNCHRONISATION FICHIER .ENV (optionnel)
################################################################################

Write-Log "─────────────────────────────────────────────────────────" "INFO"
Write-Log "3. Vérification configuration .env" "INFO"

$EnvFile = Join-Path $LocalAppPath ".env"
if (Test-Path $EnvFile) {
    Write-Log "Fichier .env trouvé (non synchronisé pour sécurité)" "INFO"
    Write-Log "Si vous souhaitez synchroniser .env, décommentez le code ci-dessous" "WARN"

    # Décommenter pour synchroniser .env (ATTENTION: contient des secrets)
    # $RemoteEnvPath = "${RemoteAppPath}/.env"
    # Sync-FileToBackup -LocalFile $EnvFile -RemoteDestination $RemoteEnvPath
    # Invoke-RemoteCommand "sudo chown www-data:www-data ${RemoteEnvPath}"
    # Invoke-RemoteCommand "sudo chmod 600 ${RemoteEnvPath}"
}

################################################################################
# 4. OPTIMISATION LARAVEL SUR LE SERVEUR DISTANT
################################################################################

Write-Log "─────────────────────────────────────────────────────────" "INFO"
Write-Log "4. Optimisation Laravel sur serveur distant" "INFO"

Invoke-RemoteCommand "cd ${RemoteAppPath} && sudo -u www-data php artisan cache:clear"
Invoke-RemoteCommand "cd ${RemoteAppPath} && sudo -u www-data php artisan config:cache"
Invoke-RemoteCommand "cd ${RemoteAppPath} && sudo -u www-data php artisan route:cache"
Invoke-RemoteCommand "cd ${RemoteAppPath} && sudo -u www-data php artisan view:cache"

Write-Log "✓ Caches Laravel régénérés" "SUCCESS"

################################################################################
# 5. NETTOYAGE DES ANCIENNES SAUVEGARDES
################################################################################

Write-Log "─────────────────────────────────────────────────────────" "INFO"
Write-Log "5. Nettoyage des anciennes sauvegardes (>7 jours)" "INFO"

Invoke-RemoteCommand "find ${RemoteAppPath}/database -name 'backup_*.sqlite' -mtime +7 -delete"

Write-Log "✓ Anciennes sauvegardes supprimées" "SUCCESS"

################################################################################
# RÉSUMÉ
################################################################################

Write-Log "═══════════════════════════════════════════════════════════" "INFO"
Write-Log "SYNCHRONISATION TERMINÉE AVEC SUCCÈS" "SUCCESS"
Write-Log "═══════════════════════════════════════════════════════════" "INFO"

$EndTime = Get-Date
Write-Log "Timestamp: $EndTime" "INFO"
Write-Log "Serveur backup: ${BackupUser}@${BackupServerIP}" "INFO"
Write-Log "Log complet: $LogFile" "INFO"

# Afficher le statut du serveur distant
Write-Log "" "INFO"
Write-Log "État du serveur backup:" "INFO"
Invoke-RemoteCommand "df -h ${RemoteAppPath} | tail -n1"
Invoke-RemoteCommand "systemctl is-active nginx"
Invoke-RemoteCommand "systemctl is-active php8.2-fpm"

Write-Log "═══════════════════════════════════════════════════════════" "INFO"

exit 0
