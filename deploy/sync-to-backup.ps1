#Requires -Version 5.1
<#
.SYNOPSIS
    Script de synchronisation automatique vers serveur backup Raspberry Pi

.DESCRIPTION
    Synchronise la base de donnees SQLite et les fichiers uploades
    depuis le serveur Windows principal vers le serveur backup Raspberry Pi

.PARAMETER BackupServerIP
    Adresse IP du serveur backup (Raspberry Pi)

.PARAMETER BackupUser
    Nom d'utilisateur SSH sur le serveur backup

.EXAMPLE
    .\sync-to-backup.ps1 -BackupServerIP "10.15.11.42" -BackupUser "magasin"
#>

param(
    [Parameter(Mandatory=$true)]
    [string]$BackupServerIP,

    [Parameter(Mandatory=$false)]
    [string]$BackupUser = "magasin",

    [Parameter(Mandatory=$false)]
    [string]$LocalAppPath = "C:\inetpub\wwwroot\app-magasin",

    [Parameter(Mandatory=$false)]
    [string]$RemoteAppPath = "/var/www/app-magasin"
)

# Configuration
$ErrorActionPreference = "Stop"
$LogFile = Join-Path $LocalAppPath "logs\sync-backup-$(Get-Date -Format 'yyyy-MM-dd').log"

# Creer le dossier logs s'il n'existe pas
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

################################################################################
# DEBUT DU SCRIPT
################################################################################

Write-Log "===============================================================" "INFO"
Write-Log "SYNCHRONISATION VERS SERVEUR BACKUP" "INFO"
Write-Log "===============================================================" "INFO"

# Verifier la connectivite au serveur backup
Write-Log "Test de connectivite au serveur $BackupServerIP..."
$ping = Test-Connection -ComputerName $BackupServerIP -Count 2 -Quiet

if (-not $ping) {
    Write-Log "Impossible de joindre le serveur backup $BackupServerIP" "ERROR"
    exit 1
}

Write-Log "Serveur backup accessible" "SUCCESS"

################################################################################
# 1. SYNCHRONISATION BASE DE DONNEES SQLITE
################################################################################

Write-Log "---------------------------------------------------------------" "INFO"
Write-Log "1. Synchronisation base de donnees SQLite" "INFO"

$DatabaseFile = Join-Path $LocalAppPath "database\database.sqlite"
$RemoteDatabasePath = "${RemoteAppPath}/database/database.sqlite"

if (-not (Test-Path $DatabaseFile)) {
    Write-Log "Base de donnees introuvable: $DatabaseFile" "ERROR"
    exit 1
}

$DbSize = (Get-Item $DatabaseFile).Length / 1MB
Write-Log "Taille de la base: $([math]::Round($DbSize, 2)) Mo"

# Creer une sauvegarde temporaire avec horodatage sur le serveur distant
$BackupTimestamp = Get-Date -Format "yyyy-MM-dd_HH-mm-ss"
$RemoteBackupPath = "${RemoteAppPath}/database/backup_${BackupTimestamp}.sqlite"

Write-Log "Creation d'une sauvegarde sur le serveur distant..."
ssh ${BackupUser}@${BackupServerIP} "sudo cp ${RemoteDatabasePath} ${RemoteBackupPath} 2>/dev/null || true"

# Synchroniser la base de donnees
Write-Log "Synchronisation: $DatabaseFile -> ${BackupUser}@${BackupServerIP}:${RemoteDatabasePath}"
scp "$DatabaseFile" "${BackupUser}@${BackupServerIP}:/tmp/database.sqlite"

if ($LASTEXITCODE -eq 0) {
    Write-Log "Fichier copie, installation sur le serveur..." "SUCCESS"

    # Deplacer et corriger permissions
    ssh ${BackupUser}@${BackupServerIP} "sudo cp /tmp/database.sqlite ${RemoteDatabasePath}"
    ssh ${BackupUser}@${BackupServerIP} "sudo chown www-data:www-data ${RemoteDatabasePath}"
    ssh ${BackupUser}@${BackupServerIP} "sudo chmod 664 ${RemoteDatabasePath}"

    Write-Log "Base de donnees synchronisee avec succes" "SUCCESS"
} else {
    Write-Log "Echec de la synchronisation de la base de donnees" "ERROR"
    exit 1
}

################################################################################
# 2. SYNCHRONISATION FICHIER .ENV
################################################################################

Write-Log "---------------------------------------------------------------" "INFO"
Write-Log "2. Synchronisation fichier .env" "INFO"

$EnvFileLocal = Join-Path $LocalAppPath "deploy\env-raspberry.txt"
if (Test-Path $EnvFileLocal) {
    Write-Log "Synchronisation du fichier .env pour Raspberry Pi..."

    scp "$EnvFileLocal" "${BackupUser}@${BackupServerIP}:/tmp/.env"
    ssh ${BackupUser}@${BackupServerIP} "sudo cp /tmp/.env ${RemoteAppPath}/.env"
    ssh ${BackupUser}@${BackupServerIP} "sudo chown www-data:www-data ${RemoteAppPath}/.env"
    ssh ${BackupUser}@${BackupServerIP} "sudo chmod 600 ${RemoteAppPath}/.env"

    Write-Log "Fichier .env synchronise" "SUCCESS"
} else {
    Write-Log "Fichier .env Raspberry introuvable, ignore" "WARN"
}

################################################################################
# 3. OPTIMISATION LARAVEL SUR LE SERVEUR DISTANT
################################################################################

Write-Log "---------------------------------------------------------------" "INFO"
Write-Log "3. Optimisation Laravel sur serveur distant" "INFO"

ssh ${BackupUser}@${BackupServerIP} "cd ${RemoteAppPath} && sudo -u www-data php artisan config:clear"
ssh ${BackupUser}@${BackupServerIP} "cd ${RemoteAppPath} && sudo -u www-data php artisan cache:clear"

Write-Log "Caches Laravel regeneres" "SUCCESS"

################################################################################
# 4. NETTOYAGE DES ANCIENNES SAUVEGARDES
################################################################################

Write-Log "---------------------------------------------------------------" "INFO"
Write-Log "4. Nettoyage des anciennes sauvegardes (>7 jours)" "INFO"

ssh ${BackupUser}@${BackupServerIP} "find ${RemoteAppPath}/database -name 'backup_*.sqlite' -mtime +7 -delete 2>/dev/null || true"

Write-Log "Anciennes sauvegardes supprimees" "SUCCESS"

################################################################################
# RESUME
################################################################################

Write-Log "===============================================================" "INFO"
Write-Log "SYNCHRONISATION TERMINEE AVEC SUCCES" "SUCCESS"
Write-Log "===============================================================" "INFO"

$EndTime = Get-Date
Write-Log "Timestamp: $EndTime" "INFO"
Write-Log "Serveur backup: ${BackupUser}@${BackupServerIP}" "INFO"
Write-Log "Log complet: $LogFile" "INFO"

# Afficher le statut du serveur distant
Write-Log "" "INFO"
Write-Log "Etat du serveur backup:" "INFO"
ssh ${BackupUser}@${BackupServerIP} "df -h ${RemoteAppPath} | tail -n1"

Write-Log "===============================================================" "INFO"

exit 0
