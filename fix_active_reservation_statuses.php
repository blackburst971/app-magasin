<?php

/**
 * Script de correction des statuts d'équipements dans les réservations actives
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Reservation;
use App\Models\Asset;
use Illuminate\Support\Facades\DB;

echo "════════════════════════════════════════════════════════════\n";
echo "  Correction des statuts - Réservations actives\n";
echo "════════════════════════════════════════════════════════════\n\n";

// Charger toutes les réservations actives
$activeReservations = Reservation::where('status', 'active')
    ->with(['assets', 'kits'])
    ->get();

echo "Réservations actives trouvées : {$activeReservations->count()}\n\n";

$fixedAssets = 0;
$fixedKits = 0;

foreach ($activeReservations as $reservation) {
    echo "─────────────────────────────────────────────────────────────\n";
    echo "Réservation #{$reservation->id} : {$reservation->title}\n";
    echo "Utilisateur : {$reservation->user->name}\n";
    echo "Statut : {$reservation->status}\n";

    $assetsCheckedOut = 0;
    $assetsTotal = $reservation->assets->count();

    foreach ($reservation->assets as $asset) {
        $pivotCheckout = $asset->pivot->checkout_at;

        // Si l'équipement a un checkout_at dans le pivot = il a été scanné
        if ($pivotCheckout) {
            if ($asset->status !== 'checked_out') {
                echo "  ⚠ Asset #{$asset->id} ({$asset->asset_tag}) : {$asset->status} → checked_out\n";
                $asset->update([
                    'status' => 'checked_out',
                    'checked_out_to_user_id' => $reservation->user_id,
                    'expected_return_at' => $reservation->end_date,
                ]);
                $fixedAssets++;
            }
            $assetsCheckedOut++;
        } else {
            // Pas de checkout_at = pas scanné, devrait être 'reserved'
            if ($asset->status !== 'reserved') {
                echo "  ⚠ Asset #{$asset->id} ({$asset->asset_tag}) NON SCANNÉ : {$asset->status} → reserved\n";
                $asset->update([
                    'status' => 'reserved',
                    'checked_out_to_user_id' => null,
                ]);
                $fixedAssets++;
            }
        }
    }

    echo "  Équipements scannés : {$assetsCheckedOut}/{$assetsTotal}\n";

    // Kits : si tous les équipements du kit sont checked_out, le kit devrait être unavailable
    foreach ($reservation->kits as $kit) {
        $kitAssets = $kit->assets;
        $allCheckedOut = $kitAssets->every(fn($a) => $a->status === 'checked_out');

        $expectedStatus = $allCheckedOut ? 'unavailable' : 'reserved';

        if ($kit->status !== $expectedStatus) {
            echo "  ⚠ Kit #{$kit->id} ({$kit->name}) : {$kit->status} → {$expectedStatus}\n";
            $kit->update(['status' => $expectedStatus]);
            $fixedKits++;
        }
    }
}

echo "\n════════════════════════════════════════════════════════════\n";
echo "  ✅ Correction terminée !\n";
echo "  - Équipements corrigés : {$fixedAssets}\n";
echo "  - Kits corrigés : {$fixedKits}\n";
echo "════════════════════════════════════════════════════════════\n";
