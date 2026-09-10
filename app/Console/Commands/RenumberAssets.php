<?php

namespace App\Console\Commands;

use App\Models\Asset;
use App\Models\EquipmentModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RenumberAssets extends Command
{
    protected $signature = 'app:renumber-assets {--rollback : Annuler la dernière renumérotation}';
    protected $description = 'Renommer les équipements selon la nomenclature standard: NomModèle #Numéro';

    private $backupFile = 'storage/asset_names_backup.json';

    public function handle()
    {
        if ($this->option('rollback')) {
            return $this->rollback();
        }

        $this->info('🔍 Analyse des équipements...');
        $this->newLine();

        // Grouper les équipements par modèle
        $models = EquipmentModel::has('assets')
            ->orderBy('name')
            ->get();

        if ($models->isEmpty()) {
            $this->error('Aucun modèle trouvé avec des équipements.');
            return 1;
        }

        $assetsToRename = [];
        $modelsSummary = [];

        foreach ($models as $model) {
            $assets = Asset::where('equipment_model_id', $model->id)
                ->orderBy('name')
                ->get();

            $needsRenaming = [];
            $alreadyCorrect = [];

            foreach ($assets as $asset) {
                // Vérifier si le nom suit le pattern: NomModèle #Numéro
                $expectedPrefix = $model->name;
                $pattern = '/^' . preg_quote($expectedPrefix, '/') . '\s*#\d+$/i';

                if (!preg_match($pattern, $asset->name)) {
                    $needsRenaming[] = $asset;
                } else {
                    $alreadyCorrect[] = $asset;
                }
            }

            if (!empty($needsRenaming)) {
                $modelsSummary[] = [
                    'model' => $model,
                    'needs_renaming' => $needsRenaming,
                    'already_correct' => $alreadyCorrect,
                    'total' => count($assets),
                ];
            }
        }

        if (empty($modelsSummary)) {
            $this->info('✅ Tous les équipements suivent déjà la nomenclature standard !');
            return 0;
        }

        // Afficher le résumé
        $this->warn('📋 Équipements à renommer :');
        $this->newLine();

        $totalToRename = 0;
        foreach ($modelsSummary as $summary) {
            $model = $summary['model'];
            $needsCount = count($summary['needs_renaming']);
            $correctCount = count($summary['already_correct']);
            $totalToRename += $needsCount;

            $this->line("  <fg=cyan>{$model->name}</> : {$needsCount} à renommer, {$correctCount} déjà corrects");

            // Afficher quelques exemples
            foreach (array_slice($summary['needs_renaming'], 0, 3) as $asset) {
                $this->line("    <fg=yellow>→</> {$asset->name}");
            }
            if (count($summary['needs_renaming']) > 3) {
                $remaining = count($summary['needs_renaming']) - 3;
                $this->line("    <fg=gray>... et {$remaining} autre(s)</>");
            }
            $this->newLine();
        }

        $this->warn("Total : {$totalToRename} équipement(s) à renommer");
        $this->newLine();

        // Demander confirmation
        if (!$this->confirm('Voulez-vous procéder à la renumérotation ?', false)) {
            $this->info('Opération annulée.');
            return 0;
        }

        // Sauvegarder les noms actuels pour rollback
        $backup = [];
        foreach ($modelsSummary as $summary) {
            foreach ($summary['needs_renaming'] as $asset) {
                $backup[$asset->id] = $asset->name;
            }
        }
        file_put_contents(storage_path('app/asset_names_backup.json'), json_encode($backup, JSON_PRETTY_PRINT));
        $this->info('💾 Backup créé : storage/app/asset_names_backup.json');

        // Renommer les équipements
        $this->newLine();
        $this->info('🔄 Renumérotation en cours...');
        $bar = $this->output->createProgressBar($totalToRename);
        $bar->start();

        $renamed = 0;
        foreach ($modelsSummary as $summary) {
            $model = $summary['model'];
            $needsRenaming = $summary['needs_renaming'];
            $alreadyCorrect = $summary['already_correct'];

            // Trouver le prochain numéro disponible
            $existingNumbers = [];
            foreach ($alreadyCorrect as $asset) {
                if (preg_match('/#(\d+)$/', $asset->name, $matches)) {
                    $existingNumbers[] = (int)$matches[1];
                }
            }

            $nextNumber = empty($existingNumbers) ? 1 : max($existingNumbers) + 1;

            // Renommer chaque équipement
            foreach ($needsRenaming as $asset) {
                $newName = $model->name . ' #' . $nextNumber;
                $asset->update(['name' => $newName]);
                $nextNumber++;
                $renamed++;
                $bar->advance();
            }
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("✅ {$renamed} équipement(s) renommé(s) avec succès !");
        $this->newLine();
        $this->line('<fg=yellow>💡 Pour annuler cette opération, exécutez :</>');
        $this->line('<fg=cyan>   php artisan app:renumber-assets --rollback</>');

        return 0;
    }

    private function rollback()
    {
        $backupPath = storage_path('app/asset_names_backup.json');

        if (!file_exists($backupPath)) {
            $this->error('❌ Aucun backup trouvé. Impossible d\'annuler.');
            return 1;
        }

        $backup = json_decode(file_get_contents($backupPath), true);

        if (empty($backup)) {
            $this->error('❌ Le fichier de backup est vide ou corrompu.');
            return 1;
        }

        $this->warn('🔙 Rollback : restauration des noms originaux...');
        $this->newLine();

        if (!$this->confirm('Voulez-vous vraiment annuler la renumérotation ?', false)) {
            $this->info('Opération annulée.');
            return 0;
        }

        $bar = $this->output->createProgressBar(count($backup));
        $bar->start();

        $restored = 0;
        foreach ($backup as $assetId => $originalName) {
            $asset = Asset::find($assetId);
            if ($asset) {
                $asset->update(['name' => $originalName]);
                $restored++;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Supprimer le fichier de backup
        unlink($backupPath);

        $this->info("✅ {$restored} équipement(s) restauré(s) à leur nom original !");
        $this->info('💾 Fichier de backup supprimé.');

        return 0;
    }
}
