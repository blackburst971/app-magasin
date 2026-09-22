# Activation de PHP OPcache

## 🚀 Gain de performance attendu : 30-50%

OPcache est un système de cache de bytecode PHP qui améliore considérablement les performances en évitant de recompiler les scripts PHP à chaque requête.

## 📋 Prérequis

- Accès administrateur Windows Server
- Nginx installé et configuré
- PHP 8.2+ installé (avec PHP-FPM ou php-cgi)

## 🔧 Méthode automatique (RECOMMANDÉE)

### Étape 1 : Exécuter le script PowerShell

1. **Ouvrir PowerShell en tant qu'Administrateur** :
   - Clic droit sur le menu Démarrer
   - Sélectionner "Windows PowerShell (Administrateur)"

2. **Naviguer vers le dossier du projet** :
   ```powershell
   cd C:\inetpub\wwwroot\app-magasin
   ```

3. **Exécuter le script d'activation** :
   ```powershell
   .\activate-opcache.ps1
   ```

4. **Suivre les instructions** :
   - Le script créera une sauvegarde de php.ini
   - Il activera OPcache et configurera les paramètres optimaux
   - Il vous proposera de redémarrer Nginx et PHP-FPM automatiquement

### Étape 2 : Vérifier l'activation

Après le redémarrage de Nginx et PHP-FPM, vérifiez que OPcache est bien activé :

```bash
php check-opcache.php
```

Vous devriez voir :
```
✓ OPcache est DISPONIBLE
✓ OPcache est ACTIVÉ
```

## 🛠️ Méthode manuelle

Si vous préférez faire l'activation manuellement :

### 1. Sauvegarder php.ini

```powershell
Copy-Item "C:\Program Files\PHP\php.ini" "C:\Program Files\PHP\php.ini.backup"
```

### 2. Éditer php.ini

Ouvrir `C:\Program Files\PHP\php.ini` avec un éditeur de texte en tant qu'Administrateur.

**Ligne 976** - Activer l'extension OPcache :
```ini
# AVANT
;zend_extension=opcache

# APRÈS
zend_extension=opcache
```

**Section [opcache] (à partir de la ligne 1775)** - Activer et configurer :

```ini
# AVANT
;opcache.enable=1
;opcache.memory_consumption=128
;opcache.interned_strings_buffer=8
;opcache.max_accelerated_files=10000
;opcache.validate_timestamps=1
;opcache.revalidate_freq=2
;opcache.save_comments=1
;opcache.enable_file_override=0

# APRÈS
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=20000
opcache.validate_timestamps=1
opcache.revalidate_freq=60
opcache.save_comments=1
opcache.enable_file_override=1
```

### 3. Redémarrer Nginx et PHP-FPM

```powershell
# Recharger la configuration Nginx
nginx -s reload

# Redémarrer PHP-FPM
Restart-Service php-cgi
# OU si vous utilisez php-fpm
Restart-Service php-fpm
```

### 4. Vérifier

```bash
php -r "echo 'OPcache: ' . (function_exists('opcache_get_status') ? 'ACTIVÉ ✓' : 'DÉSACTIVÉ ✗');"
```

## ⚙️ Configuration appliquée

| Paramètre | Valeur | Description |
|-----------|--------|-------------|
| `opcache.enable` | 1 | Active OPcache |
| `opcache.memory_consumption` | 128 MB | Mémoire allouée au cache |
| `opcache.interned_strings_buffer` | 8 MB | Mémoire pour les chaînes internées |
| `opcache.max_accelerated_files` | 20000 | Nombre max de fichiers en cache (Laravel a beaucoup de fichiers) |
| `opcache.validate_timestamps` | 1 | Vérifie si les fichiers ont changé (désactiver en production pour max perf) |
| `opcache.revalidate_freq` | 60 | Vérifie les changements toutes les 60 secondes |
| `opcache.save_comments` | 1 | Conserve les commentaires (nécessaire pour Laravel/Doctrine) |
| `opcache.enable_file_override` | 1 | Optimise file_exists(), is_file(), etc. |

## 📊 Monitoring

Pour voir les statistiques d'utilisation d'OPcache :

```bash
php check-opcache.php
```

Ou créer une page web `opcache-stats.php` :

```php
<?php
if (function_exists('opcache_get_status')) {
    echo '<pre>';
    print_r(opcache_get_status());
    echo '</pre>';
} else {
    echo 'OPcache non disponible';
}
```

## 🔄 Vider le cache OPcache

Si vous déployez une nouvelle version de l'application :

**Via CLI** :
```bash
php -r "opcache_reset();"
```

**Via Nginx/PHP-FPM** :
```powershell
nginx -s reload
Restart-Service php-cgi
```

**Via Laravel** :
```bash
php artisan optimize:clear
```

## ⚠️ Dépannage

### OPcache ne s'active pas

1. **Vérifier que php_opcache.dll existe** :
   ```powershell
   Test-Path "C:\Program Files\PHP\ext\php_opcache.dll"
   ```

2. **Vérifier les logs d'erreur PHP** :
   - Regarder dans les logs Nginx (`C:\nginx\logs\error.log`)
   - Vérifier les logs PHP-FPM

3. **Vérifier les permissions** :
   - Le dossier `C:\Program Files\PHP\` doit être accessible en lecture

### Performance non améliorée

1. **Vérifier le taux de hit** :
   ```bash
   php check-opcache.php
   ```
   Le taux de succès doit être > 95%

2. **Augmenter la mémoire** si nécessaire :
   ```ini
   opcache.memory_consumption=256
   ```

3. **Désactiver validate_timestamps en production** pour performances maximales :
   ```ini
   opcache.validate_timestamps=0
   ```
   ⚠️ Attention : nécessite un redémarrage de PHP-FPM après chaque déploiement

## 📈 Résultats attendus

Avec OPcache activé, vous devriez observer :

- ✅ **Temps de réponse** : -30% à -50%
- ✅ **Utilisation CPU** : -20% à -40%
- ✅ **Nombre de requêtes supportées** : +50% à +100%
- ✅ **Temps de chargement pages** : Beaucoup plus rapide

## 📝 Notes

- OPcache est déjà inclus dans PHP 8.2+, pas besoin de l'installer séparément
- Les paramètres actuels sont optimisés pour un environnement de production
- Pour un environnement de développement, réduire `revalidate_freq` à 2 secondes

## 🆘 Support

En cas de problème, vérifier :
1. Les logs IIS : `C:\inetpub\logs\LogFiles\`
2. Les logs PHP
3. Exécuter `php -v` et `php -m` pour voir les extensions chargées
