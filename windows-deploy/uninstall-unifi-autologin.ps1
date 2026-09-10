# Désinstallation de la tâche planifiée UniFi Auto-Login
# Doit être exécuté en tant qu'Administrateur

# Vérifier les droits administrateur
$currentPrincipal = New-Object Security.Principal.WindowsPrincipal([Security.Principal.WindowsIdentity]::GetCurrent())
if (-not $currentPrincipal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    Write-Host "ERREUR: Ce script doit être exécuté en tant qu'Administrateur!" -ForegroundColor Red
    pause
    exit 1
}

Write-Host "=== Désinstallation de l'auto-connexion UniFi ===" -ForegroundColor Cyan

$taskName = "UniFi Auto-Login"

# Vérifier si la tâche existe
$task = Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue

if ($task) {
    Write-Host "Tâche trouvée. Suppression en cours..." -ForegroundColor Yellow
    Unregister-ScheduledTask -TaskName $taskName -Confirm:$false
    Write-Host "✓ Tâche supprimée avec succès!" -ForegroundColor Green
} else {
    Write-Host "La tâche '$taskName' n'existe pas." -ForegroundColor Yellow
}

Write-Host "`nDésinstallation terminée." -ForegroundColor White
pause
