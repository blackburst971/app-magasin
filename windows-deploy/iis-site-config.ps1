#Requires -RunAsAdministrator
<#
.SYNOPSIS
    Configure le site IIS pour App Magasin avec HTTPS (optionnel)
#>
param(
    [string]$AppPath = "C:\inetpub\wwwroot\app-magasin",
    [string]$SiteName = "AppMagasin",
    [int]$HttpPort = 80,
    [string]$Hostname = "",  # ex: magasin.lycee.fr
    [switch]$EnableHttps
)

Import-Module WebAdministration

$physicalPath = "$AppPath\public"
$poolName = "${SiteName}Pool"

Write-Host "Configuration IIS pour App Magasin..." -ForegroundColor Cyan

# 1. Application Pool
if (-not (Test-Path "IIS:\AppPools\$poolName")) {
    New-WebAppPool -Name $poolName
}
Set-ItemProperty "IIS:\AppPools\$poolName" -Name managedRuntimeVersion -Value ""
Set-ItemProperty "IIS:\AppPools\$poolName" -Name processModel.identityType -Value 4  # ApplicationPoolIdentity
Set-ItemProperty "IIS:\AppPools\$poolName" -Name recycling.periodicRestart.time -Value "00:00:00"
Write-Host "  [OK] Application Pool '$poolName' configuré" -ForegroundColor Green

# 2. Website
if (Get-Website -Name $SiteName -ErrorAction SilentlyContinue) {
    Remove-Website -Name $SiteName
}

$binding = "*:${HttpPort}:"
if ($Hostname) { $binding = "*:${HttpPort}:$Hostname" }

New-Website -Name $SiteName -PhysicalPath $physicalPath -Port $HttpPort -ApplicationPool $poolName
Write-Host "  [OK] Site '$SiteName' créé sur port $HttpPort" -ForegroundColor Green

# 3. HTTPS (auto-signed certificate)
if ($EnableHttps) {
    $cert = New-SelfSignedCertificate -DnsName ($Hostname ? $Hostname : "localhost") `
        -CertStoreLocation "cert:\LocalMachine\My" `
        -NotAfter (Get-Date).AddYears(3)
    New-WebBinding -Name $SiteName -Protocol https -Port 443 -SslFlags 0
    $binding443 = Get-WebBinding -Name $SiteName -Protocol https
    $binding443.AddSslCertificate($cert.Thumbprint, "My")
    Write-Host "  [OK] HTTPS activé avec certificat auto-signé" -ForegroundColor Green
}

# 4. Permissions
icacls $physicalPath /grant "IIS_IUSRS:(OI)(CI)F" /T /Q
icacls "$AppPath\storage" /grant "IIS_IUSRS:(OI)(CI)F" /T /Q
icacls "$AppPath\bootstrap\cache" /grant "IIS_IUSRS:(OI)(CI)F" /T /Q
Write-Host "  [OK] Permissions IIS configurées" -ForegroundColor Green

# 5. Default Document
Set-WebConfigurationProperty -PSPath "IIS:\Sites\$SiteName" `
    -Filter "system.webServer/defaultDocument" -Name "enabled" -Value $true
Add-WebConfigurationProperty -PSPath "IIS:\Sites\$SiteName" `
    -Filter "system.webServer/defaultDocument/files" -Name "." -Value @{value="index.php"}

Write-Host "`nSite IIS configuré!" -ForegroundColor Green
Write-Host "URL: http://$(if($Hostname){$Hostname}else{'localhost'}):$HttpPort" -ForegroundColor Cyan
Restart-Service W3SVC
