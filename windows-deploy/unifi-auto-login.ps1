# Script de connexion automatique au portail captif UniFi
# Configuration
$portalUrl = "http://10.15.1.1"
$username = "macedo"
$password = "64810Mcd"
$logFile = "C:\inetpub\wwwroot\app-magasin\windows-deploy\unifi-login.log"

# Fonction de logging
function Write-Log {
    param($Message)
    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    $logMessage = "[$timestamp] $Message"
    Add-Content -Path $logFile -Value $logMessage
    Write-Host $logMessage
}

# Fonction pour tester la connectivité Internet
function Test-InternetConnection {
    try {
        $result = Test-Connection -ComputerName "8.8.8.8" -Count 1 -Quiet -ErrorAction SilentlyContinue
        return $result
    }
    catch {
        return $false
    }
}

Write-Log "=== Début tentative connexion portail UniFi ==="

# Vérifier si déjà connecté
if (Test-InternetConnection) {
    Write-Log "Internet déjà accessible. Aucune action nécessaire."
    exit 0
}

Write-Log "Pas de connexion Internet. Tentative connexion..."

# Liste des URLs à essayer
$urls = @(
    "$portalUrl/guest/s/default/",
    "$portalUrl/api/login",
    "$portalUrl/login",
    "$portalUrl:8443/guest/s/default/"
)

$success = $false

foreach ($url in $urls) {
    if ($success) { break }

    try {
        Write-Log "Essai: $url"

        $body = @{
            'username' = $username
            'password' = $password
        }

        $response = Invoke-WebRequest -Uri $url -Method POST -Body $body -UseBasicParsing -TimeoutSec 10 -ErrorAction Stop

        Write-Log "Reponse: $($response.StatusCode)"

        Start-Sleep -Seconds 5

        if (Test-InternetConnection) {
            Write-Log "Connexion reussie!"
            $success = $true
            exit 0
        }
    }
    catch {
        Write-Log "Echec $url : $($_.Exception.Message)"
    }
}

# Test final
Start-Sleep -Seconds 3
if (Test-InternetConnection) {
    Write-Log "Connexion etablie!"
    exit 0
}
else {
    Write-Log "Echec connexion automatique"
    exit 1
}
