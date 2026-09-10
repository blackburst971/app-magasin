# Script de connexion automatique au portail captif UniFi
# Auteur: Claude Code
# Description: Se connecte automatiquement au portail captif UniFi toutes les 24h

# Configuration
$portalUrl = "http://10.15.1.1:8080"
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
        $result = Test-NetConnection -ComputerName "8.8.8.8" -Port 53 -WarningAction SilentlyContinue -ErrorAction SilentlyContinue
        return $result.TcpTestSucceeded
    } catch {
        return $false
    }
}

Write-Log "=== Début de la tentative de connexion au portail UniFi ==="

# Vérifier si on a déjà Internet (déjà connecté)
if (Test-InternetConnection) {
    Write-Log "Connexion Internet déjà active. Aucune action nécessaire."
    exit 0
}

Write-Log "Pas de connexion Internet détectée. Tentative de connexion au portail..."

# Méthode 1: Formulaire POST classique UniFi Guest Portal
try {
    Write-Log "Tentative de connexion via POST classique..."

    # Préparer les données du formulaire
    $body = @{
        'username' = $username
        'password' = $password
    }

    # Tentative de connexion - URL de login typique UniFi
    $loginUrls = @(
        "$portalUrl/guest/s/default/",
        "$portalUrl/login",
        "$portalUrl/auth",
        "$portalUrl/guest/login.php"
    )

    foreach ($url in $loginUrls) {
        try {
            Write-Log "Essai de connexion sur: $url"
            $response = Invoke-WebRequest -Uri $url -Method POST -Body $body -UseBasicParsing -TimeoutSec 10

            if ($response.StatusCode -eq 200) {
                Write-Log "Réponse reçue (Code: $($response.StatusCode))"

                # Vérifier si la connexion a réussi
                Start-Sleep -Seconds 5
                if (Test-InternetConnection) {
                    Write-Log "✓ Connexion réussie! Internet accessible."
                    exit 0
                }
            }
        } catch {
            Write-Log "Échec sur $url : $($_.Exception.Message)"
        }
    }

    # Méthode 2: Format JSON (certains portails UniFi modernes)
    Write-Log "Tentative de connexion via JSON..."
    $jsonBody = @{
        'username' = $username
        'password' = $password
    } | ConvertTo-Json

    foreach ($url in $loginUrls) {
        try {
            Write-Log "Essai JSON sur: $url"
            $response = Invoke-RestMethod -Uri $url -Method POST -Body $jsonBody -ContentType "application/json" -TimeoutSec 10

            Start-Sleep -Seconds 5
            if (Test-InternetConnection) {
                Write-Log "✓ Connexion réussie via JSON! Internet accessible."
                exit 0
            }
        } catch {
            Write-Log "Échec JSON sur $url : $($_.Exception.Message)"
        }
    }

    # Méthode 3: Utilisation d'Internet Explorer COM (pour les redirections complexes)
    Write-Log "Tentative via Internet Explorer COM..."
    $ie = New-Object -ComObject InternetExplorer.Application
    $ie.Visible = $false
    $ie.Navigate($portalUrl)

    # Attendre le chargement de la page
    while ($ie.Busy) { Start-Sleep -Milliseconds 100 }
    Start-Sleep -Seconds 2

    # Remplir le formulaire
    $doc = $ie.Document
    if ($doc.getElementById("username") -ne $null) {
        $doc.getElementById("username").value = $username
        $doc.getElementById("password").value = $password

        # Chercher le bouton de soumission
        $buttons = $doc.getElementsByTagName("button")
        foreach ($button in $buttons) {
            if ($button.type -eq "submit") {
                $button.click()
                break
            }
        }

        Start-Sleep -Seconds 10
        if (Test-InternetConnection) {
            Write-Log "✓ Connexion réussie via IE COM! Internet accessible."
            $ie.Quit()
            [System.Runtime.Interopservices.Marshal]::ReleaseComObject($ie) | Out-Null
            exit 0
        }
    }

    $ie.Quit()
    [System.Runtime.Interopservices.Marshal]::ReleaseComObject($ie) | Out-Null

} catch {
    Write-Log "Erreur générale: $($_.Exception.Message)"
}

# Test final
Start-Sleep -Seconds 5
if (Test-InternetConnection) {
    Write-Log "✓ Connexion établie avec succès!"
    exit 0
} else {
    Write-Log "✗ Échec de la connexion automatique au portail."
    exit 1
}
