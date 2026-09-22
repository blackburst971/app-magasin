<?php
/**
 * Script de vérification de l'activation et de la configuration d'OPcache
 */

echo "========================================\n";
echo "Vérification de PHP OPcache\n";
echo "========================================\n\n";

echo "Version PHP: " . PHP_VERSION . "\n\n";

// Vérifier si OPcache est disponible
if (function_exists('opcache_get_status')) {
    echo "✓ OPcache est DISPONIBLE\n\n";

    $status = opcache_get_status();

    if ($status === false) {
        echo "✗ OPcache est installé mais DÉSACTIVÉ\n";
        echo "  Vérifiez que 'opcache.enable=1' est dans php.ini\n\n";
    } else {
        echo "✓ OPcache est ACTIVÉ\n\n";

        echo "--- Statistiques OPcache ---\n";
        echo "Statut: " . ($status['opcache_enabled'] ? 'Activé' : 'Désactivé') . "\n";
        echo "Mémoire utilisée: " . round($status['memory_usage']['used_memory'] / 1024 / 1024, 2) . " MB\n";
        echo "Mémoire libre: " . round($status['memory_usage']['free_memory'] / 1024 / 1024, 2) . " MB\n";
        echo "Mémoire gaspillée: " . round($status['memory_usage']['wasted_memory'] / 1024 / 1024, 2) . " MB\n";
        echo "Nombre de scripts en cache: " . $status['opcache_statistics']['num_cached_scripts'] . "\n";
        echo "Nombre de hits: " . $status['opcache_statistics']['hits'] . "\n";
        echo "Nombre de misses: " . $status['opcache_statistics']['misses'] . "\n";

        if ($status['opcache_statistics']['hits'] > 0) {
            $hitRate = ($status['opcache_statistics']['hits'] / ($status['opcache_statistics']['hits'] + $status['opcache_statistics']['misses'])) * 100;
            echo "Taux de succès: " . round($hitRate, 2) . "%\n";
        }

        echo "\n--- Configuration OPcache ---\n";
        $config = opcache_get_configuration();
        echo "Mémoire allouée: " . $config['directives']['opcache.memory_consumption'] . " MB\n";
        echo "Fichiers max: " . $config['directives']['opcache.max_accelerated_files'] . "\n";
        echo "Validation timestamps: " . ($config['directives']['opcache.validate_timestamps'] ? 'Oui' : 'Non') . "\n";
        echo "Fréquence de revalidation: " . $config['directives']['opcache.revalidate_freq'] . " secondes\n";
        echo "Commentaires sauvegardés: " . ($config['directives']['opcache.save_comments'] ? 'Oui' : 'Non') . "\n";

        echo "\n✓ OPcache fonctionne correctement!\n";
        echo "  Gain de performance attendu: 30-50%\n";
    }
} else {
    echo "✗ OPcache n'est PAS disponible\n\n";
    echo "Solutions:\n";
    echo "1. Vérifiez que l'extension est activée dans php.ini:\n";
    echo "   zend_extension=opcache\n";
    echo "2. Redémarrez IIS: iisreset\n";
    echo "3. Vérifiez que php_opcache.dll existe dans le dossier ext/ de PHP\n";
}

echo "\n========================================\n";
echo "Fin de la vérification\n";
echo "========================================\n";
