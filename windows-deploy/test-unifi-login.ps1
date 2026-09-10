# Script de test manuel pour la connexion au portail UniFi
# Peut être exécuté sans droits administrateur

Write-Host "=== Test de connexion au portail UniFi ===" -ForegroundColor Cyan
Write-Host ""

# Test de connectivité Internet
Write-Host "Test de la connexion Internet actuelle..." -ForegroundColor Yellow
try {
    $pingResult = Test-NetConnection -ComputerName "8.8.8.8" -Port 53 -WarningAction SilentlyContinue -ErrorAction SilentlyContinue
    if ($pingResult.TcpTestSucceeded) {
        Write-Host "✓ Internet accessible - Vous êtes déjà connecté" -ForegroundColor Green
        $alreadyConnected = $true
    } else {
        Write-Host "✗ Pas de connexion Internet détectée" -ForegroundColor Red
        $alreadyConnected = $false
    }
} catch {
    Write-Host "✗ Erreur lors du test de connexion" -ForegroundColor Red
    $alreadyConnected = $false
}

Write-Host ""

if (-not $alreadyConnected) {
    Write-Host "Lancement du script de connexion automatique..." -ForegroundColor Yellow
    $scriptPath = "C:\inetpub\wwwroot\app-magasin\windows-deploy\unifi-auto-login.ps1"

    if (Test-Path $scriptPath) {
        & $scriptPath
    } else {
        Write-Host "ERREUR: Script non trouvé à $scriptPath" -ForegroundColor Red
    }
}

Write-Host ""
Write-Host "Affichage des dernières lignes du log:" -ForegroundColor Cyan
$logFile = "C:\inetpub\wwwroot\app-magasin\windows-deploy\unifi-login.log"

if (Test-Path $logFile) {
    Get-Content $logFile -Tail 20
} else {
    Write-Host "Aucun fichier de log trouvé." -ForegroundColor Yellow
}

Write-Host ""
pause
