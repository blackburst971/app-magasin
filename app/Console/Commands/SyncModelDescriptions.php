<?php

namespace App\Console\Commands;

use App\Models\EquipmentModel;
use Illuminate\Console\Command;

class SyncModelDescriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-model-descriptions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronise les descriptions des modèles d\'équipement vers tous les équipements liés';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Synchronisation des descriptions des modèles...');

        $models = EquipmentModel::whereNotNull('description')
            ->where('description', '!=', '')
            ->withCount('assets')
            ->get();

        if ($models->isEmpty()) {
            $this->info('Aucun modèle avec une description à synchroniser.');
            return 0;
        }

        $totalUpdated = 0;

        foreach ($models as $model) {
            if ($model->assets_count > 0) {
                $updated = $model->assets()->update([
                    'public_description' => $model->description
                ]);

                $totalUpdated += $updated;
                $this->line("✓ {$model->name}: {$updated} équipement(s) mis à jour");
            }
        }

        $this->info("✓ Synchronisation terminée: {$totalUpdated} équipement(s) mis à jour au total.");
        return 0;
    }
}
