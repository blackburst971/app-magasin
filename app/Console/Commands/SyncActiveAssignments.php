<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SyncActiveAssignments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-active-assignments';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronise les emplacements des équipements avec leurs affectations actives';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Synchronisation des emplacements pour les affectations actives...');

        // Récupérer toutes les affectations actives (non encore retournées)
        $activeAssignments = \App\Models\AssetPlaceAssignment::with(['asset', 'place'])
            ->whereNull('returned_at')
            ->get();

        if ($activeAssignments->isEmpty()) {
            $this->info('Aucune affectation active trouvée.');
            return 0;
        }

        $this->info("Traitement de {$activeAssignments->count()} affectation(s) active(s)...");

        $stats = ['updated' => 0, 'already_correct' => 0, 'errors' => 0];

        $bar = $this->output->createProgressBar($activeAssignments->count());
        $bar->start();

        foreach ($activeAssignments as $assignment) {
            try {
                // Vérifier si location/place_id correspondent déjà
                if ($assignment->asset->location === $assignment->place->name
                    && $assignment->asset->place_id === $assignment->place->id) {
                    $stats['already_correct']++;
                    $bar->advance();
                    continue;
                }

                // Mettre à jour l'emplacement et le lieu de l'équipement
                $assignment->asset->update([
                    'location' => $assignment->place->name,
                    'place_id' => $assignment->place->id,
                ]);

                $stats['updated']++;
            } catch (\Exception $e) {
                $stats['errors']++;
                $this->error("\nErreur pour l'équipement {$assignment->asset->asset_tag}: " . $e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Afficher les résultats
        $this->info("✓ {$stats['updated']} équipement(s) mis à jour");
        $this->info("→ {$stats['already_correct']} équipement(s) déjà corrects");

        if ($stats['errors'] > 0) {
            $this->warn("✗ {$stats['errors']} erreur(s)");
        }

        return 0;
    }
}
