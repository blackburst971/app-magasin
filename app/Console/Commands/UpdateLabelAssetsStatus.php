<?php

namespace App\Console\Commands;

use App\Models\Asset;
use Illuminate\Console\Command;

class UpdateLabelAssetsStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-label-assets-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mettre à jour le statut des équipements avec étiquettes à créer/coller vers "en_attente"';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Recherche des équipements avec étiquettes à créer/coller...');

        // Trouver les équipements avec au moins une case étiquette cochée
        // mais qui n'ont pas le statut "en_attente"
        $assets = Asset::where(function($q) {
                $q->where('label_to_create', true)
                  ->orWhere('label_to_apply', true);
            })
            ->where('status', '!=', 'en_attente')
            ->get();

        $count = $assets->count();

        if ($count === 0) {
            $this->info('✓ Aucun équipement à mettre à jour.');
            return 0;
        }

        $this->warn("Trouvé {$count} équipement(s) à mettre à jour.");

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        foreach ($assets as $asset) {
            // Sauvegarder l'ancien statut
            $asset->update([
                'label_previous_status' => $asset->status,
                'status' => 'en_attente',
            ]);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("✓ {$count} équipement(s) mis à jour avec le statut 'en_attente'.");

        return 0;
    }
}
