#Requires -RunAsAdministrator
<#
.SYNOPSIS
    Configure une tâche planifiée Windows pour synchroniser automatiquement vers le serveur backup

.DESCRIPTION
    Crée une tâche planifiée qui exécute le script de synchronisation toutes les heures

.PARAMETER BackupServerIP
    Adresse IP du serveur backup

.EXAMPLE
    .\setup-scheduled-sync.ps1 -BackupServerIP "192.168.1.100"
#>

param(
    [Parameter(Mandatory=$true)]
    [string]$BackupServerIP,

    [Parameter(Mandatory=$false)]
    [string]$BackupUser = "pi",

    [Parameter(Mandatory=$false)]
    [string]$SyncIntervalHours = 1
)

$ScriptPath = "C:\inetpub\wwwroot\app-magasin\deploy\sync-to-backup.ps1"
$TaskName = "App-Magasin-Backup-Sync"

Write-Host "═══════════════════════════════════════════════════════════" -ForegroundColor Blue
Write-Host "Configuration de la synchronisation automatique" -ForegroundColor Green
Write-Host "═══════════════════════════════════════════════════════════" -ForegroundColor Blue
Write-Host ""

# Vérifier que le script existe
if (-not (Test-Path $ScriptPath)) {
    Write-Host "✗ Script de synchronisation introuvable: $ScriptPath" -ForegroundColor Red
    exit 1
}

Write-Host "✓ Script de synchronisation trouvé" -ForegroundColor Green

# Supprimer la tâche existante si elle existe
$existingTask = Get-ScheduledTask -TaskName $TaskName -ErrorAction SilentlyContinue
if ($existingTask) {
    Write-Host "Suppression de la tâche planifiée existante..." -ForegroundColor Yellow
    Unregister-ScheduledTask -TaskName $TaskName -Confirm:$false
}

# Créer l'action (exécuter le script)
$action = New-ScheduledTaskAction -Execute "PowerShell.exe" `
    -Argument "-NoProfile -ExecutionPolicy Bypass -File `"$ScriptPath`" -BackupServerIP `"$BackupServerIP`" -BackupUser `"$BackupUser`""

# Créer le déclencheur (toutes les heures)
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date) -RepetitionInterval (New-TimeSpan -Hours $SyncIntervalHours)

# Créer les paramètres de la tâche
$settings = New-ScheduledTaskSettingsSet `
    -AllowStartIfOnBatteries `
    -DontStopIfGoingOnBatteries `
    -StartWhenAvailable `
    -RunOnlyIfNetworkAvailable `
    -ExecutionTimeLimit (New-TimeSpan -Hours 1)

# Créer le principal (exécuter en tant que SYSTEM)
$principal = New-ScheduledTaskPrincipal -UserId "SYSTEM" -LogonType ServiceAccount -RunLevel Highest

# Enregistrer la tâche planifiée
Register-ScheduledTask -TaskName $TaskName `
    -Action $action `
    -Trigger $trigger `
    -Settings $settings `
    -Principal $principal `
    -Description "Synchronise automatiquement la base de données et les fichiers vers le serveur backup Raspberry Pi toutes les $SyncIntervalHours heure(s)"

Write-Host ""
Write-Host "✓ Tâche planifiée créée avec succès !" -ForegroundColor Green
Write-Host ""
Write-Host "Détails de la tâche :" -ForegroundColor Cyan
Write-Host "  Nom: $TaskName" -ForegroundColor White
Write-Host "  Fréquence: Toutes les $SyncIntervalHours heure(s)" -ForegroundColor White
Write-Host "  Serveur backup: ${BackupUser}@${BackupServerIP}" -ForegroundColor White
Write-Host ""
Write-Host "Pour tester immédiatement la synchronisation :" -ForegroundColor Yellow
Write-Host "  Start-ScheduledTask -TaskName '$TaskName'" -ForegroundColor White
Write-Host ""
Write-Host "Pour désactiver la tâche :" -ForegroundColor Yellow
Write-Host "  Disable-ScheduledTask -TaskName '$TaskName'" -ForegroundColor White
Write-Host ""
Write-Host "Pour supprimer la tâche :" -ForegroundColor Yellow
Write-Host "  Unregister-ScheduledTask -TaskName '$TaskName' -Confirm:`$false" -ForegroundColor White
Write-Host ""
Write-Host "═══════════════════════════════════════════════════════════" -ForegroundColor Blue
