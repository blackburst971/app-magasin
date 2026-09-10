# Auto-connexion au portail captif UniFi

## Description
Ce système permet de se connecter automatiquement au portail captif UniFi sans intervention manuelle après les déconnexions toutes les 24h.

## Fichiers

- `unifi-auto-login.ps1` - Script principal de connexion
- `install-unifi-autologin.ps1` - Installation de la tâche planifiée
- `uninstall-unifi-autologin.ps1` - Désinstallation de la tâche
- `test-unifi-login.ps1` - Test manuel de connexion

## Installation

1. **Ouvrir PowerShell en tant qu'Administrateur**
   - Clic droit sur le bouton Démarrer
   - Sélectionner "Windows PowerShell (Admin)"

2. **Exécuter le script d'installation**
   ```powershell
   cd C:\inetpub\wwwroot\app-magasin\windows-deploy
   .\install-unifi-autologin.ps1
   ```

3. **Confirmer le test de connexion** quand demandé (O/N)

## Fonctionnement

La tâche planifiée se déclenche :
- **Au démarrage** du système (après 2 minutes)
- **Toutes les heures** (pour vérifier la connexion)
- **3 fois par jour** (6h, 12h, 18h) en backup

Le script vérifie d'abord si Internet est accessible. Si non, il tente de se connecter au portail.

## Vérification

### Vérifier que la tâche est active
```powershell
Get-ScheduledTask -TaskName "UniFi Auto-Login"
```

### Exécuter manuellement la tâche
```powershell
Start-ScheduledTask -TaskName "UniFi Auto-Login"
```

### Consulter les logs
```powershell
Get-Content C:\inetpub\wwwroot\app-magasin\windows-deploy\unifi-login.log -Tail 50
```

Ou ouvrir directement le fichier :
```
C:\inetpub\wwwroot\app-magasin\windows-deploy\unifi-login.log
```

## Test manuel

Pour tester la connexion sans attendre la tâche planifiée :
```powershell
cd C:\inetpub\wwwroot\app-magasin\windows-deploy
.\test-unifi-login.ps1
```

## Désinstallation

1. Ouvrir PowerShell en tant qu'Administrateur
2. Exécuter :
   ```powershell
   cd C:\inetpub\wwwroot\app-magasin\windows-deploy
   .\uninstall-unifi-autologin.ps1
   ```

Ou manuellement :
```powershell
Unregister-ScheduledTask -TaskName "UniFi Auto-Login" -Confirm:$false
```

## Dépannage

### Le script ne se connecte pas

1. Vérifier que l'ordinateur est bien sur le réseau WiFi
2. Vérifier que le portail est accessible : http://10.15.1.1:8080
3. Consulter les logs pour voir les erreurs
4. Vérifier les identifiants (username: macedo, password: 64810Mcd)

### Modifier les identifiants

Éditer le fichier `unifi-auto-login.ps1` et modifier les lignes :
```powershell
$username = "macedo"
$password = "64810Mcd"
```

### La tâche ne s'exécute pas

Vérifier l'état de la tâche :
```powershell
Get-ScheduledTask -TaskName "UniFi Auto-Login" | Get-ScheduledTaskInfo
```

### Forcer l'exécution immédiate

```powershell
Start-ScheduledTask -TaskName "UniFi Auto-Login"
```

## Configuration du portail UniFi

- **URL** : http://10.15.1.1:8080
- **Identifiant** : macedo
- **Mot de passe** : 64810Mcd
- **Durée de session** : 24 heures

## Sécurité

⚠️ **Important** : Les identifiants sont stockés en clair dans le script.
- Assurez-vous que seuls les administrateurs ont accès au fichier
- Le script s'exécute avec les droits SYSTEM pour garantir son fonctionnement

## Support

En cas de problème :
1. Consulter les logs
2. Tester manuellement avec `test-unifi-login.ps1`
3. Vérifier la connectivité au portail
4. Réinstaller la tâche planifiée
