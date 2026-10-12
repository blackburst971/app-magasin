<?php

/**
 * Script pour ajouter le statut 'damaged' dans asset_statuses
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\AssetStatus;

echo "════════════════════════════════════════════════════════════\n";
echo "  Ajout du statut 'damaged' pour les équipements\n";
echo "════════════════════════════════════════════════════════════\n\n";

echo "Statuts actuels:\n";
$statuses = AssetStatus::all();
foreach ($statuses as $s) {
    echo "  - {$s->key}: {$s->label} (couleur: {$s->color})\n";
}

echo "\n";

// Vérifier si 'damaged' existe déjà
$damaged = AssetStatus::where('key', 'damaged')->first();

if ($damaged) {
    echo "✅ Le statut 'damaged' existe déjà !\n";
    echo "   Label: {$damaged->label}\n";
    echo "   Couleur: {$damaged->color}\n";
} else {
    echo "Création du statut 'damaged'...\n";

    AssetStatus::create([
        'key' => 'damaged',
        'label' => 'Endommagé',
        'color' => 'orange',
        'is_system' => true,
        'blocks_reservation' => true,
        'is_active' => true,
        'order' => 100,
    ]);

    echo "✅ Statut 'damaged' créé avec succès !\n";
    echo "   Label: Endommagé\n";
    echo "   Couleur: Orange\n";
    echo "   Bloque la réservation: Oui\n";
}

echo "\n════════════════════════════════════════════════════════════\n";
echo "  Terminé !\n";
echo "════════════════════════════════════════════════════════════\n";
