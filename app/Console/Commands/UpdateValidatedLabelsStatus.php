<?php

namespace App\Console\Commands;

use App\Models\Asset;
use Illuminate\Console\Command;

class UpdateValidatedLabelsStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-validated-labels-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mettre à jour le statut des équipements dont les étiquettes ont été validées (scannées) vers "disponible"';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Recherche des équipements avec étiquettes validées (scannées)...');

        // Trouver les équipements qui :
        // - N'ont PLUS de cases étiquettes cochées (label_to_create = false ET label_to_apply = false)
        // - Mais sont toujours en statut "en_attente"
        // => Cela signifie que l'étiquette a été validée par scan
        $assets = Asset::where('label_to_create', false)
            ->where('label_to_apply', false)
            ->where('status', 'en_attente')
            ->get();

        $count = $assets->count();

        if ($count === 0) {
            $this->info('✓ Aucun équipement à mettre à jour.');
            return 0;
        }

        $this->warn("Trouvé {$count} équipement(s) avec étiquettes validées à passer en 'disponible'.");

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        foreach ($assets as $asset) {
            // Passer au statut "disponible" et nettoyer label_previous_status
            $asset->update([
                'status' => 'available',
                'label_previous_status' => null,
            ]);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("✓ {$count} équipement(s) mis à jour avec le statut 'disponible'.");

        return 0;
    }
}
