#Requires -RunAsAdministrator
<#
.SYNOPSIS
    Configure les tâches planifiées Windows pour Laravel
    - Planificateur de tâches Laravel (schedule:run toutes les minutes)
    - Worker de queue (queue:work en continu)
#>
param([string]$AppPath = "C:\inetpub\wwwroot\app-magasin")

$phpPath = "C:\PHP82\php.exe"
$artisanPath = "$AppPath\artisan"

# Task 1: Laravel Scheduler (every minute)
Write-Host "Création de la tâche planifiée: Laravel Scheduler..." -ForegroundColor Cyan
$action = New-ScheduledTaskAction -Execute $phpPath -Argument "$artisanPath schedule:run" -WorkingDirectory $AppPath
$trigger = New-ScheduledTaskTrigger -RepetitionInterval (New-TimeSpan -Minutes 1) -Once -At (Get-Date)
$settings = New-ScheduledTaskSettingsSet -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Minutes 5)
$principal = New-ScheduledTaskPrincipal -UserId "SYSTEM" -RunLevel Highest

Register-ScheduledTask -TaskName "Laravel Scheduler - App Magasin" `
    -Action $action -Trigger $trigger -Settings $settings -Principal $principal `
    -Description "Exécute le planificateur de tâches Laravel toutes les minutes" -Force

Write-Host "[OK] Tâche 'Laravel Scheduler' créée" -ForegroundColor Green

# Task 2: Queue Worker
Write-Host "Création du service Queue Worker..." -ForegroundColor Cyan
$action2 = New-ScheduledTaskAction -Execute $phpPath -Argument "$artisanPath queue:work --sleep=3 --tries=3 --max-time=3600" -WorkingDirectory $AppPath
$trigger2 = New-ScheduledTaskTrigger -AtStartup
$settings2 = New-ScheduledTaskSettingsSet -MultipleInstances IgnoreNew

Register-ScheduledTask -TaskName "Laravel Queue - App Magasin" `
    -Action $action2 -Trigger $trigger2 -Settings $settings2 -Principal $principal `
    -Description "Worker de queue Laravel pour les emails et jobs asynchrones" -Force

Start-ScheduledTask -TaskName "Laravel Queue - App Magasin"

Write-Host "[OK] Tâche 'Laravel Queue' créée et démarrée" -ForegroundColor Green
Write-Host "`nTâches planifiées configurées avec succès!" -ForegroundColor Green
