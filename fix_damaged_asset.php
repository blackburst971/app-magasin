<?php

/**
 * Script de correction manuelle pour l'équipement 6KP07H16913
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Asset;
use App\Models\Reservation;

echo "════════════════════════════════════════════════════════════\n";
echo "  Correction équipement 6KP07H16913\n";
echo "════════════════════════════════════════════════════════════\n\n";

$asset = Asset::where('asset_tag', '6KP07H16913')->first();

if (!$asset) {
    echo "❌ Équipement non trouvé avec le tag 6KP07H16913\n";
    exit(1);
}

echo "Équipement trouvé: {$asset->name} (ID: {$asset->id})\n";
echo "Statut actuel: {$asset->status}\n\n";

// Trouver la réservation active avec cet équipement
$reservation = Reservation::whereHas('assets', fn($q) => $q->where('assets.id', $asset->id))
    ->whereIn('status', ['active', 'approved'])
    ->first();

if ($reservation) {
    echo "Réservation active trouvée: #{$reservation->id} - {$reservation->title}\n";
    echo "Utilisateur: {$reservation->user->name}\n\n";

    // Marquer comme retourné
    $reservation->assets()->updateExistingPivot($asset->id, [
        'returned_at' => now(),
    ]);

    echo "✅ Équipement marqué comme retourné dans la réservation\n";

    // Vérifier si tous les équipements sont retournés
    $allReturned = $reservation->assets()
        ->wherePivotNull('returned_at')
        ->doesntExist();

    if ($allReturned) {
        $reservation->update(['status' => 'returned']);
        echo "✅ Réservation marquée comme 'returned' (tous équipements retournés)\n";
    } else {
        $remaining = $reservation->assets()
            ->wherePivotNull('returned_at')
            ->count();
        echo "ℹ️  Réservation toujours active ({$remaining} équipement(s) restant(s))\n";
    }
} else {
    echo "ℹ️  Aucune réservation active trouvée pour cet équipement\n";
}

// Mettre à jour le statut de l'équipement
$asset->update([
    'status' => 'damaged',
    'checked_out_to_user_id' => null,
    'expected_return_at' => null,
]);

echo "\n✅ Statut de l'équipement mis à jour: 'damaged'\n";
echo "✅ L'équipement n'est plus réservable et n'apparaît plus dans la réservation\n";

echo "\n════════════════════════════════════════════════════════════\n";
echo "  Correction terminée !\n";
echo "════════════════════════════════════════════════════════════\n";
