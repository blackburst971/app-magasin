#Requires -RunAsAdministrator
<#
.SYNOPSIS
    Installation via Chocolatey (méthode alternative)
    Chocolatey est un gestionnaire de paquets pour Windows (comme apt/brew)
#>

Write-Host "Installation de Chocolatey..." -ForegroundColor Cyan
Set-ExecutionPolicy Bypass -Scope Process -Force
[System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072
Invoke-Expression ((New-Object System.Net.WebClient).DownloadString('https://community.chocolatey.org/install.ps1'))

Write-Host "`nInstallation des outils via Chocolatey..." -ForegroundColor Cyan

# PHP 8.2
choco install php --version=8.2.28 --params '"/ThreadSafe"' -y
# MySQL
choco install mysql --version=8.0.36 -y
# Composer
choco install composer -y
# Node.js LTS
choco install nodejs-lts -y
# IIS URL Rewrite
choco install urlrewrite -y
# Git (optionnel)
choco install git -y

Write-Host "`n[OK] Tous les outils installés via Chocolatey" -ForegroundColor Green
Write-Host "Rafraîchir l'environnement: refreshenv" -ForegroundColor Yellow
Write-Host "Puis lancer: cd C:\inetpub\wwwroot\app-magasin && .\windows-deploy\setup-windows.ps1 -SkipMySQL" -ForegroundColor Yellow
