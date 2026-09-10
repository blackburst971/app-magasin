# Installation de la tache planifiee pour la connexion automatique au portail UniFi
# Doit etre execute en tant qu'Administrateur

# Verifier les droits administrateur
$currentPrincipal = New-Object Security.Principal.WindowsPrincipal([Security.Principal.WindowsIdentity]::GetCurrent())
if (-not $currentPrincipal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    Write-Host "ERREUR: Ce script doit etre execute en tant qu'Administrateur!" -ForegroundColor Red
    Write-Host "Clic droit > Executer en tant qu'administrateur" -ForegroundColor Yellow
    pause
    exit 1
}

Write-Host "=== Installation de l'auto-connexion au portail UniFi ===" -ForegroundColor Cyan

$scriptPath = "C:\inetpub\wwwroot\app-magasin\windows-deploy\unifi-auto-login.ps1"
$taskName = "UniFi Auto-Login"

# Verifier que le script existe
if (-not (Test-Path $scriptPath)) {
    Write-Host "ERREUR: Script non trouve a $scriptPath" -ForegroundColor Red
    pause
    exit 1
}

# Supprimer la tache si elle existe deja
$existingTask = Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue
if ($existingTask) {
    Write-Host "Suppression de la tache existante..." -ForegroundColor Yellow
    Unregister-ScheduledTask -TaskName $taskName -Confirm:$false
}

# Creer l'action (executer le script PowerShell)
$action = New-ScheduledTaskAction -Execute "powershell.exe" -Argument "-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File `"$scriptPath`""

# Creer les declencheurs (triggers)
# 1. Au demarrage du systeme (apres 2 minutes)
$triggerStartup = New-ScheduledTaskTrigger -AtStartup
$triggerStartup.Delay = "PT2M"  # 2 minutes apres le demarrage

# 2. Toutes les heures (pour verifier la connexion)
$triggerHourly = New-ScheduledTaskTrigger -Once -At (Get-Date) -RepetitionInterval (New-TimeSpan -Hours 1)

# 3. Tous les jours a 6h, 12h, 18h (moments cles)
$trigger6AM = New-ScheduledTaskTrigger -Daily -At "06:00"
$trigger12PM = New-ScheduledTaskTrigger -Daily -At "12:00"
$trigger6PM = New-ScheduledTaskTrigger -Daily -At "18:00"

# Parametres de la tache
$principal = New-ScheduledTaskPrincipal -UserId "SYSTEM" -LogonType ServiceAccount -RunLevel Highest

$settings = New-ScheduledTaskSettingsSet `
    -AllowStartIfOnBatteries `
    -DontStopIfGoingOnBatteries `
    -StartWhenAvailable `
    -RestartCount 3 `
    -RestartInterval (New-TimeSpan -Minutes 1) `
    -ExecutionTimeLimit (New-TimeSpan -Minutes 5)

# Enregistrer la tache avec tous les declencheurs
Write-Host "Creation de la tache planifiee..." -ForegroundColor Green
$task = Register-ScheduledTask `
    -TaskName $taskName `
    -Action $action `
    -Trigger $triggerStartup,$trigger6AM,$trigger12PM,$trigger6PM `
    -Principal $principal `
    -Settings $settings `
    -Description "Connexion automatique au portail captif UniFi toutes les heures et a chaque demarrage"

# Ajouter le declencheur horaire avec duree indefinie via XML
$taskXml = Export-ScheduledTask -TaskName $taskName
$taskXml = $taskXml -replace '<Enabled>false</Enabled>', '<Enabled>true</Enabled>'

# Ajouter le trigger horaire dans le XML
$hourlyTrigger = @"
    <TimeTrigger>
      <Repetition>
        <Interval>PT1H</Interval>
      </Repetition>
      <StartBoundary>$(Get-Date -Format "yyyy-MM-dd")T00:00:00</StartBoundary>
      <Enabled>true</Enabled>
    </TimeTrigger>
"@

$taskXml = $taskXml -replace '</Triggers>', "$hourlyTrigger</Triggers>"
$taskXml | Set-Content -Path "$env:TEMP\unifi-task.xml" -Encoding UTF8
Register-ScheduledTask -TaskName $taskName -Xml (Get-Content "$env:TEMP\unifi-task.xml" | Out-String) -Force | Out-Null
Remove-Item "$env:TEMP\unifi-task.xml" -Force -ErrorAction SilentlyContinue

Write-Host "`nTache planifiee creee avec succes!" -ForegroundColor Green
Write-Host "`nDetails de la tache:" -ForegroundColor Cyan
Write-Host "  Nom: $taskName" -ForegroundColor White
Write-Host "  Script: $scriptPath" -ForegroundColor White
Write-Host "  Declencheurs:" -ForegroundColor White
Write-Host "    - Au demarrage (apres 2 min)" -ForegroundColor Gray
Write-Host "    - Toutes les heures" -ForegroundColor Gray
Write-Host "    - Tous les jours a 6h, 12h, 18h" -ForegroundColor Gray
Write-Host "  Execution: SYSTEM (droits eleves)" -ForegroundColor White

Write-Host "`nVoulez-vous tester la connexion maintenant? (O/N)" -ForegroundColor Yellow
$response = Read-Host

if ($response -eq "O" -or $response -eq "o") {
    Write-Host "`nExecution du test de connexion..." -ForegroundColor Cyan
    Start-ScheduledTask -TaskName $taskName
    Start-Sleep -Seconds 15

    # Afficher les dernieres lignes du log
    $logFile = "C:\inetpub\wwwroot\app-magasin\windows-deploy\unifi-login.log"
    if (Test-Path $logFile) {
        Write-Host "`n=== Dernieres lignes du log ===" -ForegroundColor Cyan
        Get-Content $logFile -Tail 15
    }
}

Write-Host "`nInstallation terminee!" -ForegroundColor Green
Write-Host "La connexion automatique est maintenant active." -ForegroundColor White
Write-Host "`nPour verifier les logs: C:\inetpub\wwwroot\app-magasin\windows-deploy\unifi-login.log" -ForegroundColor Gray
Write-Host "Pour desinstaller: Get-ScheduledTask -TaskName '$taskName' | Unregister-ScheduledTask" -ForegroundColor Gray

pause
