<?php

namespace App\Console\Commands;

use App\Models\Asset;
use App\Models\Place;
use Illuminate\Console\Command;

class SyncAssetPlaces extends Command
{
    protected $signature = 'app:sync-asset-places {--fix-magasin : Corriger les équipements avec "Magasin" vers "Le magasin 211"}';
    protected $description = 'Synchroniser les champs location et place_id des équipements en cherchant les lieux correspondants';

    public function handle()
    {
        // Option spéciale pour corriger "Magasin" → "Le magasin 211"
        if ($this->option('fix-magasin')) {
            return $this->fixMagasinLocation();
        }

        $this->info('🔍 Recherche des équipements avec emplacement (location) renseigné...');
        $this->newLine();

        // Récupérer tous les lieux existants
        $places = Place::all()->keyBy('name');

        if ($places->isEmpty()) {
            $this->warn('Aucun lieu trouvé dans la base de données.');
            return 0;
        }

        $this->info('Lieux disponibles : ' . $places->pluck('name')->implode(', '));
        $this->newLine();

        // Trouver les équipements avec location renseigné
        $assets = Asset::whereNotNull('location')
            ->where('location', '!=', '')
            ->get();

        if ($assets->isEmpty()) {
            $this->info('✓ Aucun équipement avec emplacement à synchroniser.');
            return 0;
        }

        $this->warn("Trouvé {$assets->count()} équipement(s) avec emplacement renseigné.");
        $this->newLine();

        $stats = [
            'synchronized'   => 0,
            'already_synced' => 0,
            'no_match'       => 0,
        ];

        $noMatchLocations = [];

        foreach ($assets as $asset) {
            $locationText = trim($asset->location);

            // Vérifier si un lieu correspond au nom exact
            if ($places->has($locationText)) {
                $place = $places->get($locationText);

                // Vérifier si déjà synchronisé
                if ($asset->place_id === $place->id) {
                    $stats['already_synced']++;
                } else {
                    $asset->update(['place_id' => $place->id]);
                    $stats['synchronized']++;
                }
            } else {
                $stats['no_match']++;
                if (!isset($noMatchLocations[$locationText])) {
                    $noMatchLocations[$locationText] = 0;
                }
                $noMatchLocations[$locationText]++;
            }
        }

        // Afficher les résultats
        $this->newLine();
        $this->info('📊 Résultats :');
        $this->line("  ✓ {$stats['synchronized']} équipement(s) synchronisés");
        $this->line("  → {$stats['already_synced']} équipement(s) déjà synchronisés");
        $this->line("  ⚠ {$stats['no_match']} équipement(s) sans correspondance de lieu");

        if (!empty($noMatchLocations)) {
            $this->newLine();
            $this->warn('📍 Emplacements sans correspondance de lieu :');
            foreach ($noMatchLocations as $location => $count) {
                $this->line("  • « {$location} » : {$count} équipement(s)");
            }
            $this->newLine();
            $this->line('💡 Ces équipements conservent leur emplacement texte mais n\'ont pas de place_id associé.');
            $this->line('   Vous pouvez créer ces lieux dans l\'interface ou modifier les emplacements manuellement.');
        }

        return 0;
    }

    private function fixMagasinLocation()
    {
        $this->info('🔧 Correction des équipements avec emplacement "Magasin"...');
        $this->newLine();

        $targetPlace = Place::where('name', 'Le magasin 211')->first();

        if (!$targetPlace) {
            $this->error('❌ Le lieu "Le magasin 211" n\'existe pas dans la base de données.');
            return 1;
        }

        $assets = Asset::where('location', 'Magasin')->get();

        if ($assets->isEmpty()) {
            $this->info('✓ Aucun équipement avec emplacement "Magasin" trouvé.');
            return 0;
        }

        $this->warn("Trouvé {$assets->count()} équipement(s) avec emplacement \"Magasin\".");
        $this->newLine();

        $bar = $this->output->createProgressBar($assets->count());
        $bar->start();

        foreach ($assets as $asset) {
            $asset->update([
                'location' => 'Le magasin 211',
                'place_id' => $targetPlace->id,
            ]);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("✅ {$assets->count()} équipement(s) mis à jour vers \"Le magasin 211\".");

        return 0;
    }
}
