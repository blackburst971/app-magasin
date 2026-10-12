<?php

/**
 * Script de correction des statuts d'équipements et kits
 * après validation de préparations
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Reservation;
use App\Models\Asset;
use App\Models\Kit;
use App\Models\Preparation;
use Illuminate\Support\Facades\DB;

echo "═══════════════════════════════════════════════════════════\n";
echo "  Correction des statuts équipements/kits (préparations)\n";
echo "═══════════════════════════════════════════════════════════\n\n";

// 1. Réservations actives avec préparations validées → équipements devraient être 'checked_out'
echo "1. Vérification des réservations actives...\n";

$activeReservations = Reservation::where('status', 'active')
    ->with(['assets', 'kits'])
    ->get();

$fixedAssets = 0;
$fixedKits = 0;

foreach ($activeReservations as $reservation) {
    echo "   Réservation #{$reservation->id} : {$reservation->title}\n";

    foreach ($reservation->assets as $asset) {
        if ($asset->status !== 'checked_out') {
            echo "      ⚠ Asset #{$asset->id} ({$asset->asset_tag}) : {$asset->status} → checked_out\n";
            $asset->update([
                'status' => 'checked_out',
                'checked_out_to_user_id' => $reservation->user_id,
                'expected_return_at' => $reservation->end_date,
            ]);
            $fixedAssets++;
        }
    }

    foreach ($reservation->kits as $kit) {
        if ($kit->status !== 'unavailable') {
            echo "      ⚠ Kit #{$kit->id} ({$kit->name}) : {$kit->status} → unavailable\n";
            $kit->update(['status' => 'unavailable']);
            $fixedKits++;
        }
    }
}

echo "\n2. Vérification des préparations en cours...\n";

$preparingPreps = Preparation::where('status', 'preparing')
    ->with(['assets'])
    ->get();

foreach ($preparingPreps as $prep) {
    echo "   Préparation #{$prep->id} : {$prep->title}\n";

    foreach ($prep->assets as $asset) {
        if ($asset->status !== 'en_preparation') {
            echo "      ⚠ Asset #{$asset->id} ({$asset->asset_tag}) : {$asset->status} → en_preparation\n";
            $asset->update(['status' => 'en_preparation']);
            $fixedAssets++;
        }
    }
}

echo "\n3. Recalcul des statuts de tous les kits...\n";

$allKits = Kit::with('assets')->get();

foreach ($allKits as $kit) {
    $hasInPrep = $kit->assets->contains(fn($a) => $a->status === 'en_preparation');
    $hasCheckedOut = $kit->assets->contains(fn($a) => $a->status === 'checked_out');

    $expectedStatus = ($hasInPrep || $hasCheckedOut) ? 'unavailable' : 'available';

    if ($kit->status !== $expectedStatus) {
        echo "   ⚠ Kit #{$kit->id} ({$kit->name}) : {$kit->status} → {$expectedStatus}\n";
        $kit->update(['status' => $expectedStatus]);
        $fixedKits++;
    }
}

echo "\n═══════════════════════════════════════════════════════════\n";
echo "  ✅ Correction terminée !\n";
echo "  - Équipements corrigés : {$fixedAssets}\n";
echo "  - Kits corrigés : {$fixedKits}\n";
echo "═══════════════════════════════════════════════════════════\n";
