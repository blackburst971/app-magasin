<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SyncKitAssetStatuses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-kit-asset-statuses';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Met à jour le statut des équipements présents dans des kits';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Synchronisation des statuts des équipements dans les kits...');

        // Récupérer tous les équipements qui font partie d'au moins un kit
        $assetsInKits = \App\Models\Asset::whereHas('kits')->with('kits')->get();

        if ($assetsInKits->isEmpty()) {
            $this->info('Aucun équipement dans un kit trouvé.');
            return 0;
        }

        $this->info("Traitement de {$assetsInKits->count()} équipement(s) dans des kits...");

        $stats = ['updated' => 0, 'already_correct' => 0];

        $bar = $this->output->createProgressBar($assetsInKits->count());
        $bar->start();

        foreach ($assetsInKits as $asset) {
            // Vérifier si le statut est déjà correct
            if ($asset->status === 'in_kit') {
                $stats['already_correct']++;
                $bar->advance();
                continue;
            }

            // Mettre à jour le statut
            $asset->update(['status' => 'in_kit']);
            $stats['updated']++;

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Afficher les résultats
        $this->info("✓ {$stats['updated']} équipement(s) mis à jour vers 'in_kit'");
        $this->info("→ {$stats['already_correct']} équipement(s) déjà corrects");

        return 0;
    }
}
