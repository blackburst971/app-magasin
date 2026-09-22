# Script PowerShell pour activer OPcache PHP
# À exécuter en tant qu'Administrateur

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Activation de PHP OPcache" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

$phpIniPath = "C:\Program Files\PHP\php.ini"

# Vérifier si le fichier existe
if (-Not (Test-Path $phpIniPath)) {
    Write-Host "ERREUR: Le fichier php.ini n'a pas été trouvé à $phpIniPath" -ForegroundColor Red
    exit 1
}

Write-Host "Lecture du fichier php.ini..." -ForegroundColor Yellow

# Lire le contenu du fichier
$content = Get-Content $phpIniPath -Raw

# Créer une sauvegarde
$backupPath = "$phpIniPath.backup_$(Get-Date -Format 'yyyyMMdd_HHmmss')"
Write-Host "Création d'une sauvegarde: $backupPath" -ForegroundColor Yellow
Copy-Item $phpIniPath $backupPath

# Activer OPcache extension
Write-Host "Activation de l'extension OPcache..." -ForegroundColor Yellow
$content = $content -replace ';zend_extension=opcache', 'zend_extension=opcache'

# Activer les paramètres OPcache recommandés
Write-Host "Configuration des paramètres OPcache..." -ForegroundColor Yellow

# opcache.enable
$content = $content -replace ';opcache\.enable=1', 'opcache.enable=1'

# opcache.memory_consumption (128MB recommandé pour production)
$content = $content -replace ';opcache\.memory_consumption=128', 'opcache.memory_consumption=128'

# opcache.interned_strings_buffer (8MB)
$content = $content -replace ';opcache\.interned_strings_buffer=8', 'opcache.interned_strings_buffer=8'

# opcache.max_accelerated_files (augmenté pour Laravel)
$content = $content -replace ';opcache\.max_accelerated_files=10000', 'opcache.max_accelerated_files=20000'

# opcache.validate_timestamps (1 pour développement, 0 pour production max perf)
$content = $content -replace ';opcache\.validate_timestamps=1', 'opcache.validate_timestamps=1'

# opcache.revalidate_freq (60 secondes pour production)
$content = $content -replace ';opcache\.revalidate_freq=2', 'opcache.revalidate_freq=60'

# opcache.save_comments (1 pour Laravel qui utilise les annotations)
$content = $content -replace ';opcache\.save_comments=1', 'opcache.save_comments=1'

# opcache.enable_file_override (optimisation pour file_exists)
$content = $content -replace ';opcache\.enable_file_override=0', 'opcache.enable_file_override=1'

# Écrire le fichier modifié
Write-Host "Écriture des modifications dans php.ini..." -ForegroundColor Yellow
Set-Content -Path $phpIniPath -Value $content -Encoding UTF8

Write-Host ""
Write-Host "========================================" -ForegroundColor Green
Write-Host "OPcache activé avec succès!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Green
Write-Host ""
Write-Host "Configuration appliquée:" -ForegroundColor Cyan
Write-Host "  - zend_extension=opcache : ACTIVÉ" -ForegroundColor White
Write-Host "  - opcache.enable=1 : ACTIVÉ" -ForegroundColor White
Write-Host "  - opcache.memory_consumption=128 : 128 MB" -ForegroundColor White
Write-Host "  - opcache.interned_strings_buffer=8 : 8 MB" -ForegroundColor White
Write-Host "  - opcache.max_accelerated_files=20000 : 20000 fichiers" -ForegroundColor White
Write-Host "  - opcache.validate_timestamps=1 : Validation activée" -ForegroundColor White
Write-Host "  - opcache.revalidate_freq=60 : Vérification toutes les 60s" -ForegroundColor White
Write-Host "  - opcache.save_comments=1 : Commentaires conservés (Laravel)" -ForegroundColor White
Write-Host "  - opcache.enable_file_override=1 : Optimisation file_exists" -ForegroundColor White
Write-Host ""
Write-Host "Sauvegarde créée: $backupPath" -ForegroundColor Yellow
Write-Host ""
Write-Host "IMPORTANT: Vous devez REDÉMARRER Nginx et PHP-FPM pour que les changements prennent effet!" -ForegroundColor Red
Write-Host "Commandes:" -ForegroundColor Yellow
Write-Host "  nginx -s reload" -ForegroundColor White
Write-Host "  Restart-Service php-cgi" -ForegroundColor White
Write-Host ""
Write-Host "Après le redémarrage, vérifiez l'activation avec:" -ForegroundColor Cyan
Write-Host "  php -r `"echo 'OPcache: ' . (function_exists('opcache_get_status') ? 'ACTIVÉ' : 'DÉSACTIVÉ');`"" -ForegroundColor White
Write-Host ""

# Demander si l'utilisateur veut redémarrer Nginx maintenant
$restart = Read-Host "Voulez-vous redémarrer Nginx et PHP-FPM maintenant? (O/N)"
if ($restart -eq "O" -or $restart -eq "o") {
    Write-Host "Redémarrage de Nginx et PHP-FPM..." -ForegroundColor Yellow

    # Recharger la configuration Nginx
    try {
        nginx -s reload
        Write-Host "Nginx rechargé!" -ForegroundColor Green
    } catch {
        Write-Host "Erreur lors du rechargement de Nginx: $_" -ForegroundColor Red
    }

    # Redémarrer PHP-FPM (ou php-cgi selon l'installation)
    try {
        Restart-Service php-cgi -ErrorAction SilentlyContinue
        if ($?) {
            Write-Host "PHP-FPM redémarré!" -ForegroundColor Green
        } else {
            # Si php-cgi n'existe pas, essayer avec php-fpm
            Restart-Service php-fpm -ErrorAction Stop
            Write-Host "PHP-FPM redémarré!" -ForegroundColor Green
        }
    } catch {
        Write-Host "Note: Impossible de redémarrer automatiquement PHP-FPM." -ForegroundColor Yellow
        Write-Host "Redémarrez manuellement le service PHP si nécessaire." -ForegroundColor Yellow
    }

    Write-Host ""
    Write-Host "Vérification de l'activation d'OPcache..." -ForegroundColor Yellow
    php -r "echo 'OPcache: ' . (function_exists('opcache_get_status') ? 'ACTIVÉ ✓' : 'DÉSACTIVÉ ✗') . PHP_EOL;"
    Write-Host ""
    Write-Host "Gain de performance attendu: 30-50% d'amélioration!" -ForegroundColor Green
} else {
    Write-Host "N'oubliez pas de redémarrer Nginx et PHP-FPM manuellement!" -ForegroundColor Yellow
}

Write-Host ""
Write-Host "Script terminé." -ForegroundColor Cyan
