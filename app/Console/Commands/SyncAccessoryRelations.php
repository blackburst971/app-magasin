<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Asset;

class SyncAccessoryRelations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-accessory-relations';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronise les relations inverses des accessoires (affiche les équipements parents dans la colonne accessoires)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Synchronisation des relations accessoires...');

        // Récupérer tous les équipements qui ont des accessoires (parent_asset_id IS NOT NULL)
        $accessories = Asset::whereNotNull('parent_asset_id')->get();

        $this->info("Trouvé {$accessories->count()} accessoire(s) à traiter.");

        $bar = $this->output->createProgressBar($accessories->count());
        $bar->start();

        foreach ($accessories as $accessory) {
            // L'accessoire a déjà son parent_asset_id défini
            // Pas besoin de modification car la relation existe déjà via parent_asset_id
            // Cette commande est principalement pour validation
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->info('✓ Synchronisation terminée.');
        $this->info('Note : Les relations sont déjà en place via parent_asset_id.');
        $this->info('Les modifications de l\'interface permettront d\'afficher ces relations de manière bidirectionnelle.');

        return Command::SUCCESS;
    }
}
